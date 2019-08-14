<?php
class Mage_Novalnet_InstantbanktransferController extends Mage_Core_Controller_Front_Action
{
	private $debug = false; #todo: set to false
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
    protected $_redirectBlockType  = 'novalnet/instantbanktransfer_redirect';#path = block/instantbanktransfer/redirect.php

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
        $order->addStatusToHistory($order->getStatus(), Mage::helper('novalnet')->__('Customer was redirected to Novalnet'));
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

        if ($status) {
            $session = $this->getCheckout();

            $session->unsNovalnetRealOrderId();
            $session->setQuoteId($session->getNovalnetQuoteId(true));
            $session->getQuote()->setIsActive(false)->save();

            $order = Mage::getModel('sales/order');
            $order->load($this->getCheckout()->getLastOrderId());
            if($order->getId()) {
                $order->sendNewOrderEmail();
            }

        #if ($status) {#removed to line 61
            $this->_redirect('checkout/onepage/success');
        } else {
            #$this->_redirect('checkout/onepage/failure');#ok, but not so good; $this->_redirect('*/*/failure');
            $this->_redirect('checkout/cart');#new; ok
            #$this->_redirect('checkout/shipping');#new; not ok, nor: 'checkout/payment'
        }
    }

    /**
     * Display failure page if error
     *
     */
    public function failureAction()
    {
        $status = $this->_checkReturnedPost();#new
        if (!$this->getCheckout()->getNovalnetErrorMessage()) {
            $this->norouteAction();
            #$this->_redirect('checkout/onepage/payment');
            return;
        }

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
        $order->loadByIncrementId($response['inputval1']);#order_id;
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
                $order->save();
            //}
        } else {
            $paymentInst->setTransactionId($response['tid']);
            $payment->setLastTransId($response['tid']);
            $payment->setCcTransId($response['tid']);

            if ($response['status'] == 94)
            {
                Mage::getSingleton('checkout/session')->addError("Customer abortet payment process");#new
                $order->addStatusToHistory($order->getStatus(), Mage::helper('novalnet')->__('Customer abortet payment process'));
            }
            else
            {
                $order->addStatusToHistory($order->getStatus(), Mage::helper('novalnet')->__('Customer was rejected by Novalnet'));
            }
            #$order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, "Failure:'No extra information'");#new
            $order->cancel();
            $status = false;
            $this->getCheckout()->setNovalnetErrorMessage($response['status_text']);
            $order->save();
            $this->deleteOrder($increment_id = $response['inputval1']);#new
        }
        return $status;
    }

	public function debug2($object, $filename, $debug = false)
	{
		if (!$this->debug and !$debug){return;}
		$fh = fopen("/tmp/$filename", 'a+');
		if (gettype($object) == 'object' or gettype($object) == 'array'){
			fwrite($fh, serialize($object));
		}else{
			fwrite($fh, date('H:i:s').' '.$object);
		}
		fwrite($fh, "<hr />\n");
		fclose($fh);
	}

    private function deleteOrder($increment_id)
    {
        $conn = Mage::getSingleton('core/resource')->getConnection('core_write');
        #$conn   = Mage::getResourceSingleton('core/resource')->getConnection('core_write');
        $query  = "select entity_id from sales_order_entity where increment_id='$increment_id';";
        if ($result = $conn->query($query))
        {
            if ($rows = $result->fetch(PDO::FETCH_ASSOC))
            {
                $order_id = $row['entity_id'];
                $query    = "delete from sales_order_entity where entity_id='$order_id' or parent_id='$order_id';";
                $conn->query($query);
            }
        }
        $query = "delete from sales_order where increment_id='$increment_id';";
        $conn = Mage::getSingleton('core/resource')->getConnection('core_write');
        $conn->query($query);
    }
}
?>