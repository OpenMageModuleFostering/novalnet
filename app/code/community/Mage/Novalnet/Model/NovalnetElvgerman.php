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
 * @version    3.0.1
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Novalnet_Model_NovalnetElvgerman extends Mage_Payment_Model_Method_Abstract
{
	const CGI_URL                = 'https://payport.novalnet.de/paygate.jsp';
	const XML_URL                = 'https://payport.novalnet.de/nn_infoport.xml';
	const PAYMENT_METHOD         = 'Direct Debit';
	const RESPONSE_DELIM_CHAR    = '&';
	const RESPONSE_CODE_APPROVED = 100;
	const BANK_SORTCODE_LENGTH   = 3;
	const CALLBACK_PIN_LENGTH    = 4;
	const ACCNO_MIN_LENGTH 	  = 5;	
	
	// are used with _buildRequest and _postRequest
	const POST_NORMAL    = 'normal';
	const POST_CALLBACK  = 'callback';
	const POST_NEWPIN    = 'newpin';
	const POST_PINSTATUS = 'pinstatus';
	const POST_EMAILSTATUS = 'emailstatus';

	const ISOCODE_FOR_GERMAN = 'DE';
	
	private $_nnPaymentId = '2';
	
	/**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */
    protected $_code = 'novalnetElvgerman';
    protected $_formBlockType = 'novalnet/elvgerman_form';
    protected $_infoBlockType = 'novalnet/elvgerman_info';
	
    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway               = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize            = false;

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
    protected $_canUseInternal          = true;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout          = true;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping  = true;

    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc               = false;

	public function __construct() {
	}

	/**
     * Send capture request to gateway
     * @param Varien_Object $payment
     * @param decimal $amount
     * @throws Mage_Core_Exception
     */
    public function capture(Varien_Object $payment, $amount) {
		$methodSession = $this->_getMethodSession();
		if( $this->isCallbackTypeCall()) {
			$buildrequest = $this->_buildPostBackRequest();
			$postrequest  = $this->_postRequest($buildrequest, self::POST_NORMAL);
			$result  = $methodSession->getNnResponseData();
		}else {
			$request = $this->_buildRequest(self::POST_NORMAL);
			$result  = $this->_postRequest($request, self::POST_NORMAL);
		}
		
		// Analyze the response from Novalnet
		if ($result->getStatus() == self::RESPONSE_CODE_APPROVED) {
			$this->_unsetMethodSession();
			$payment->setStatus(self::STATUS_APPROVED)
				     ->setLastTransId($result->getTid())
                 ->setNnAccountNumber(substr($this->_getNnAccountNumber(),0,-4) . '****')
					  ->setNnBankSortingCode(substr($this->_getNnBankSortingCode(),0,-4) . '****')
			;
			$quotePayment = $this->_getQuotePaymentById($payment->getNnId());
			if($quotePayment) {
				$quotePayment->setNnTestorder($result->getTestMode())
					->setNnAccountNumber(substr($this->_getNnAccountNumber(),0,-4) . '****')
					->setNnBankSortingCode(substr($this->_getNnBankSortingCode(),0,-4) . '****')
					->save()
				;
			}
			Mage::getSingleton('core/session')->addSuccess(Mage::helper('novalnet')->__('successful'));
		} else {
			$error = ($result->getStatusDesc()||$result->getStatusMessage())
				? Mage::helper('novalnet')->htmlEscape(utf8_decode($result->getStatusMessage()).utf8_decode($result->getStatusDesc()))
				: Mage::helper('novalnet')->__('Error in capturing the payment')
			;
			Mage::throwException($error);
		}
		return $this;
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
			}else if(time()<$this->_getMethodSession()->getNnDeDisableTime()){
		   return false;
		}   
			return  parent::isAvailable();
	}
	
	/**
     * To check Novalnet Authentication data
     */
	private function _checkNnAuthData() {
		return strlen($this->getConfigData('merchant_id'))
			&& strlen($this->getConfigData('auth_code'))
			&& strlen($this->getConfigData('product_id'))
			&& strlen($this->getConfigData('tariff_id'))
		;
	}
	
	/**
     * To build the request as per the type request
	 * @param String $type
     */
    protected function _buildRequest($type=self::POST_NORMAL) {
		if( $type == self::POST_NORMAL || $type == self::POST_CALLBACK ) {
			$request = Mage::getModel('novalnet/novalnet_request');
			$amount  = round($this->_getAmount(), 2) * 100;
			$billing = $this->_getBillingAddress();
			
			$this->_assignNnAuthData($request, $amount);
			$request->setAmount($amount)
				->setCurrency($this->_getCurrencyCode())
				->setCustomerNo(Mage::getSingleton('customer/session')->getCustomerId()) 
				->setUseUtf8(1) 
				->setfirstName($billing->getFirstname())
				->setLastName($billing->getLastname())
				->setSearchInStreet(1)
				->setStreet(implode(',', $billing->getStreet()))
				->setCity($billing->getCity())
				->setZip($billing->getPostcode())
				->setCountry($billing->getCountry())
				->setLang(strtoupper(substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2)))
				->setTel($billing->getTelephone())
				->setFax($billing->getFax())
				->setRemoteIp(Mage::helper('novalnet')->getRealIpAddr())
				->setGender('u')
				->setEmail($this->_getCustomerEmail())
				->setBankAccountHolder($this->_getNnAccountHolder())
				->setBankAccount($this->_getNnAccountNumber())
				->setBankCode($this->_getNnBankSortingCode())
				->setAcdc($this->getConfigData('acdc_check'))
				->setOrderNo($this->_getOrderId())
       		->setInput1('order_id')
			  	->setInputval1($this->_getOrderId());
			
			$infoInstance = $this->getInfoInstance();
			$infoInstance->setNnAccountNumber(substr($this->_getNnAccountNumber(),0,-4) . '****')
						 ->setNnBankSortingCode(substr($this->_getNnBankSortingCode(),0,-4) . '****');
						 
			if( $type == self::POST_CALLBACK ) {
				if($this->getConfigData('callback') == 1) {
					$request->setTel($this->getInfoInstance()->getNnCallbackTel());
					$request->setPinByCallback(1);
				}else if($this->getConfigData('callback') == 2) {
					$request->setMobile($this->getInfoInstance()->getNnCallbackTel());
					$request->setPinBySms(1);
				}else if($this->getConfigData('callback') == 3){
					$request->setReplyEmailCheck(1);
				}
			}
			$request->toLatin1();
			return $request;
		}else if($type == self::POST_NEWPIN) {
				$request  = '<?xml version="1.0" encoding="UTF-8"?>';
				$request .= '<nnxml><info_request>';
				$request .= '<vendor_id>' . trim($this->getConfigData( 'merchant_id' )) . '</vendor_id>';
				$request .= '<vendor_authcode>' . trim($this->getConfigData( 'auth_code' )) . '</vendor_authcode>';
				$request .= '<request_type>TRANSMIT_PIN_AGAIN</request_type>';
				$request .= '<tid>' . $this->_getMethodSession()->getNnCallbackTid() . '</tid>';
				$request .= '</info_request></nnxml>';
			return $request;
		}else if($type == self::POST_PINSTATUS) {
			$request  = '<?xml version="1.0" encoding="UTF-8"?>';
			$request .= '<nnxml><info_request>';
			$request .= '<vendor_id>' . trim($this->getConfigData( 'merchant_id' )) . '</vendor_id>';
			$request .= '<vendor_authcode>' . trim($this->getConfigData( 'auth_code' )) . '</vendor_authcode>';
			$request .= '<request_type>PIN_STATUS</request_type>';
			$request .= '<tid>' . $this->_getMethodSession()->getNnCallbackTid() . '</tid>';
			$request .= '<pin>' . $this->_getMethodSession()->getNnCallbackPin() . '</pin>';
			$request .= '</info_request></nnxml>';
			return $request;
		}else if($type == self::POST_EMAILSTATUS) {
			$request  = '<?xml version="1.0" encoding="UTF-8"?>';
			$request .= '<nnxml><info_request>';
			$request .= '<vendor_id>' . trim($this->getConfigData( 'merchant_id' )) . '</vendor_id>';
			$request .= '<vendor_authcode>' . trim($this->getConfigData( 'auth_code' )) . '</vendor_authcode>';
			$request .= '<request_type>REPLY_EMAIL_STATUS</request_type>';
			$request .= '<tid>' . $this->_getMethodSession()->getNnCallbackTid() . '</tid>';
			$request .= '</info_request></nnxml>';
			return $request;
		}
    }
	
	/**
     * To assign Novalnet Authentication Data
	 * @param Varien_Object $request
	 * @param Decimal $amount
     */
	private function _assignNnAuthData( Varien_Object $request, $amount ) {
		$manualCheckAmt = (int)$this->getConfigData('manual_checking_amount');
		$request->setVendor(trim($this->getConfigData('merchant_id')))
			->setAuthCode(trim($this->getConfigData('auth_code')))
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
			->setTestMode((!$this->getConfigData('live_mode'))? 1: 0)
			->setKey($this->_nnPaymentId)
		;
	}
	
	/**
     * To post the request to Novalnet server
	 * @param Varien_Object $request
	 * @param String $type
     */
    protected function _postRequest($request, $type=self::POST_NORMAL)
    {
    	$result = Mage::getModel('novalnet/novalnet_result');
    	$httpClientConfig = array('maxredirects'=>0);
		if(((int)$this->getConfigData( 'gateway_timeout' )) > 0) {
			$httpClientConfig['timeout'] = (int)$this->getConfigData( 'gateway_timeout' );
		}
		$client = new Varien_Http_Client( self::CGI_URL, $httpClientConfig );
		if( $type == self::POST_NEWPIN || $type == self::POST_PINSTATUS || $type == self::POST_EMAILSTATUS ) {
			$client->setUri( self::XML_URL );
			$client->setRawData( $request )->setMethod( Varien_Http_Client::POST );
		}else {
			$client->setParameterPost( $request->getData() )->setMethod( Varien_Http_Client::POST );
		}
		$response = $client->request();        	        	
		if (!$response->isSuccessful()) {
			Mage::throwException( Mage::helper('novalnet')->__('Gateway request error: %s', $response->getMessage()) );
		}
		if( $type == self::POST_NEWPIN || $type == self::POST_PINSTATUS || $type == self::POST_EMAILSTATUS ) {
			$result = new Varien_Simplexml_Element( $response->getRawBody() );
			$result = new Varien_Object( $this->_xmlToArray( $result ) );
		}else {
			$result->addData( $this->_deformatNvp( $response->getBody() ) );
			$result->toUtf8();
		}
        return $result;
    }
	
	/**
     * To build post back request
     */
    protected function _buildPostBackRequest()
	{
			$methodSession = $this->_getMethodSession();
			$request = Mage::getModel('novalnet/novalnet_request');
			$amount  = round($this->_getAmount(), 2) * 100;
			$this->_assignNnAuthData($request, $amount);
			$request->setTid($methodSession->getNnResponseData()->getTid())
					->setOrderNo($this->_getOrderId())
					->setStatus(100);
			return $request;
	}	
	
	private function _xmlToArray( SimpleXMLElement $xml ) {
		$array = array();
		if( version_compare( Mage::getVersion(), '1.4.0.0', '>=' ) ) {
			$array = $xml->asArray();
		}else {
			foreach( $xml->children() as $name => $value ) {
				if( $value instanceof SimpleXMLElement && count( $value ) ) {
					$array[ trim( $name ) ] = $this->_xmlToArray( $value );
				}else {
					$array[$name] = trim( (string)$value );
				}
			}
		}
		return $array;
	}
	
	public function isCallbackTypeCall(){
		$total = $this->_getAmount() * 100;
		$callBackMinimum = (int)$this->getConfigData('callback_minimum_amount');
		$countryCode = strtoupper( $this->_getBillingCountryCode() );
		
		return $this->_getCheckoutSession()->hasNnCallbackTid() 
			|| ($this->getConfigData('callback')
				&& ($callBackMinimum?$total>=$callBackMinimum:true)
				&& ($countryCode?$countryCode==self::ISOCODE_FOR_GERMAN:true))
		;
	}
	
	public function getCallbackConfigData(){
		return $this->getConfigData('callback')	;
	}

    public function assignData($data)
    {   
		if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
		$infoInstance = $this->getInfoInstance();
        $infoInstance->setNnElvCountry($data->getElvCountry())
			->setNnAccountHolder($data->getAccountHolder())
		   ->setNnAccountNumber(trim($data->getAccountNumber()))
			->setNnBankSortingCode(trim($data->getBankSortingCode()))
			->setNnAcdc($data->getAcdc())
			->setNnCallbackPin($data->getCallbackPin())
			->setNnNewCallbackPin($data->getNewCallbackPin())
			->setNnCallbackTel($data->getCallbackTel())
			->setNnCallbackEmailDE($data->getCallbackEmail());
		if( $this->getConfigData('acdc_check') ) {
			$infoInstance->setAcdcValidationFlag( true );
		}
		if( $this->isCallbackTypeCall() && $this->getCallbackConfigData()!=3) {
			$infoInstance->setCallbackPinValidationFlag( true );
		}
        return $this;
    }
	
	public function validate() {
		parent::validate();
        //Validate Backend Parameters
      if(!trim($this->getConfigData('merchant_id')) || !trim($this->getConfigData('auth_code')) || !trim($this->getConfigData('product_id')) || !trim($this->getConfigData('tariff_id'))) {
		   Mage::throwException(Mage::helper('novalnet')->__('Basic Parameter Missing').'!');
	   }if((int)$this->getConfigData('manual_checking_amount')&& (!trim($this->getConfigData('second_product_id')) || !trim($this->getConfigData('second_tariff_id')))) {
         Mage::throwException(Mage::helper('novalnet')->__('Required Second Product Tariff').'!');
      }    
      //Customer_id verification
      try{
         $login_check = Mage::getSingleton('customer/session')->isLoggedIn();
         if($login_check){
            $customer_no = Mage::getSingleton('customer/session')->getCustomer()->getId();
            if (empty($customer_no)){
               $customer_no = $_SESSION['core']['visitor_data']['customer_id'];
            }
            if($customer_no==""){
               Mage::log(Mage::getSingleton('customer/session')->getCustomer(),NULL,"Customerid_Missing_".Mage::getModel('core/date')->date('d-m-Y h:i:s').".log");
               Mage::log("Below are Order Details : ",NULL,"Customerid_Missing_".Mage::getModel('core/date')->date('d-m-Y h:i:s').".log");
               $order = Mage::getModel('checkout/cart')->getQuote()->getData();
               Mage::log($order,NULL,"Customerid_Missing_".Mage::getModel('core/date')->date('d-m-Y h:i:s').".log");
               Mage::throwException(Mage::helper('novalnet')->__('Basic Parameter Missing. Please contact Shop Admin').'!');    
            }
         }
      }catch(Exception $e){
         Mage::log($e->getMessage(),NULL,"Customerid_Missing_".Mage::getModel('core/date')->date('d-m-Y h:i:s').".log");
      }
		$this->_validateCallbackSession();
		$infoInstance      = $this->getInfoInstance();
		$nnAccountHolder   = $infoInstance->getNnAccountHolder(); 
		$nnAccountNumber   = preg_replace('/[\-\s]+/', '', $infoInstance->getNnAccountNumber());
		$nnBankSortingCode = preg_replace('/[\-\s]+/', '', $infoInstance->getNnBankSortingCode());
		
      if(preg_match('/[#%\^<>@$=*!]/', $nnAccountHolder)){
          Mage::throwException(Mage::helper('novalnet')->__('This is not a valid Account Holder Name').'!');
      }  
		if (preg_match("/\D/", $nnAccountNumber)){
			Mage::throwException(Mage::helper('novalnet')->__('This is not a valid account number.').'!');
		}
		if (preg_match("/\D/", $nnBankSortingCode)){
			Mage::throwException(Mage::helper('novalnet')->__('This is not a valid bank sorting code.').'!');
		}
		if (strlen($nnAccountNumber) < self::ACCNO_MIN_LENGTH) {
			Mage::throwException(Mage::helper('novalnet')->__('This is not a valid account number.').'!');
		}
		if (strlen($nnBankSortingCode) < self::BANK_SORTCODE_LENGTH) {
			Mage::throwException(Mage::helper('novalnet')->__('This is not a valid bank sorting code.').'!');
		}
		if ($infoInstance->getAcdcValidationFlag() && !$infoInstance->getNnAcdc()){
			Mage::throwException(Mage::helper('novalnet')->__('You must check ACDC'.'!'));
		}
		if( $infoInstance->getCallbackPinValidationFlag() && $this->_getMethodSession()->getNnCallbackTid() ) {
			if( !$infoInstance->getNnNewCallbackPin()
				&& !preg_match('/^[0-9]+$/', $infoInstance->getNnCallbackPin())
				&& strlen( $infoInstance->getNnCallbackPin() ) != self::CALLBACK_PIN_LENGTH )
			{
				Mage::throwException(Mage::helper('novalnet')->__('This is not a valid pin code.'));
			}
		}
		// Call for pin generation
		if( $this->isCallbackTypeCall() && !$this->_isPlaceOrder() && $this->getCallbackConfigData()!=3) {
			if( $this->_getMethodSession()->getNnCallbackTid() ) {
				if($infoInstance->getNnNewCallbackPin()) {
					$this->regenerateCallbackPin();
				}else {
					$this->_getMethodSession()
						->setNnCallbackPin($infoInstance->getNnCallbackPin());
				}
			}else {
				$this->generateCallbackPin();
			}
		}else if($this->isCallbackTypeCall() && !$this->_isPlaceOrder() && $this->getCallbackConfigData()==3){
			if(!$this->_getMethodSession()->getNnCallbackTid()) {
				$this->generateCallbackEmail();
			}
		}
		
		if($this->_isPlaceOrder()){
			$this->_validateCallbackProcess();
		}
		return $this;
	}
	
	public function prepareSave() {
		$infoInstance = $this->getInfoInstance();
		$t = $infoInstance->getData();
		if( $this->_isPlaceOrder() ) {
			$infoInstance->setNnAccountNumber(substr($infoInstance->getAccountNumber(), 0, -4) . '****' )
				->setNnBankSortingCode(substr($infoInstance->getBankSortingCode(), 0, -4) . '****' )
			;
		}
		return $this;
	}
	
    public function getTitle() {
        //return $this->getConfigData('title');
		return Mage::helper('novalnet')->__($this->getConfigData('title'));
    }
	
	public function getCheckoutRedirectUrl() {
		return false;
	}
	
	public function getIsCentinelValidationEnabled() {
		return false;
	}
	
	private function generateCallbackPin() {
		$request  = $this->_buildRequest(self::POST_CALLBACK);
		$response = $this->_postRequest($request, self::POST_CALLBACK);
		if( $response->getStatus() == self::RESPONSE_CODE_APPROVED ) {
			$this->_getMethodSession()
				->setNnCallbackTid($response->getTid())
				->setNnTestMode($response->getTestMode())
				->setNnCallbackTidTimeStamp(time())
				->setOrderAmount($request->getAmount())
				->setNnCallbackSuccessState(true)
			;
			$this->getInfoInstance()->save();
			$text = Mage::helper('novalnet')->__('You receive a call soon! Please enter the PIN code received in the text box.');
		}else {
			$text = Mage::helper('novalnet')->__( utf8_decode($response->getStatusDesc()) );
		}
		Mage::throwException($text);
	}

	private function generateCallbackEmail() {
		$request  = $this->_buildRequest(self::POST_CALLBACK);
		$response = $this->_postRequest($request, self::POST_CALLBACK);
		if( $response->getStatus() == self::RESPONSE_CODE_APPROVED ) {
			$this->_getMethodSession()
				->setNnCallbackTid($response->getTid())
				->setNnTestMode($response->getTestMode())
				->setNnCallbackTidTimeStamp(time())
				->setOrderAmount($request->getAmount())
				->setNnCallbackSuccessState(true)
			;
			$this->getInfoInstance()->save();
				$text = Mage::helper('novalnet')->__('Bitte antworten Sie auf die E-Mail');
		}else {
			$text = Mage::helper('novalnet')->__( $response->getStatusDesc() );
		}
		Mage::throwException($text);
	}
	
	private function regenerateCallbackPin() {
		$request  = $this->_buildRequest(self::POST_NEWPIN);
		$response = $this->_postRequest($request, self::POST_NEWPIN);
		if( $response->getStatus() == self::RESPONSE_CODE_APPROVED ) {
			$text = Mage::helper('novalnet')->__('You receive a call soon! Please enter the PIN code received in the text box.');
		}else {
			$text = Mage::helper('novalnet')->__( utf8_decode($response->getStatusMessage()) );//status_message
		}
		Mage::throwException($text);
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
	
   /**
     * For Admin orders
   */
	private function _getQuotePaymentById($id) {
        //return $this->_getCheckoutSession()->getQuote()->getPaymentById($id);
		$PaymentById = Mage::getSingleton('checkout/session')->getQuote()->getPaymentById($id);	
		  if(!$PaymentById)
  			 return Mage::getSingleton('adminhtml/session_quote')->getQuote()->getPaymentById($id);
		  else
  			 return $PaymentById;	
    }

    private function _getCheckoutSession() {
        return Mage::getSingleton('checkout/session');
    }
	
	private function _validateCallbackSession() {

		$methodSession = $this->_getMethodSession();
		if($methodSession->hasNnCallbackTid()) {
			if($methodSession->getNnDeDisableTime() && time()>$methodSession->getNnDeDisableTime()){
				$this->_unsetMethodSession();
			}elseif( $methodSession->getOrderAmount() != (int)($this->_getAmount()*100) ) {
				$this->_unsetMethodSession();
				if (!$this->_isPlaceOrder()) {
					Mage::throwException(Mage::helper('payment')->__('Order amount has been changed. Please resubmit the payment method'));
				}
			}
		}	
	}		
			
	private function _validateCallbackProcess() {
		$methodSession = $this->_getMethodSession();
		if($methodSession->getNnCallbackSuccessState()){
				if($this->getCallbackConfigData()==3) {
					$type=self::POST_EMAILSTATUS;
				}elseif($this->getCallbackConfigData()!=3){
					$type=self::POST_PINSTATUS;
				}
			$request = $this->_buildRequest($type);
			$result  = $this->_postRequest($request, $type);
			$result->setTid( $methodSession->getNnCallbackTid() );
			$result->setTestMode( $methodSession->getNnTestMode() );
			
			// Analyze the response from Novalnet
			if ($result->getStatus() == self::RESPONSE_CODE_APPROVED) {
				$methodSession->setNnResponseData($result);
				$methodSession->setNnCallbackSuccessState(false);
			} else {
				if($result->getStatus()=='0529006'){ 
					$methodSession->setNnDeDisableTime(time()+(30*60));
				}
				$error = ($result->getStatusDesc()||$result->getStatusMessage())
					? Mage::helper('novalnet')->htmlEscape($result->getStatusMessage().$result->getStatusDesc())
					: Mage::helper('novalnet')->__('Error in capturing the payment')
				;
				Mage::throwException($error);
			}
		}			
	}
	
	private function _getMethodSession() {
		$checkoutSession = $this->_getCheckoutSession();
		if( !$checkoutSession->hasData( $this->getCode() ) ) {
			$checkoutSession->setData( $this->getCode(), new Varien_Object() );
		}
		return $checkoutSession->getData( $this->getCode() );
	}
	
	private function _unsetMethodSession() {
		$this->_getCheckoutSession()->unsetData( $this->getCode() );
		return $this;
	}
	
	private function _getNnAccountHolder() {
		$info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
			return $info->getOrder()->getPayment()->getNnAccountHolder();
        } else {
			return $info->getNnAccountHolder();
        }
	}
	
	private function _getNnAccountNumber() {
		$info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
			return $info->getOrder()->getPayment()->getNnAccountNumber();
        } else {
			return $info->getNnAccountNumber();
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
	
	private function _getNnBankSortingCode() {
		$info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
			return $info->getOrder()->getPayment()->getNnBankSortingCode();
        } else {
			return $info->getNnBankSortingCode();
        }
	}
	
	private function _getOrderId(){
        $info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
            return $info->getOrder()->getIncrementId();
        } else {
			return true;
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
	
	private function _getBillingAddress() {
		$info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
			return $info->getOrder()->getBillingAddress();
        } else {
			return $info->getQuote()->getBillingAddress();
        }
	}
	
	private function _getBillingCountryCode() {
		$info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
			return $info->getOrder()->getBillingAddress()->getCountryId();
        } else {
			return $info->getQuote()->getBillingAddress()->getCountryId();
        }
	}
	
	private function _getCurrencyCode(){
        $info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
			return $info->getOrder()->getBaseCurrencyCode();
        } else {
			return $info->getQuote()->getBaseCurrencyCode();
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
}
