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

class Mage_Novalnet_Model_NovalnetInvoice extends Mage_Payment_Model_Method_Abstract
{
    const CGI_URL                = 'https://payport.novalnet.de/paygate.jsp';
	const XML_URL                = 'https://payport.novalnet.de/nn_infoport.xml';
    const PAYMENT_METHOD         = 'Invoice';
    const RESPONSE_DELIM_CHAR    = '&';
    const RESPONSE_CODE_APPROVED = 100;
	const CALLBACK_PIN_LENGTH    = 4;
	
	// are used with _buildRequest and _postRequest
	const POST_NORMAL    = 'normal';
	const POST_CALLBACK  = 'callback';
	const POST_NEWPIN    = 'newpin';
	const POST_PINSTATUS = 'pinstatus';
	const POST_EMAILSTATUS = 'emailstatus';
	
	const ISOCODE_FOR_GERMAN = 'DE';
	
    private   $_nnPaymentId = 27;
    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */
    protected $_code = 'novalnetInvoice';
    protected $_formBlockType = 'novalnet/invoice_form';
    protected $_infoBlockType = 'novalnet/invoice_info';
	
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
		return $this->_checkNnAuthData() && parent::isAvailable();
	}
	


	private function _checkNnAuthData() {
		return strlen($this->getConfigData('merchant_id'))
			&& strlen($this->getConfigData('auth_code'))
			&& strlen($this->getConfigData('product_id'))
			&& strlen($this->getConfigData('tariff_id'))
		;
	}
	
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
	
    protected function _buildRequest($type=self::POST_NORMAL) {
		if( $type == self::POST_NORMAL || $type == self::POST_CALLBACK ) {
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
			;
			$infoInstance = $this->getInfoInstance();
			if( $type == self::POST_CALLBACK ) {
				if($this->getConfigData('callback') == 1) {
					$request->setTel($infoInstance->getNnCallbackTel());
					$request->setPinByCallback(1);
				}else if($this->getConfigData('callback') == 2) {
					$request->setMobile($infoInstance->getNnCallbackTel());
					$request->setPinBySms(1);
				}else if($this->getConfigData('callback') == 3){
					$request->setReplyEmailCheck(1);
				}
			}else{
    	   			$request->setInvoiceRef('BNR-'.$this->getConfigData('product_id').'-'. $this->_getOrderId());
			}
			$request->toLatin1();
			return $request;
		}else if($type == self::POST_NEWPIN) {
				$request  = '<?xml version="1.0" encoding="UTF-8"?>';
				$request .= '<nnxml><info_request>';
				$request .= '<vendor_id>' . $this->getConfigData( 'merchant_id' ) . '</vendor_id>';
				$request .= '<vendor_authcode>' . $this->getConfigData( 'auth_code' ) . '</vendor_authcode>';
				$request .= '<request_type>TRANSMIT_PIN_AGAIN</request_type>';
				$request .= '<tid>' . $this->_getMethodSession()->getNnCallbackTid() . '</tid>';
				$request .= '</info_request></nnxml>';
			return $request;
		}else if($type == self::POST_PINSTATUS) {
			$request  = '<?xml version="1.0" encoding="UTF-8"?>';
			$request .= '<nnxml><info_request>';
			$request .= '<vendor_id>' . $this->getConfigData( 'merchant_id' ) . '</vendor_id>';
			$request .= '<vendor_authcode>' . $this->getConfigData( 'auth_code' ) . '</vendor_authcode>';
			$request .= '<request_type>PIN_STATUS</request_type>';
			$request .= '<tid>' . $this->_getMethodSession()->getNnCallbackTid() . '</tid>';
			$request .= '<pin>' . $this->_getMethodSession()->getNnCallbackPin() . '</pin>';
			$request .= '</info_request></nnxml>';
			return $request;
		}else if($type == self::POST_EMAILSTATUS) {
			$request  = '<?xml version="1.0" encoding="UTF-8"?>';
			$request .= '<nnxml><info_request>';
			$request .= '<vendor_id>' . $this->getConfigData( 'merchant_id' ) . '</vendor_id>';
			$request .= '<vendor_authcode>' . $this->getConfigData( 'auth_code' ) . '</vendor_authcode>';
			$request .= '<request_type>REPLY_EMAIL_STATUS</request_type>';
			$request .= '<tid>' . $this->_getMethodSession()->getNnCallbackTid() . '</tid>';
			$request .= '</info_request></nnxml>';
			return $request;
		}
    }
	
	// Amount in cents
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

    protected function _postRequest($request, $type=self::POST_NORMAL)
    {
		$result = Mage::getModel('novalnet/novalnet_result');
    	$httpClientConfig = array('maxredirects'=>0);
		if(((int)$this->getConfigData( 'gateway_timeout' )) > 0) {
			$httpClientConfig['timeout'] = (int)$this->getConfigData( 'gateway_timeout' );
		}
		$client = new Varien_Http_Client( self::CGI_URL, $httpClientConfig );
		if( $type == self::POST_NEWPIN || $type == self::POST_PINSTATUS || $type == self::POST_EMAILSTATUS  ) {
			$client->setUri( self::XML_URL );
			$client->setRawData( $request )->setMethod( Varien_Http_Client::POST );
		}else {
			$client->setParameterPost( $request->getData() )->setMethod( Varien_Http_Client::POST );
		}
		$response = $client->request();  
		
		if (!$response->isSuccessful()) {
			Mage::throwException( Mage::helper('novalnet')->__('Gateway request error: %s', $response->getMessage()) );
		}
		if( $type == self::POST_NEWPIN || $type == self::POST_PINSTATUS || $type == self::POST_EMAILSTATUS  ) {
			$result = new Varien_Simplexml_Element( $response->getRawBody() );
			$result = new Varien_Object( $this->_xmlToArray( $result ) );
		}else {
			$result->addData( $this->_deformatNvp( $response->getBody() ) );
			$result->toUtf8();
		}
        return $result;
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
        $infoInstance->setNnCallbackPin($data->getCallbackPin())
			->setNnNewCallbackPin($data->getNewCallbackPin())
			->setNnCallbackTel($data->getCallbackTel())
		;
		if( $this->isCallbackTypeCall() && $this->getCallbackConfigData()!=3) {
			$infoInstance->setCallbackPinValidationFlag( true );
		}
        return $this;
    }
	
	public function validate() {
	
		$session = Mage::getSingleton('checkout/session');
		
	if(!$this->_isPlaceOrder()){
		parent::validate();
		$this->_validateCallbackSession();
		$infoInstance = $this->getInfoInstance();
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
					$this->_regenerateCallbackPin();
				}else {
					$this->_getMethodSession()
						->setNnCallbackPin($infoInstance->getNnCallbackPin());
				}
			}else {
				$this->_generateCallbackPin();
			}
			
		}else if(!$this->_isPlaceOrder() && $this->getCallbackConfigData()==3){
			if(!$this->_getMethodSession()->getNnCallbackTid()) {
				$this->_generateCallbackEmail();
			}
		}
		
		}
		
		if($this->_isPlaceOrder()){
		if(!$this->isCallbackTypeCall() && !$session->getInvoiceReqData())
		{
			$request = $this->_buildRequest(self::POST_NORMAL);
			$inputval1 = $this->_getOrderId();
			$request->setOrderNo($inputval1)
					->setInvoiceRef('BNR-'.$this->getConfigData('product_id').'-'. $inputval1)
					->setInputval1($inputval1);
			$response  = $this->_postRequest($request, self::POST_NORMAL);
			Mage::getSingleton('checkout/session')->setInvoiceReqData($response);
			Mage::getSingleton('checkout/session')->setInvoiceReqDataNote($this->_getNote($response));
			if($response->getStatus()!='100'){
			
				$session=Mage::getSingleton('checkout/session');
				$session->unsInvoiceReqData()
				        ->unsInvoiceReqDataNote();
				$text = Mage::helper('novalnet')->__($response->getstatus_desc());
				Mage::throwException($text);
			}
			return $this;
		}
			$this->_validateCallbackProcess();
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
	
	private function _generateCallbackPin() {
		$request  = $this->_buildRequest(self::POST_CALLBACK);
		$inputval1 = $this->_getIncrementId();
		$request->setOrderNo($inputval1)
				->setInvoiceRef('BNR-'.$this->getConfigData('product_id').'-'. $inputval1)
				->setInputval1($inputval1);
		$response = $this->_postRequest($request, self::POST_CALLBACK);
		
		if( $response->getStatus() == self::RESPONSE_CODE_APPROVED ) {
		
		Mage::getSingleton('checkout/session')->setInvoiceReqData($response);
		Mage::getSingleton('checkout/session')->setInvoiceReqDataNote($this->_getNote($response));
		
			$this->_getMethodSession()
				->setNnCallbackTid($response->getTid())
				->setNnTestMode($response->getTestMode())
				->setNnCallbackTidTimeStamp(time())
				->setNote($this->_getNote($response))
				->setOrderAmount($request->getAmount())
				->setNnCallbackSuccessState(true)
			;
			$this->getInfoInstance()->save();
			$text = Mage::helper('novalnet')->__('Sie werden in kürze angerufen! Bitte geben Sie den erhaltenen PIN-Code in das Textfeld ein.');
		}else {
			$text = Mage::helper('novalnet')->__( $response->getStatusDesc() );
		}
		Mage::throwException($text);
	}

	private function _generateCallbackEmail() {
		$request  = $this->_buildRequest(self::POST_CALLBACK);
		$inputval1 = $this->_getIncrementId();
		$request->setOrderNo($inputval1)
				->setInvoiceRef('BNR-'.$this->getConfigData('product_id').'-'. $inputval1)
				->setInputval1($inputval1);
		$response = $this->_postRequest($request, self::POST_CALLBACK);
		if( $response->getStatus() == self::RESPONSE_CODE_APPROVED ) {
		
		Mage::getSingleton('checkout/session')->setInvoiceReqData($response);
		Mage::getSingleton('checkout/session')->setInvoiceReqDataNote($this->_getNote($response));
		
			$this->_getMethodSession()
				->setNnCallbackTid($response->getTid())
				->setNnTestMode($response->getTestMode())
				->setNnCallbackTidTimeStamp(time())
				->setNote($this->_getNote($response))
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
	
	private function _regenerateCallbackPin() {
		$request  = $this->_buildRequest(self::POST_NEWPIN);
		$response = $this->_postRequest($request, self::POST_NEWPIN);
		if( $response->getStatus() == self::RESPONSE_CODE_APPROVED ) {
			$text = Mage::helper('novalnet')->__('Sie werden in kürze angerufen! Bitte geben Sie den erhaltenen PIN-Code in das Textfeld ein.');
		}else {
			$text = Mage::helper('novalnet')->__( $response->getStatusMessage() );//status_message
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
	
	private function _getQuotePaymentById($id) {
        return $this->_getCheckoutSession()->getQuote()->getPaymentById($id);
    }
    	
	private function _validateCallbackSession() {
		$methodSession = $this->_getMethodSession();
		if($methodSession->hasNnCallbackTid()) {
			if(time()>($methodSession->getNnCallbackTidTimeStamp()+(30*60))){
				$this->_unsetMethodSession();
				if (!$this->_isPlaceOrder()) {
					Mage::throwException(Mage::helper('payment')->__('Callback session has expired. Please resubmit payment method'));
				}
			}elseif( $methodSession->getOrderAmount() != ($this->_getAmount()*100) ) {
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
					$error = ($result->getStatusDesc()||$result->getStatusMessage())
						? Mage::helper('novalnet')->htmlEscape($result->getStatusMessage().$result->getStatusDesc())
						: Mage::helper('novalnet')->__('Error in capturing the payment')
					;
					Mage::throwException($error);
				}
			}			
	}
	
    private function _getCheckoutSession() {
        return Mage::getSingleton('checkout/session');
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
	
	private function _getNote($result)
    {
		$paymentDuration = (int)trim($this->getConfigData('payment_duration'));
		$helper = Mage::helper('novalnet');
		$note   = NULL;
		$note  .= "<b>".$helper->__('Please transfer the invoice amount with the following information to our payment provider Novalnet AG')."</b><br />";
		$note  .= $paymentDuration
			? ($helper->__('Due Date') . ': ' . date('d.m.Y', strtotime('+' . $paymentDuration . ' days')) . "<br />")
			: NULL
		;
		$note  .= $helper->__('Account Holder2') . ":<b>NOVALNET AG</b><br />";
		$note  .= $helper->__('Account Number') . ":<b>" . $result->getInvoiceAccount() . "</b><br />";
		$note  .= $helper->__('Bank Sorting Code') . ":<b>" . $result->getInvoiceBankcode() . "</b><br />";
		$note  .= $helper->__('Bank') . ":<b>" . $result->getInvoiceBankname() . ", Muenchen </b><br />";
		$note  .= $helper->__('Amount') . ":<b>" . str_replace('.', ',', $result->getAmount()) . " EUR </b><br />";
		$note  .= $helper->__('Reference') . ":<b>TID " . $result->getTid() . "</b><br />";
		$note  .= $helper->__('Only for foreign transfers') . ":<br />";
		$note  .= "IBAN:<b> " . $result->getInvoiceIban() . " </b><br />";
		$note  .= "SWIFT/BIC: <b>" . $result->getInvoiceBic() . " </b><br />";
		return $note;
    }
	
	private function _getOrderId(){
        $info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
            return $info->getOrder()->getIncrementId();
        } else {
			return true;
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
	
	private function _getIncrementId() {
	
		$dbc_collect_order = Mage::getSingleton('core/resource')->getConnection('core_read');
		$magento_version=Mage::getVersion();
		if($magento_version<'1.4.1.0'){
			$tableSalesOrder  =  Mage::getSingleton('core/resource')->getTableName('sales_order');
			$result 	 	  =  $dbc_collect_order->query("SELECT `increment_id` FROM `".$tableSalesOrder."` ORDER BY `entity_id` DESC LIMIT 1");
		}else{
			$tableSalesFlatOrder  =  Mage::getSingleton('core/resource')->getTableName('sales_flat_order');
			$result 		 	  =  $dbc_collect_order->query("SELECT `increment_id` FROM `".$tableSalesFlatOrder."` ORDER BY `entity_id` DESC LIMIT 1");
		}

 			$result_data		  =  $result->fetch(PDO::FETCH_ASSOC);
			$order_id_data		  =  $result_data['increment_id'];
		if(!$order_id_data){
			$last_main_order_id = '100000000';
			$inputval1			= $last_main_order_id;
		}else{
			$last_main_order_id = $order_id_data; 
			$inputval1			= $last_main_order_id;
		}
		$IncrementId = $inputval1+1;	
		return $IncrementId;
	}
	
	public function getOrderPlaceRedirectUrl()
	{
		$this->_unsetMethodSession();
		return Mage::getUrl('novalnet/invoice/invoicefunction');
	}

	
}
