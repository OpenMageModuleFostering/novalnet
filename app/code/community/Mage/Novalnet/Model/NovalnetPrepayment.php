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

class Mage_Novalnet_Model_NovalnetPrepayment extends Mage_Payment_Model_Method_Abstract
{
    const CGI_URL                = 'https://payport.novalnet.de/paygate.jsp';
    const RESPONSE_DELIM_CHAR    = '&';
    const RESPONSE_CODE_APPROVED = 100;
    const PAYMENT_METHOD         = 'PREPAYMENT';
	
    private $_nnPaymentId = '27';
	
    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */
    protected $_code = 'novalnetPrepayment';
    protected $_formBlockType = 'novalnet/prepayment_form';
    protected $_infoBlockType = 'novalnet/prepayment_info';

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
    protected $_canCapture              = true; #important; default: false
	
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
     * Here you will need to implement authorize, capture and void public methods
     *
     * @see examples of transaction specific public methods such as
     * authorize, capture and void in Mage_Paygate_Model_Authorizenet
     */

	/**
     * Prepare request to gateway
     *
     * @link http://www.authorize.net/support/AIM_guide.pdf
     * @param Mage_Sales_Model_Document $order
     * @return unknown
     */
	/*
    protected function _saveObject (Varien_Object $payment)
    {
        $order = $payment->getOrder();
        if (!empty($order)) {
            $billing = $order->getBillingAddress();
        }
    }
	*/
	
    protected function _postRequest(Varien_Object $request)
    {
		$result = Mage::getModel('novalnet/novalnet_result');
		$request->toLatin1();
		$httpClientConfig = array('maxredirects'=>0);
		if(((int)$this->getConfigData( 'gateway_timeout' )) > 0) {
			$httpClientConfig['timeout'] = (int)$this->getConfigData( 'gateway_timeout' );
		}
        $client = new Varien_Http_Client(self::CGI_URL, $httpClientConfig);
        $client->setParameterPost($request->getData())
			->setMethod(Zend_Http_Client::POST)
		;
		$response = $client->request();
		if (!$response->isSuccessful()) {
			Mage::throwException(
                Mage::helper('novalnet')->__('Gateway request error: %s', $response->getMessage())
            );
		}
		$result->addData(
			$this->_deformatNvp($response->getBody()));
		$result->setNnNote($this->getNote($result));
        $result->toUtf8();
        return $result;
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
            }elseif(!$this->getConfigData('merchant_id') || !$this->getConfigData('auth_code') || !$this->getConfigData('product_id') || !$this->getConfigData('tariff_id')) {
				return false;
			}
		return parent::isAvailable();
	}
	
		public function validate() {
		
		$session = Mage::getSingleton('checkout/session');
		if(!$session->getInvoiceReqData() && $this->_isPlaceOrder())
		{
			$request = $this->_buildNoInvoiceRequest();
			$response  = $this->_postRequest($request);
			Mage::getSingleton('checkout/session')->setInvoiceReqData($response);
			Mage::getSingleton('checkout/session')->setInvoiceReqDataNote($this->getNote($response));
			$resultdata=Mage::getSingleton('checkout/session')->getInvoiceReqData();
			if($response->getStatus()!='100'){
			
				$session=Mage::getSingleton('checkout/session');
				$session->unsInvoiceReqData()
				        ->unsInvoiceReqDataNote();
						
				$text = Mage::helper('novalnet')->__($response->getstatus_desc());
				Mage::throwException($text);
			}
			return $this;
		  }
		}
		protected function _buildNoInvoiceRequest() {
		
			$request = Mage::getModel('novalnet/novalnet_request');
			$amount  = round($this->_getAmount(), 2) * 100;
			$billing = $this->_getBillingAddress();
			
			$paymentDuration = (int)trim($this->getConfigData('payment_duration'));
			$dueDate         = $paymentDuration ? date('d.m.Y', strtotime('+' . $paymentDuration . ' days')) : NULL;
			
			$this->_assignNnAuthData($request, $amount);
			$request->setAmount($amount)
				->setCurrency($this->_getCurrencyCode())
				->setfirstName($billing->getFirstname())
				->setLastName($billing->getLastname())
				->setSearchInStreet(1)
				->setStreet(implode(',', $billing->getStreet()))
				->setCity($billing->getCity())
				->setZip($billing->getPostcode())
				->setCountry($billing->getCountry())
				->setTel($billing->getTelephone())
				->setFax($billing->getFax())
				->setRemoteIp(Mage::helper('novalnet')->getRealIpAddr())
				->setGender('u')
				->setEmail($this->_getCustomerEmail())
				->setOrderNo($this->_getOrderId())
		        ->setInput1('order_id')
				->setInputval1($this->_getOrderId())
				->setInvoiceType(self::PAYMENT_METHOD)
				->setDueDate($dueDate)
				->setInvoiceRef('BNR-'.$this->getConfigData('product_id').'-'. $this->_getOrderId())
			;
			
			return $request;
		}
	
	/**
     * Get checkout
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        if (empty($this->_quote)) {
            $this->_quote = $this->getCheckout()->getQuote();
        }
        return $this->_quote;
    }
    /**
     * Get checkout
     *
     * @return Mage_Sales_Model_Order
     */
    public function getCheckout()
    {
        if (empty($this->_checkout)) {
            $this->_checkout = Mage::getSingleton('checkout/session');
        }
        return $this->_checkout;
    }
   
    public function getTitle() {
        //return $this->getConfigData('title');
		return Mage::helper('novalnet')->__($this->getConfigData('title'));
    }
	
	public function isPublicIP($value)
	{
		if(!$value || count(explode('.',$value))!=4)
		{
			return false;
		}
		return !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value);
	}
	
    public function getNote($result)
	{
		$helper = Mage::helper('novalnet');
		$note   = NULL;
		$note  .= "<b>".$helper->__('Please transfer the invoice amount with the following information to our payment provider Novalnet AG')."</b><br />";

		$note  .= $helper->__('Account Holder2') . ":<b>NOVALNET AG</b><br />";
		$note  .= $helper->__('Account Number') . ":<b>" . $result->getInvoiceAccount() . "</b><br />";
		$note  .= $helper->__('Bank Sorting Code') . ":<b>" . $result->getInvoiceBankcode() . "</b><br />";
		$note  .= $helper->__('Bank') . ":<b>" . $result->getInvoiceBankname() . ", Muenchen</b><br />";
		$note  .= $helper->__('Amount') . ":<b>" . str_replace('.', ',', $result->getAmount()) . " EUR</b><br />";
		$note  .= $helper->__('Reference') . ":<b>TID " . $result->getTid() . "</b><br />";
		$note  .= $helper->__('Only for foreign transfers') . ":<br />";
		$note  .= "IBAN: <b>" . $result->getInvoiceIban() . "</b><br />";
		$note  .= "SWIFT/BIC: <b>" . $result->getInvoiceBic() . "</b><br />";
		return $note;
	}

	private function _deformatNvp($query) {
		$deformated = array();
		if(strlen($query)){
			$tmp = explode(self::RESPONSE_DELIM_CHAR, $query);
			foreach($tmp as $k){
				$k = explode('=', $k);
				$deformated[urldecode($k[0])] = isset($k[1]) ? urldecode($k[1]) : NULL;
			}
		}
		return $deformated;
	}
	
	private function _assignNnAuthData( Varien_Object $request, $amount ) {
		$manualCheckAmt = (int)$this->getConfigData('manual_checking_amount');
		$request->setVendor($this->getConfigData('merchant_id'))
			->setAuthCode($this->getConfigData('auth_code'))
			->setProduct(
				(strlen($this->getConfigData('second_product_id')) && $manualCheckAmt && $manualCheckAmt>$amount)
				?$this->getConfigData('second_product_id')
				:$this->getConfigData('product_id')
			)
			->setTariff(
				(strlen($this->getConfigData('second_tariff_id')) && $manualCheckAmt && $manualCheckAmt>$amount)
				?$this->getConfigData('second_tariff_id')
				:$this->getConfigData('tariff_id')
			)
			->setTestMode((!$this->getConfigData('live_mode'))? 1: 0)
			->setKey($this->_nnPaymentId)
		;
	}
	
	private function _getCurrencyCode(){
        $info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
			return $info->getOrder()->getBaseCurrencyCode();
        } else {
			return $info->getQuote()->getBaseCurrencyCode();
        }
    }
	
	private function _getCustomerEmail() {
		$info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
			return $info->getOrder()->getCustomerEmail();
        } else {
			return $info->getQuote()->getCustomerEmail();
        }
	}
	
	private function _getOrderId(){
        $info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
            return $info->getOrder()->getIncrementId();
        } else {
            if (!$info->getQuote()->getReservedOrderId()) {
                $info->getQuote()->reserveOrderId();
            }
            return $info->getQuote()->getReservedOrderId();
        }
    }
	
	private function _getAmount() {
        $info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
            return (double)$info->getOrder()->getQuoteBaseGrandTotal();
        } else {
            return (double)$info->getQuote()->getBaseGrandTotal();
        }
    }
	
	private function _isPlaceOrder() {
        $info = $this->getInfoInstance();
        if ($info instanceof Mage_Sales_Model_Quote_Payment) {
            return false;
        } elseif ($info instanceof Mage_Sales_Model_Order_Payment) {
            return true;
        }
    }
	
	private function _getBillingAddress() {
		$info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
			return $info->getOrder()->getBillingAddress();
        } else {
			return $info->getQuote()->getBillingAddress();
        }
	}
	
	public function getOrderPlaceRedirectUrl()
	{
		return Mage::getUrl('novalnet/invoice/invoicefunction');
	}

}
