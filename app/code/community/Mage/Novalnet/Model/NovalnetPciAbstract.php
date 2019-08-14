<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * Part of the Paymentmodul of Novalnet AG
 * http://www.novalnet.de 
 * If you have found this script usefull a small        
 * recommendation as well as a comment on merchant form 
 * would be greatly appreciated.
 * 
 * @category   design_default
 * @package    Mage
 * @copyright  Copyright (c) 2012 Novalnet AG
 * @version    1.0.0
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Novalnet_Model_NovalnetPciAbstract extends Mage_Payment_Model_Method_Abstract
{
	const RESPONSE_CODE_APPROVED 	= 100;
	const RESPONSE_CODE_ABORT    	= 20;
	const CGI_URL                	= 'https://payport.novalnet.de/pci_payport';
	/**
	* unique internal payment method identifier
	* 
	* @var string [a-z0-9_]
	*/
	protected $_formBlockType 		= 'novalnet/pci_form';
	protected $_infoBlockType 		= 'novalnet/pci_info';
	protected $_redirectBlockType 	= 'novalnet/pci_redirect';
	protected $_formPHTML 			= 'novalnet/pci/form.phtml';
	protected $_infoPHTML 			= 'novalnet/pci/info.phtml';
	protected $_pdfPHTML 			= 'payment/info/pdf/pci.phtml';
	protected $_nnPaymentId;
	protected $_code;

    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway               = false;
	
    /**
     * Can authorize online?
     */
    protected $_canAuthorize            = true;
	
    /**
     * Can capture funds online?
     */
    protected $_canCapture              = false; #important; default: false
	
    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial       = false;
	
    /**
     * Can refund online?
     */
    protected $_canRefund               = false;
	
    /**
     * Can void transactions online?
     */
    protected $_canVoid                 = false;
	
    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal          = false;
	
    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout          = true;
    
    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping  = false;
	
    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc               = false;
	
	protected $_isInitializeNeeded      = true;
	
	/**
     * Instantiate state and set it to state object
     * @param string $paymentAction
     * @param Varien_Object
     */
    public function initialize($paymentAction, $stateObject)
    {
    }
	
	public function getRedirectBlockType()
	{
		return $this->_redirectBlockType;
	}
	
	public function getReturnURL()
	{
		return Mage::getUrl('novalnet/pci/success', array('_secure' => true));
	}
	
    public function getBookingReference()
    {
        return $this->getConfigData('booking_reference');
    }
	
	public function getTitle()
    {
        return $this->getConfigData('title');
    }
	
	/**
     * To display the payment method based on the condition
     * @param Varien_Object $quote
     */
	public function isAvailable($quote = null) {
	
		$minOrderCount = trim($this->getConfigData('orderscount'));
		$customerId = Mage::getSingleton('customer/session')->getCustomerId();
       
	        // Load orders and check
            $orders = Mage::getResourceModel('sales/order_collection')
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('customer_id', $customerId)
                ->load();
			
            //added   
            if (count($orders) < $minOrderCount) {
                return false;
            }
		return parent::isAvailable();
	}
	
	public function getFormData()
    {
		$dataObj	= new Varien_Object();
				
		$dataObj->setPaymentId($this->_nnPaymentId)
				->setNnMethod($this->_code);
				
		$this->_importNovalnetFormData($dataObj)
				->_importBillingData($dataObj)
				->_importUrlData($dataObj)
				->_importEncodeData($dataObj)
				->_importHashData($dataObj);
		return $dataObj;
    }
	
	/**
     * To import Novalnet form data
     * @param Varien_Object $dataObj
     */
    public function _importNovalnetFormData($dataObj) {
		$pid    		= trim($this->getConfigData('product_id'));
		$tid    		= trim($this->getConfigData('tariff_id'));	
		$product2 		= trim($this->getConfigData('second_product_id'));
		$tariff2  		= trim($this->getConfigData('second_tariff_id'));
		if($this->_checkPaymentType()) {
			$grand_total	= Mage::getSingleton('checkout/session')->getQuote()->getGrandTotal();
			$amount 		= (round($grand_total, 2) * 100);
		}else{
			$order          = $this->getInfoInstance()->getOrder();
			$amount 		= (round($order->getBaseGrandTotal(), 2) * 100);
		}
		$manualCheckAmt = (int)$this->getConfigData('manual_checking_amount');

		if($manualCheckAmt && $amount>=$manualCheckAmt && $product2 && $tariff2) {
			$pid    	= trim($this->getConfigData('second_product_id'));
			$tid    	= trim($this->getConfigData('second_tariff_id'));
		}
		
		$uniqid    		= uniqid(); 
		
		$test_mode		= (!$this->getConfigData('live_mode'))? 1: 0;
		
		$dataObj->setVendorId(trim($this->getConfigData('merchant_id')))
				->setVendorAuthcode(trim($this->getConfigData('auth_code')))
				->setProductId(trim($pid))
				->setTariffId(trim($tid))
				->setUniqid($uniqid)
				->setAmount($amount)
				->setTestMode($test_mode)
				->setPaymentId($this->_nnPaymentId)
				->setCustomerNo(Mage::getSingleton('customer/session')->getCustomerId()) 
				->setUseUtf8(1);
		
		return $this;
	}
	
	/**
     * To import Billing data
     * @param Varien_Object $dataObj
     */
	public function _importBillingData($dataObj) {
		if($this->_checkPaymentType()) {
			$billing	= Mage::getModel('checkout/session')->getQuote()->getBillingAddress();
			$email_first_register = Mage::getSingleton('customer/session')->getCustomer()->getEmail();
   		$email_address = $billing->getEmail();
   		if(trim($email_address)=="")
    			$email_address = $email_first_register;
			$dataObj->setCurrency(Mage::app()->getStore()->getCurrentCurrencyCode())
					->setEmail($email_address);

		}else {
			$order  	= $this->getInfoInstance()->getOrder();
            $billing	= $order->getBillingAddress();
			$objQuote = $objQuote 	= Mage::getModel( 'sales/quote' );
			$objQuote->setStoreId($order->getStoreId())->load($order->getQuoteId());
			$objQuotePayment = $objQuote->getPayment();
			$objQuotePayment->setNnTestOrder((!$this->getConfigData('live_mode'))? 1: 0)->save();
			$dataObj->setCurrency($order->getOrderCurrencyCode())
					->setEmail($order->getCustomerEmail());
		}
		$dataObj->setFirstname($billing->getFirstname())
				->setLastname($billing->getLastname())
				->setStreet($billing->getData('street'))
				->setSearchInStreet(1)
				->setCity($billing->getCity())
				->setZip($billing->getPostcode())
				->setCountryCode($billing->getCountry())
				->setLang(substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2))
				->setRemoteIp(Mage::helper('novalnet')->getRealIpAddr())
				->setTel($billing->getTelephone())
				->setFax($billing->getFax());
		return $this;
	}
	
	/**
     * To import URL data
     * @param Varien_Object $dataObj
     */
	public function _importUrlData($dataObj) {
		if($this->_checkPaymentType()) {
			$magento_version=Mage::getVersion();
			$dbc_collect_order = Mage::getSingleton('core/resource')->getConnection('core_read');
			$store_id = Mage::app()->getStore()->getId();
         if($magento_version<'1.4.1.0'){
            $tableSalesOrder  =  Mage::getSingleton('core/resource')->getTableName('sales_order');
            $result    =  $dbc_collect_order->query("SELECT `increment_id` FROM `".$tableSalesOrder."` where store_id= ".$store_id." ORDER BY `entity_id` DESC LIMIT 1");
       }else{
            $tableSalesFlatOrder  =  Mage::getSingleton('core/resource')->getTableName('sales_flat_order');
            $result        =  $dbc_collect_order->query("SELECT `increment_id` FROM `".$tableSalesFlatOrder."` where store_id= ".$store_id." ORDER BY `entity_id` DESC LIMIT 1");
        }
			$result_data		  =  $result->fetch(PDO::FETCH_ASSOC);
			$order_id_data		  =  $result_data['increment_id'];
			if(!$order_id_data)
			{
				$last_main_order_id = '100000000';
				$inputval1			= $last_main_order_id+1;
			}
			else
			{
				$last_main_order_id = $order_id_data; 
				$inputval1			= $last_main_order_id+1;
			}
			$dataObj->setOrderNo($inputval1)
					->setInputval1($inputval1);
		}else {
			$order  = $this->getInfoInstance()->getOrder();
			$dataObj->setOrderNo($order->getRealOrderId())
					->setInputval1($order->getRealOrderId());
		}
		$dataObj->setSession(Mage::getSingleton('checkout/session')->getSessionId())
				->setReturnUrl($this->getReturnURL())
				->setReturnMethod('POST')
				->setErrorReturnUrl($this->getReturnURL())
				->setErrorReturnMethod('POST')
				->setInput1('order_id')
				->setImplementation('PHP_PCI');
		
		return $this;
	}

	/**
     * To get Hash data
     * @param Varien_Object $dataObj
     */
	public function _importHashData($dataObj) {
		$hash = Mage::helper('novalnet')->generateHash($dataObj, trim($this->getConfigData('password')));
		if($hash == false) {
			Mage::getSingleton('core/session')
                ->addError('Die Hashfunktionen sind nicht verf&uuml;gbar!');
            $url = Mage::getModel('core/url')->getUrl("checkout/onepage/failure");
            Mage::app()->getResponse()->setRedirect($url);
            Mage::app()->getResponse()->sendResponse();
			exit;
		}
		$dataObj->setHash($hash);
		return $this;
	}
	
	/**
     * To get encode data
     * @param Varien_Object $dataObj
     */
	public function _importEncodeData($dataObj) {
		$encoding = Mage::helper('novalnet')->encode($dataObj, trim($this->getConfigData('password')));
		if($encoding != true) {
			Mage::getSingleton('core/session')
                ->addError('Die Methoden f&uuml;r die Verarbeitung von Zeichens&auml;tzen sind nicht verf&uuml;gbar!');
            $url = Mage::getModel('core/url')->getUrl("checkout/onepage/failure");
            Mage::app()->getResponse()->setRedirect($url);
            Mage::app()->getResponse()->sendResponse();
			exit;
		}
		return $this;
	}
	
	/**
     * To Validate Admin Back End Parameters
     */
    public function validate() {
		parent::validate();
		if(!trim($this->getConfigData('merchant_id')) || !trim($this->getConfigData('auth_code')) || !trim($this->getConfigData('product_id')) || !trim($this->getConfigData('tariff_id')) || !trim($this->getConfigData('password'))) {
			Mage::throwException(Mage::helper('novalnet')->__('Basic Parameter Missing').'!');
		}
		if((int)$this->getConfigData('manual_checking_amount')&& (!trim($this->getConfigData('second_product_id')) || !trim($this->getConfigData('second_tariff_id')))) {
			Mage::throwException(Mage::helper('novalnet')->__('Required Second Product Tariff').'!');
		}   
		return $this;
	}
	
	/**
     * Check status based on server response
     * @param Varien_Object $response
     * @param Varien_Object $session
     */
	public function statusCheck($response,$session) {
		$status  = false;
		$dataObj = new Varien_Object($response);
		
		$order = Mage::getModel('sales/order')
				->loadByIncrementId($dataObj->getOrderId());
		$payment = $order->getPayment();
		
		if(Mage::helper('novalnet')->checkHash($response, trim($this->getConfigData('password')))) {
			if ($response['status'] == Mage_Novalnet_Model_NovalnetCcpci::RESPONSE_CODE_APPROVED) {
				$status = $this->onSuccess($dataObj,$order,$payment,$session);
			} else {
				$status = $this->onFailure($dataObj,$order,$payment,$session);
			}
		}else {
		
			Mage::getSingleton('core/session')
                ->addError(Mage::helper('novalnet')->__('Die Hashfunktionen sind nicht verf&uuml;gbar!'));
            $url = Mage::getModel('core/url')->getUrl("checkout/onepage/failure");
            Mage::app()->getResponse()->setRedirect($url);
            Mage::app()->getResponse()->sendResponse();
			exit;
		}
		$session->unsNovalnetRealOrderId();
		$session->unsNovalnetQuoteId();
		return $status;
	}
	
	/**
     * On success based on server response
     * @param Varien_Object $dataObj
     * @param Varien_Object $order
     * @param Varien_Object $payment
     * @param Varien_Object $session
     */
	public function onSuccess($dataObj,$order,$payment,$session) {
		$payment->setStatus(Mage_Novalnet_Model_NovalnetCcpci::STATUS_SUCCESS)
				->setStatusDescription(Mage::helper('novalnet')->__('Payment was successful.'))
				->setTransactionId($dataObj->getTid())
				->setSuTransactionId($dataObj->getTid())
				->setLastTransId($dataObj->getTid());

		$session->setQuoteId($session->getNovalnetQuoteId());
		$session->getQuote()->setIsActive(false)->save();
		$order->setPayment($payment);
    
		$session->unsNnTestOrder();

		if( $dataObj->hasTestMode() ) {
			Mage::getModel( 'sales/quote' )
				->load($session->getNovalnetQuoteId())
				->getPayment()
				->setNnComments()
				->setNnTestorder($this->_decode($dataObj->getTestMode(), trim($this->getConfigData('password'))))
				->save();
		   $session->setpciTestOrder($this->_decode($dataObj->getTestMode(), trim($this->getConfigData('password'))));
		}
		
		if($this->getConfigData('createinvoice') == 1){
			if ($this->saveInvoice($order)) {
			  //$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
			}
		}
		
		$order->addStatusToHistory( 
				$this->getConfigData('order_status'),
				Mage::helper('novalnet')->__('Customer successfully returned from Novalnet'),
				true
			)->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
			->save();

		if ($order->getId()) { 
			  try {                          
				  $order->sendNewOrderEmail();                       
			  } catch (Exception $e) {
				  Mage::throwException(Mage::helper('novalnet')->__('Can not send new order email.'));
			 }
		}	   
		$session->unspciTestOrder();
		return true;
	}
	
	/**
     * On success based on server response
     * @param Varien_Object $dataObj
     * @param Varien_Object $order
     * @param Varien_Object $payment
     * @param Varien_Object $session
     */
	public function onFailure($dataObj,$order,$payment,$session) {
		$payment->setStatus(Mage_Novalnet_Model_NovalnetCcpci::STATUS_ERROR);
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
		$paystatus = "<b><font color='red'>Payment Failed</font> - ".utf8_decode($dataObj->getStatusText())."</b>";
		Mage::getModel( 'sales/quote' )
					->load($session->getNovalnetQuoteId())
					->getPayment()
					->setNnComments($paystatus)
					->save();
		$order->cancel();
		$order->save();
		$order->setState(Mage_Sales_Model_Order::STATE_CANCELED)->save();
		Mage::getSingleton('checkout/session')
			 ->setErrorMessage(utf8_decode($dataObj->getStatusText()));
		return false;
	}
	
	/**
	   *  Save invoice for order
	   *  @param    Mage_Sales_Model_Order $order
	   *  @return	  boolean Can save invoice or not
	   */
	protected function saveInvoice (Mage_Sales_Model_Order $order) {
		if ($order->canInvoice()) {
		  $invoice = $order->prepareInvoice();

		  $invoice->register();
		  Mage::getModel('core/resource_transaction')
			   ->addObject($invoice)
			   ->addObject($invoice->getOrder())
			   ->save();

		  $invoice->sendEmail(true, '');
		  return true;
		}
		return false;
	}
	
	/**
     * On success based on server response



     * @param String $data
     * @param String $pwd
     */
	public function _decode($data,$pwd)
	{
		$data = trim($data);
		if ($data == '') {return'Error: no data';}
		if (!function_exists('base64_decode') or !function_exists('pack') or !function_exists('crc32')){return'Error: func n/a';}

		try {
		  $data =  base64_decode(strrev($data));
		  $data = pack("H".strlen($data), $data);
		  $data = substr($data, 0, stripos($data, $pwd));
		  $pos = strpos($data, "|");
		  if ($pos === false){
			return("Error: CKSum not found!");
		  }
		  $crc = substr($data, 0, $pos);
		  $value = trim(substr($data, $pos+1));
		  if ($crc !=  sprintf('%u', crc32($value))){
			return("Error; CKSum invalid!");
		  }
		  return $value;
		}catch (Exception $e){
		  echo('Error: '.$e);
		}
	}
	public function getOrderPlaceRedirectUrl()
	{
		return Mage::getUrl('novalnet/pci/redirect');
	}
	
	public function getCgiUrl() {
		return self::CGI_URL;
	}
	
	public function _checkPaymentType() {
		if($this->_code == "novalnetCc") {
			return  true;
		}
		return false;
	}

}
