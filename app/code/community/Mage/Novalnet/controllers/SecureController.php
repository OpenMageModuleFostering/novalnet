<?php
class Mage_Novalnet_SecureController extends Mage_Core_Controller_Front_Action
{
    protected function _expireAjax()
    {
        if (!$this->getCheckout()->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
            exit;
        }
    }

    /**
     * Redirect Block
     * need to be redeclared
     */
    protected $_redirectBlockType  = 'novalnet/secure_redirect';

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * when customer select novalnet payment method
     */
    public function redirectAction()
    {
        $session = $this->getCheckout();
        $session->setNovalnetQuoteId($session->getQuoteId());
        $session->setNovalnetRealOrderId($session->getLastRealOrderId());

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($session->getLastRealOrderId());
        $order->addStatusToHistory($order->getStatus(), Mage::helper('novalnet')->__('Customer was redirected to Novalnet.'));
        $order->save();

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock($this->_redirectBlockType)
                ->setOrder($order)
                ->toHtml()
        );

        $session->unsQuoteId();
    }

    /**
     * novalnet returns POST variables to this action
     */
    public function  successAction()
    {
        $status = $this->_checkReturnedPost();

        $session = $this->getCheckout();

        $session->unsNovalnetRealOrderId();
        $session->setQuoteId($session->getNovalnetQuoteId(true));
        $session->getQuote()->setIsActive(false)->save();

        $order = Mage::getModel('sales/order');
        $order->load($this->getCheckout()->getLastOrderId());
        if($order->getId()) {
            $order->sendNewOrderEmail();
        }

        if ($status) {
            $this->_redirect('checkout/onepage/success');
        } else {
            $this->_redirect('*/*/failure');
        }
    }

    /**
     * Display failure page if error
     *
     */
    public function failureAction()
    {
        if (!$this->getCheckout()->getNovalnetErrorMessage()) {
            $this->norouteAction();
            return;
        }

        $this->getCheckout()->clear();

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Checking POST variables.
     * Creating invoice if payment was successfull or cancel order if payment was declined
     */
    protected function _checkReturnedPost()
    {
        if (!$this->getRequest()->isPost()) {
            $this->norouteAction();
            return;
        }
        $status = true;
        $response = $this->getRequest()->getPost();
        //error_log(print_r($response,true),3,'/tmp/magento_response.log');

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($response['inputval1']);
        $payment = $order->getPayment();
        $paymentInst = $payment->getMethodInstance();
      
        $paymentInst->setResponse($response);

        if ($response['status'] == 100 ) {

           // if ($order->canInvoice()) {
                $invoice = $order->prepareInvoice();
                $invoice->register()->capture();
                Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();

                $paymentInst->setTransactionId($response['tid']);
                $payment->setLastTransId($response['tid']);
                $payment->setCcTransId($response['tid']);
                $order->addStatusToHistory($order->getStatus(), Mage::helper('novalnet')->__('Customer successfully returned from Novalnet'));
            //}
        } else {
            $paymentInst->setTransactionId($response['tid']);
            $payment->setLastTransId($response['tid']);
            $payment->setCcTransId($response['tid']);
            $order->cancel();
            $order->addStatusToHistory($order->getStatus(), Mage::helper('novalnet')->__('Customer was rejected by Novalnet'));
            $status = false;
            $this->getCheckout()->setNovalnetErrorMessage($response['status_text']);
        }

        $order->save();

        return $status;
    }

}
?>