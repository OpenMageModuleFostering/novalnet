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
 * @copyright  Copyright (c) 2008 Novalnet AG
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
	
	public function isAvailable($quote = null) {
	
		$minOrderCount = trim($this->getConfigData('orderscount'));
		$customerId = Mage::getSingleton('customer/session')->getCustomerId();
       
	        // Load orders and check
            $orders = Mage::getResourceModel('sales/order_collection')
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('customer_id', $customerId)
                ->load();
            if (count($orders) < $minOrderCount) {
                return false;
            }elseif(!$this->getConfigData('merchant_id') || !$this->getConfigData('auth_code') || !$this->getConfigData('product_id') || !$this->getConfigData('tariff_id') || !$this->getConfigData('password')) {
				return false;
			}
		return parent::isAvailable();
	}
	
	public function getFormData()
    {
		$dataObj	= new Varien_Object();
		$order      = $this->getInfoInstance()->getOrder();
        $billing	= $order->getBillingAddress();
		
		$dataObj->setPaymentId($this->_nnPaymentId)
				->setNnMethod('nn_cc_pci');
				
		$this->importNovalnetFormData($dataObj)
				->importBillingData($dataObj)
				->importUrlData($dataObj)
				->importEncodeData($dataObj)
				->importHashData($dataObj);
				
		return $dataObj;
    }
	
    public function importNovalnetFormData($dataObj) {
		
		$order          = $this->getInfoInstance()->getOrder();
	
		$pid    		= $this->getConfigData('product_id');
		$tid    		= $this->getConfigData('tariff_id');	
		$product2 		= $this->getConfigData('second_product_id');
		$tariff2  		= $this->getConfigData('second_tariff_id');
		$amount 		= (round($order->getBaseGrandTotal(), 2) * 100);
		$manualCheckAmt = (int)$this->getConfigData('manual_checking_amount');
		
		if($manualCheckAmt && $manualCheckAmt>=$amount && $product2 && $tariff2) {
			$pid    	= $this->getConfigData('second_product_id');
			$tid    	= $this->getConfigData('second_tariff_id');
		}
		
		$uniqid    		= uniqid(); 
		
		$test_mode		= (!$this->getConfigData('live_mode'))? 1: 0;
		
		$dataObj->setVendorId($this->getConfigData('merchant_id'))
				->setVendorAuthcode($this->getConfigData('auth_code'))
				->setProductId($pid)
				->setTariffId($tid)
				->setUniqid($uniqid)
				->setAmount($amount)
				->setTestMode($test_mode)
				->setPaymentId($this->_nnPaymentId);
				
		return $this;
	}
	
	public function importBillingData($dataObj) {
		$order  	= $this->getInfoInstance()->getOrder();
        $billing	= $order->getBillingAddress();
		
		$objQuote = $objQuote 	= Mage::getModel( 'sales/quote' );
		$objQuote->setStoreId($order->getStoreId())->load($order->getQuoteId());
		$objQuotePayment = $objQuote->getPayment();
		$objQuotePayment->setNnTestOrder((!$this->getConfigData('live_mode'))? 1: 0)->save();
		
		$dataObj->setCurrency($order->getOrderCurrencyCode())
				->setFirstname($billing->getFirstname())
				->setLastname($billing->getLastname())
				->setEmail($order->getCustomerEmail())
				->setStreet($billing->getStreet(1))
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
	
	public function importUrlData($dataObj) {
		$order  	= $this->getInfoInstance()->getOrder();
		$dataObj->setSession(Mage::getSingleton('checkout/session')->getSessionId())
				->setReturnUrl($this->getReturnURL())
				->setReturnMethod('POST')
				->setErrorReturnUrl($this->getReturnURL())
				->setErrorReturnMethod('POST')
				->setInput1('order_id')
				->setOrderNo($order->getRealOrderId())
				->setImplementation('PHP_PCI')
				->setInputval1($order->getRealOrderId());
		return $this;
	}
	
	public function importHashData($dataObj) {
		$hash = Mage::helper('novalnet')->generateHash($dataObj, $this->getConfigData('password'));
		
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
	
	public function importEncodeData($dataObj) {
		$encoding = Mage::helper('novalnet')->encode($dataObj, $this->getConfigData('password'));
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
	
	public function statusCheck($response,$session) {
		$status  = false;
		$dataObj = new Varien_Object($response);
		
		$order = Mage::getModel('sales/order')
				->loadByIncrementId($dataObj->getOrderId());
		$payment = $order->getPayment();
		
		if(Mage::helper('novalnet')->checkHash($response, $this->getConfigData('password'))) {
			
			if ($response['status'] == Mage_Novalnet_Model_NovalnetCcpci::RESPONSE_CODE_APPROVED) {
				$status = $this->onSucess($dataObj,$order,$payment,$session);
			} else {
				//echo "ashok aasdfsa "; exit;
				$status = $this->onFailure($dataObj,$order,$payment,$session);
			}
		}
		$session->unsNovalnetRealOrderId();
		$session->unsNovalnetQuoteId();
		return $status;
	}
	
	public function onSucess($dataObj,$order,$payment,$session) {
			$payment->setStatus(Mage_Novalnet_Model_NovalnetCcpci::STATUS_SUCCESS)
					->setStatusDescription(Mage::helper('novalnet')->__('Payment was successful.'))
					->setTransactionId($dataObj->getTid())
					->setSuTransactionId($dataObj->getTid())
					->setLastTransId($dataObj->getTid());

			$session->setQuoteId($session->getNovalnetQuoteId());
			$session->getQuote()->setIsActive(false)->save();
			$order->setPayment($payment);
			
			if( $dataObj->hasTestMode() ) {
				Mage::getModel( 'sales/quote' )
					->load($session->getNovalnetQuoteId())
					->getPayment()
					->setNnComments()
					->setNnTestorder($this->decode($dataObj->getTestMode(), $this->getConfigData('password')))
					->save();
			}
			
			if($this->getConfigData('createinvoice') == 1){
				if ($this->saveInvoice($order)) {
				  $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
				}
			}
			
			$order->addStatusToHistory( 
					$this->getConfigData('order_status'),
					Mage::helper('novalnet')->__('Customer successfully returned from Novalnet'),
					true
				)->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
				->save();
			
			
			if ($order->getId()) {
				$order->sendNewOrderEmail();
			}
			
		return true;
	}
		/**
	   *  Save invoice for order
	   *
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
		$paystatus = "<b><font color='red'>Payment Failed</font> - ".$dataObj->getStatusText()."</b>";
		Mage::getModel( 'sales/quote' )
					->load($session->getNovalnetQuoteId())
					->getPayment()
					->setNnComments($paystatus)
					->save();
		$order->cancel();
		$order->save();
		$order->setState(Mage_Sales_Model_Order::STATE_CANCELED)->save();
		Mage::getSingleton('checkout/session')
			 ->setErrorMessage($dataObj->getStatusText());
		return false;
	}
	public function decode($data,$pwd)
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

}