<?php


class Mage_Novalnet_Model_Observer extends Mage_Payment_Model_Method_Abstract
{

			const PAYMENT_METHOD    = 'Credit Card';			
			protected $_nnPaymentId = 6;			
			protected $_code        = 'novalnetCc';
			
		
			
			/**
     * Instantiate state and set it to state object
     * @param string $paymentAction
     * @param Varien_Object
     */
    	
	public function getReturnURL()
	{
		return Mage::getUrl('novalnet/pci/success', array('_secure' => true));
	}
	
	public function getFormData($observer)
    {
		
				$check_active 		= $this->getConfigData('active');
				$dataObj	= new Varien_Object();
				$dataObj->setCheckActive($check_active);
			if($check_active=='1')
			{
			
				$paymentid 			= $this->_nnPaymentId;
				$vendorid 			= $this->getConfigData('merchant_id');
				$vendorAuthcode 	= $this->getConfigData('auth_code');
				$pid    			= $this->getConfigData('product_id');
				$tid    			= $this->getConfigData('tariff_id');
				$uniqid    			= uniqid(); 
				$product2 			= $this->getConfigData('second_product_id');
				$tariff2  			= $this->getConfigData('second_tariff_id');
				$manualCheckAmt 	= (int)$this->getConfigData('manual_checking_amount');
				$test_mode			= (!$this->getConfigData('live_mode'))? 1: 0;
				$password			= $this->getConfigData('password');
				$order_status		= $this->getConfigData('order_status');
				$createinvoice		= $this->getConfigData('createinvoice');
				
				$returnURL			= $this->getReturnURL();
				$returnmethod		= 'POST';
				$errorreturnURL 	= $this->getReturnURL();
				$errorreturnmethod	= 'POST';
				$input1				= 'order_id';
				$Implementation		= 'PHP_PCI';
				$sessionid			= Mage::getSingleton('checkout/session')->getSessionId();


				$dataObj->setCheckActive($check_active)
						->setPaymentId($paymentid)			
						->setVendorAuthcode($vendorAuthcode)						
						->setVendorId($vendorid)
						->setVendorAuthcode($vendorAuthcode)
						->setProductId($pid)
						->setTariffId($tid)
						->setProductId2($product2)
						->setTariffId2($tariff2)
						->setManualCheckAmt($manualCheckAmt)
						->setUniqid($uniqid)
						->setTestMode($test_mode)
						->setPassword($password)
						->setLang()									
						->setRemoteIp(Mage::helper('novalnet')->getRealIpAddr())	
						->setSessionId($sessionid)
						->setReturnUrl($returnURL)
						->setReturnMethod($returnmethod)
						->setErrorReturnUrl($errorreturnURL)
						->setErrorReturnMethod($errorreturnmethod)
						->setInput1($input1)
						->setImplementation($Implementation)
						->setOrderStatus($order_status)
						->setCreateInvoice($createinvoice);
						
			}
		
				$session=Mage::getSingleton('checkout/session')->setNnDataValue($dataObj);
	
    }
	
 /* 
  * To Set the Order Status for CCiframe during redirection .
  *
  */	
	
	public function SaveOrderStatus	()
	{
		$paymentmethod=Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getMethodInstance()->getCode();
		if($paymentmethod == 'novalnetCc')
		{
			$orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
			$order   = Mage::getModel('sales/order')->loadByIncrementId($orderId);
			$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT)
					->addStatusToHistory(
						Mage_Sales_Model_Order::STATE_HOLDED,
						Mage::helper('novalnet')->__('Customer was redirected to Novalnet'),
						true
					)->save();
		}
	}

}

?>