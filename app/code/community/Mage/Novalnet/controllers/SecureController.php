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
	
	private function _getConfigData($var)
	{
		return Mage::getModel( 'novalnet/novalnetSecure' )->getConfigData( $var );
	}

    /**
     * when customer select novalnet payment method
     */
    public function redirectAction()
    {
        $session = $this->getCheckout();
		$session->setNovalnetQuoteId($session->getQuoteId())
				->setNovalnetRealOrderId($session->getLastRealOrderId());
		
		$order = Mage::getModel('sales/order')
					->loadByIncrementId($session->getLastRealOrderId());
		$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT)
			  ->addStatusToHistory(
				Mage_Sales_Model_Order::STATE_HOLDED, #$order->getStatus(),
				Mage::helper('novalnet')->__('Customer was redirected to Novalnet'),
				true
			  )->save();
		
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
		if ($this->_checkReturnedPost()) {
			$this->_redirect('checkout/onepage/success');
		} else {
			$this->_redirect('checkout/onepage/failure');
		}
    }
	
    /**
     * Checking POST variables.
     * Creating invoice if payment was successfull or cancel order if payment was declined
     */
    protected function _checkReturnedPost()
    {
		$status  = false;
		if (!$this->getRequest()->isPost()) {
			$this->norouteAction();
			return $status;
		}
		$session = $this->getCheckout();
		$dataObj = new Varien_Object($this->getRequest()->getPost());
		if (   $dataObj->hasOrderId()
			&& $session->hasNovalnetRealOrderId()
			&& $dataObj->getOrderId() == $session->getNovalnetRealOrderId())
		{
			$session->setQuoteId($session->getNovalnetQuoteId());
			$session->getQuote()->setIsActive(false)->save();
			
			$order = Mage::getModel('sales/order')
						->loadByIncrementId($dataObj->getOrderId());
			$payment = $order->getPayment();
			
			if ($dataObj->getStatus() == Mage_Novalnet_Model_NovalnetSecure::RESPONSE_CODE_APPROVED) {
				$payment->setStatus(Mage_Novalnet_Model_NovalnetSecure::STATUS_SUCCESS)
						->setStatusDescription(Mage::helper('novalnet')->__('Payment was successful.'))
						->setTransactionId($dataObj->getTid())
						->setSuTransactionId($dataObj->getTid())
						->setLastTransId($dataObj->getTid());
				Mage::getSingleton('core/session')->addSuccess(Mage::helper('novalnet')->__('successful'));
				$order->setPayment($payment);
				 //Added      
				$session->unsSecureTestOrder();
				if( $dataObj->hasTestMode() ) {
					Mage::getModel( 'sales/quote' )
						->load($session->getNovalnetQuoteId())
						->getPayment()
						->setNnTestorder($dataObj->getTestMode())
						->save();
               $session->setSecureTestOrder($dataObj->getTestMode());
				}

				$invoice = $order->prepareInvoice();
				$invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)
						->register()
						->capture();
				Mage::getModel('core/resource_transaction')
					->addObject($invoice)
					->addObject($invoice->getOrder())
					->save();
				
				$order->addStatusToHistory( 
					$this->_getConfigData('order_status'),
					Mage::helper('novalnet')->__('Customer successfully returned from Novalnet'),
					true
				)->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
				->save();
				if ($order->getId()) {
					$order->sendNewOrderEmail();
				}
				$status = true;
            $session->unsSecureTestOrder()
                    ->unsNnSecureTestOrder();
			} else {
				$payment->setStatus(Mage_Novalnet_Model_NovalnetSecure::STATUS_ERROR);
				$payment->setStatusDescription(Mage::helper('novalnet')->__('Payment was fail.'));
				$order->setPayment($payment);
				if ($dataObj->getStatus() == 20){
					$order->addStatusToHistory(
						$order->getStatus(),
						Mage::helper('novalnet')->__('Customer aborted payment process'),
						true
					);
				} else {
					$order->addStatusToHistory(
						$order->getStatus(),
						Mage::helper('novalnet')->__('Customer was rejected by Novalnet'),
						true
					);
				}
				$order->cancel()
					  ->save();
				$order->setState(Mage_Sales_Model_Order::STATE_CANCELED)->save();
				Mage::getSingleton('core/session')->addError(html_entity_decode($dataObj->getStatusText()));
				Mage::getSingleton('checkout/session')
					 ->setErrorMessage(html_entity_decode($dataObj->getStatusText()));
			}
		}
		$session->unsNovalnetRealOrderId();
		$session->unsNovalnetQuoteId();
		return $status;
    }
}
