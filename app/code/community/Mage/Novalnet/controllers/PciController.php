<?php
class Mage_Novalnet_PciController extends Mage_Core_Controller_Front_Action
{
	protected $_responseText;
	
	protected function _expireAjax()
	{
		if (!$this->getCheckout()->getQuote()->hasItems()) {
			$this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
			exit;
		}
	}
	/**
	* Get singleton of Checkout Session Model
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
				->createBlock($order->getPayment()->getMethodInstance()->getRedirectBlockType())
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
            $url = Mage::getModel('core/url')->getUrl("checkout/onepage/success");
			Mage::getSingleton('core/session')->addSuccess($this->_responseText);
		} else {
            $url = Mage::getModel('core/url')->getUrl("checkout/onepage/failure");
			Mage::getSingleton('core/session')->addError($this->_responseText);
			Mage::getSingleton('checkout/session')->setErrorMessage(html_entity_decode($this->_responseText));
		}
		Mage::app()->getResponse()->setRedirect($url);
        Mage::app()->getResponse()->sendResponse();			
		exit;
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
		$response   = $this->getRequest()->getPost();
		$session = $this->getCheckout();
		$dataObj = new Varien_Object($response);
		if ($dataObj->hasOrderId()
			&& $session->hasNovalnetRealOrderId()
			&& $dataObj->getOrderId() == $session->getNovalnetRealOrderId()) {
			
			$order = Mage::getModel('sales/order')->loadByIncrementId($dataObj->getOrderId());
			$status=$order->getPayment()->getMethodInstance()->statusCheck($response,$session);
			$this->_responseText = $response['status_desc'];
		}
                
		return $status;
	}
}
