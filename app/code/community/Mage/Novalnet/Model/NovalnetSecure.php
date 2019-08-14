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


class Mage_Novalnet_Model_NovalnetSecure extends Mage_Payment_Model_Method_Cc
{
	const CGI_URL                = 'https://payport.novalnet.de/global_pci_payport';
	const PAYMENT_METHOD         = '3D-Secure Credit Card';
	const RESPONSE_CODE_APPROVED = 100;
	const RESPONSE_CODE_ABORT    = 20;
	
	private $_nnPaymentId = 6;
	
	/**
	* unique internal payment method identifier
	* 
	* @var string [a-z0-9_]
	*/
	protected $_code = 'novalnet_secure';
	protected $_formBlockType = 'novalnet/secure_form';
	protected $_infoBlockType = 'novalnet/secure_info';
	
	/**
	* Is this payment method a gateway (online auth/charge) ?
	*/
	protected $_isGateway               = true;
	
	/**
	* Can authorize online?
	*/
	protected $_canAuthorize            = true;
	
	/**
	* Can capture funds online?
	*/
	protected $_canCapture              = true;
	
	/**
	* Can capture partial amounts online?
	*/
	protected $_canCapturePartial       = true;
	
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
	protected $_canSaveCc = false;
	
	protected $_isInitializeNeeded      = true;
	
	/**
     * Instantiate state and set it to state object
     * @param string $paymentAction
     * @param Varien_Object
     */
    public function initialize($paymentAction, $stateObject)
    {
		$paymentInfo = $this->getInfoInstance();
		$session = Mage::getSingleton('checkout/session');
		$session->setCcNo(Mage::helper('core')->encrypt($paymentInfo->getCcNumber()));
		$session->setCcCvc2(Mage::helper('core')->encrypt($paymentInfo->getCcCid()));
    }
	
	public function getBookingReference()
	{
		return $this->getConfigData('booking_reference');
	}
	
	public function getTitle() {
		return Mage::helper('novalnet')->__($this->getConfigData('title'));
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
		if (count($orders) < $minOrderCount) {
			return false;
		}
		return parent::isAvailable();
	}
	
	public function getFormData()
    {
		$dataObj        = new Varien_Object();
		$order          = $this->getInfoInstance()->getOrder();
		$amount         = (round($order->getBaseGrandTotal(), 2) * 100);
		$manualCheckAmt = (int)$this->getConfigData('manual_checking_amount');
		$billing	= $order->getBillingAddress();
		$session        = Mage::getSingleton('checkout/session');
		$payment        = $order->getPayment();
		
		$objQuote = $objQuote = Mage::getModel( 'sales/quote' );
		$objQuote->setStoreId($order->getStoreId())->load($order->getQuoteId());
		$objQuotePayment = $objQuote->getPayment();
		$objQuotePayment->setNnTestOrder((!$this->getConfigData('live_mode'))? 1: 0)->save();
		
		if(!trim($this->getConfigData('merchant_id')) || !trim($this->getConfigData('auth_code')) || !trim($this->getConfigData('product_id')) || !trim($this->getConfigData('tariff_id')))
		{
			Mage::getSingleton('core/session')
                ->addError('Die Hashfunktionen sind nicht verf&uuml;gbar!');
            $url = Mage::getModel('core/url')->getUrl("checkout/onepage/failure");
            Mage::app()->getResponse()->setRedirect($url);
            Mage::app()->getResponse()->sendResponse();
			exit;
		}
		
		$dataObj->setVendor(trim($this->getConfigData('merchant_id')))
			->setVendorAuthcode(trim($this->getConfigData('auth_code')))
			->setProduct(
				(strlen(trim($this->getConfigData('second_product_id'))) && $manualCheckAmt && $manualCheckAmt<=$amount)
				?trim($this->getConfigData('second_product_id'))
				:trim($this->getConfigData('product_id'))
			)
			->setTariff(
				(strlen(trim($this->getConfigData('second_tariff_id'))) && $manualCheckAmt && $manualCheckAmt<=$amount)
				?trim($this->getConfigData('second_tariff_id'))
				:trim($this->getConfigData('tariff_id'))
			)
			->setAmount($amount)
			->setKey($this->_nnPaymentId)
			->setTestMode((!$this->getConfigData('live_mode'))? 1: 0)
			->setCurrency($order->getOrderCurrencyCode())
			->setCustomerNo(Mage::getSingleton('customer/session')->getCustomerId()) 
			->setUseUtf8(1) 
			->setFirstName($billing->getFirstname())
			->setLastName($billing->getLastname())
			->setEmail($order->getCustomerEmail())
			->setStreet($billing->getStreet(1))
			->setSearchInStreet(1)
			->setCity($billing->getCity())
			->setZip($billing->getPostcode())
			->setCountryCode($billing->getCountry())
			->setLang(substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2))
			->setRemoteIp(Mage::helper('novalnet')->getRealIpAddr())
			->setTel($billing->getTelephone())
			->setFax($billing->getFax())
			->setCcHolder($payment->getCcOwner())
			->setCcNo(Mage::helper('core')->decrypt($session->getCcNo()))
			->setCcExpMonth($payment->getCcExpMonth())
			->setCcExpYear($payment->getCcExpYear())
			->setCcCvc2(Mage::helper('core')->decrypt($session->getCcCvc2()))
			->setSession($session->getSessionId())
			->setReturnUrl(Mage::getUrl('novalnet/secure/success', array('_secure' => true)))
			->setReturnMethod('POST')
			->setErrorReturnUrl(Mage::getUrl('novalnet/secure/success', array('_secure' => true)))
			->setErrorReturnMethod('POST')
			->setOrderId($order->getRealOrderId())
			->setOrderNo($order->getRealOrderId())
			->setInput1('order_id')
			->setInputval1($order->getRealOrderId());
			
		   $session->unsCcNo()->unsCcCvc2()->unsNnSecureTestOrder();
         $session->setNnSecureTestOrder($dataObj->getTestMode());
        return $dataObj;
    }
	/**
     * To Validate Admin Back End Parameters
     */
	public function validate() 
	{
		parent::validate();
		if(!trim($this->getConfigData('merchant_id')) || !trim($this->getConfigData('auth_code')) || !trim($this->getConfigData('product_id')) || !trim($this->getConfigData('tariff_id'))) {
			Mage::throwException(Mage::helper('novalnet')->__('Basic Parameter Missing').'!');
		}if((int)$this->getConfigData('manual_checking_amount')&& (!trim($this->getConfigData('second_product_id')) || !trim($this->getConfigData('second_tariff_id')))) {
			Mage::throwException(Mage::helper('novalnet')->__('Required Second Product Tariff').'!');
		}   
		$info = $this->getInfoInstance();  
		if(preg_match('/[#%\^<>@$=*!]/', $info->getCcOwner())){
			Mage::throwException(Mage::helper('novalnet')->__('This is not a valid Account Holder Name'));
		}  
		return $this;
	}
	/**
     * To Save the masking account information
     */
	public function prepareSave()
	{
		$info = $this->getInfoInstance();   
		$info->setCcNumberEnc(str_pad(substr($info->getCcNumber(),0,6),strlen($info->getCcNumber())-4,'*',STR_PAD_RIGHT).substr($info->getCcNumber(),-4));      
		$info->setCcNumber(null)
		->setCcCid(null);
		return $this;
	}
	public function getOrderPlaceRedirectUrl()
	{
		return Mage::getUrl('novalnet/secure/redirect');
	}
	
	public function getCgiUrl() {
		return self::CGI_URL;
	}
	
	public function isPublicIP($value)
	{
		if(!$value || count(explode('.',$value))!=4)
		{
			return false;
		}
		return !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value);
	}
}
