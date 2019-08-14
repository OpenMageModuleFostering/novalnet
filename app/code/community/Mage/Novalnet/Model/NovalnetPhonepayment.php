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

class Mage_Novalnet_Model_NovalnetPhonepayment extends Mage_Payment_Model_Method_Abstract
{
  const CGI_URL = 'https://payport.novalnet.de/paygate.jsp';
  const CGI_URL2= 'https://payport.novalnet.de/nn_infoport.xml';#'nn_infoport_test.xml';
  const RESPONSE_DELIM_CHAR = '&';
  const RESPONSE_CODE_APPROVED = 100;
  const PAYMENT_METHOD = 'PHONEPAYMENT';
  const KEY = 18;
	const AMOUNT_MIN = 90;
	const AMOUNT_MAX = 1000;#10;#todo: 
	const CURRENCY = 'EUR';
	const CODE = 'novalnet_tel';
    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */
	protected $_code = 'novalnetPhonepayment';
	protected $_formBlockType = 'novalnet/phonepayment_form';
	protected $_infoBlockType = 'novalnet/phonepayment_info';
	protected $code = 'novalnet_tel';
	protected $public_title = 'Telefonpayment';
	protected $amount = 0;
	protected $aBillingAddress = array();
	protected $aryResponse = array();
	protected $urlparam = '';
	protected $text = '';
	protected $debug = false;

     
    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize = false;

    /**
     * Can capture funds online?
     */
    protected $_canCapture = true;

    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial = true;

    /**
     * Can refund online?
     */
    protected $_canRefund = false;

    /**
     * Can void transactions online?
     */
    protected $_canVoid = false;

    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal = true;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout = true;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping  = true;

    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc = false;

    /**
     * Here you will need to implement authorize, capture and void public methods
     *
     * @see examples of transaction specific public methods such as
     * authorize, capture and void in Mage_Paygate_Model_Authorizenet
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        return $this;
    }
    public function capture(Varien_Object $payment, $amount)
    {
      $error = false;
      $payment->setAmount($amount);
      $this->debug2($amount, 'magento_capture_amount.txt');

      $order = $payment->getOrder();
      $note = $order->getCustomerNote();
      if ($note)
      {
        $note .= '<br />'.Mage::helper('novalnet')->__('Comment').': ';
        $note .= $order->getCustomerNote();
      }
      if ( !$this->getConfigData('live_mode') ){
        $note .= '<br /><b>'.strtoupper(Mage::helper('novalnet')->__('Testorder')).'</b>';
      }

      $order->setCustomerNote($note);
      $order->setCustomerNoteNotify(true);

        $request = $this->_buildRequest($payment);
        $this->debug2($request, 'magento_capture_request.txt');
        $result = $this->_postRequest($request, $payment);
        $this->debug2($result, 'magento_capture_result.txt');
        if ($result->getStatus() == self::RESPONSE_CODE_APPROVED) {
        $this->debug2($result, 'magento_capture_resultok.txt');
            $payment->setStatus(self::STATUS_APPROVED);
            $payment->setLastTransId($result->getTid());
            $id = $payment->getNnId();
            $quote_payment = $this->getQuote()->getPaymentById($id);
          if ($quote_payment)#to avoid error msg. in admin interface
          {
            $quote_payment->save();
          }
        }
        else {
          $this->debug2($result, 'magento_capture_resultnotok.txt');
            if ($result->getStatusDesc()) {
                $error = Mage::helper('novalnet')->htmlEscape($result->getStatusDesc());
            }else {
                $error = Mage::helper('novalnet')->__('Error in capturing the payment');
            }
        }

        if ($error !== false) {
          $this->debug2($error, 'magento_error.txt');
          Mage::throwException($error);
        }
        return $this;
    }
    public function refund(Varien_Object $payment, $amount)
    {
        return $this;
    }

    public function void(Varien_Object $payment)
    {
        return $this;
    }
    /**
     * Prepare request to gateway
     *
     * @link http://www.authorize.net/support/AIM_guide.pdf
     * @param Mage_Sales_Model_Document $order
     * @return unknown
     */
    protected function _saveObject (Varien_Object $payment)
    {
        $order = $payment->getOrder();
        if (!empty($order)) {
            $billing = $order->getBillingAddress();
        }
    }
    protected function _buildRequest(Varien_Object $payment)
    {
        $order = $payment->getOrder();
        if (session_is_registered('tid')){
          $this->debug2($order, 'magento_order2.txt');
        }
        $request = Mage::getModel('novalnet/novalnet_request');

        $request->setvendor($this->getConfigData('merchant_id'))
        ->setauth_code($this->getConfigData('auth_code'))
        ->setproduct($this->getConfigData('product_id'))
        ->settariff($this->getConfigData('tariff_id'))
        ->settest_mode((!$this->getConfigData('live_mode'))? 1: 0);

        $request->setcurrency($order->getOrderCurrencyCode());

        if($payment->getAmount()){
            $request->setamount(round($payment->getAmount(), 2) * 100);
        }

        if (!empty($order)) {
            $request->setinput1($order->getIncrementId());

            $billing = $order->getBillingAddress();
            $street = preg_split("/(\d)/",$billing->getStreet(1),2,PREG_SPLIT_DELIM_CAPTURE);
            if (!$billing->getStreet(1)){Mage::throwException(Mage::helper('novalnet')->__('Street missing'));}
            if (!empty($billing)) {
          if (session_is_registered('tid')){
            $this->debug2($billing, 'magento_billing2.txt');
          }

          if (!$order->getCustomerEmail())
          {
            Mage::throwException(Mage::helper('novalnet')->__('Email address missing'));
          }

                $request->setfirst_name($billing->getFirstname())
                ->setLast_name($billing->getlastname())
                ->setstreet($street[0].$street[1].$street[2])
                ->setcity($billing->getCity())
                ->setzip($billing->getPostcode())
                ->setcountry($billing->getCountry())
                ->settel($billing->getTelephone())
                ->setfax($billing->getFax())
                ->setremote_ip(Mage::helper('novalnet')->getRealIpAddr())
                ->setgender('u')
                ->setemail($order->getCustomerEmail())
                ->setInput1('order_id')
				->setOrderNo($this->_getOrderId())
                ->setInputval1($this->_getOrderId())
                ->setsearch_in_street(1);
            }
        }
        $request->setkey(self::KEY);
        
		if (session_is_registered('tid')){
			$this->urlparam  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
			$this->urlparam .= '<nnxml><info_request><vendor_id>'.$this->getConfigData('merchant_id').'</vendor_id>';
			$this->urlparam .= '<vendor_authcode>'.$this->getConfigData('auth_code').'</vendor_authcode>';
			$this->urlparam .= '<request_type>NOVALTEL_STATUS</request_type><tid>'.$_SESSION['tid'].'</tid>';
			$this->urlparam .= '<lang>DE</lang></info_request></nnxml>';
		}

        return $request;
    }

    protected function _postRequest(Varien_Object $request, Varien_Object $payment=null)
    {
        $result = Mage::getModel('novalnet/novalnet_result');
        $client = new Varien_Http_Client();

        if (!session_is_registered('tid')){#first call
			    $client->setUri(self::CGI_URL);
        }else{#second call
          $client->setUri(self::CGI_URL2);
        }

        $httpClientConfig = array( 'maxredirects'=>0 );
		if( ((int)$this->getConfigData( 'gateway_timeout' )) > 0 ) {
			$httpClientConfig['timeout'] = (int)$this->getConfigData( 'gateway_timeout' );
		}
        $client->setConfig( $httpClientConfig );

		if (!session_is_registered('tid')){#first call
			$request->toLatin1();
			$client->setParameterPost($request->getData());
			$this->debug2($request->getData(), 'magento_request1.txt');
		}else{#secondcall
                $client->setHeaders('Content-Type', 'application/atom+xml');
                $client->setRawData($this->urlparam);
				$this->debug2($this->urlparam, 'magento_request2.txt');
		}

        $client->setMethod(Zend_Http_Client::POST);
        try {
			$method2 = '';
			if (session_is_registered('tid')){
				$method2 = 'POST';
			}
            $response = $client->request($method2);
        } catch (Exception $e) {
          $this->debug2($e->getMessage(), 'magento_exceptionMsg.txt');
          $result->setResponseCode(-1)
          ->setResponseReasonCode($e->getCode())
          ->setResponseReasonText($e->getMessage());
          Mage::throwException(
          Mage::helper('novalnet')->__('Gateway request error: %s', $e->getMessage())
          );
        }
        $responseBody = $response->getBody();

        if (session_is_registered('tid')){#second call
			$this->debug2($response, 'magento_response2.txt');
			$xml = serialize($response);
			$this->debug2($xml, 'magento_xml.txt');

			if (!preg_match('|\<nnxml\>(.+)\</nnxml\>|is', $xml, $matches)){
				Mage::throwException(Mage::helper('novalnet')->__('Error in payment gateway').': '.Mage::helper('novalnet')->__('Response contains no XML'));
			}

			$xml = $matches[1];
			$this->debug2($xml, 'magento_xml_purged.txt');

			$data = $xml; #$response;
			if(strstr($data, '<novaltel_status>'))
			{
				preg_match('/novaltel_status>?([^<]+)/i', $data, $matches);
				$aryResponse['status'] = $matches[1];
				$result->setStatus($aryResponse['status']);
				$this->debug2($aryResponse['status'], 'magento_novaltel_status.txt');

				preg_match('/novaltel_status_message>?([^<]+)/i', $data, $matches);
				$aryResponse['status_desc'] = $matches[1];
				$result->setStatusDesc($aryResponse['status_desc']);
				$result->setTid($_SESSION['tid']);
				$this->debug2($aryResponse['status_desc'], 'magento_novaltel_status_desc.txt');

				if ($aryResponse['status']!= 100){
					if ($aryResponse['status'] == 18 and isset($_SESSION['novalnet_tel'])){
						Mage::throwException(Mage::helper('novalnet')->__('Gateway request error: %s', $aryResponse['status_desc'])."\n".Mage::helper('novalnet')->__('Did you called this number').': '.preg_replace('/(\d{4})(\d{4})(\d{4})(\d{4})/', "$1 $2 $3 $4", $_SESSION['novalnet_tel']));
					}else{
						Mage::throwException(Mage::helper('novalnet')->__('Gateway request error: %s', $aryResponse['status_desc']));
					}
				}
			}
			$this->debug2($result, 'magento_result2.txt');
		}else {#first call
			$this->debug2($response, 'magento_response1.txt');
			$r = explode(self::RESPONSE_DELIM_CHAR, $responseBody);

			if ($r) {
				foreach($r as $key => $value)
				{
					if($value!="")
					{
						$aryKeyVal = explode("=",$value);
						$aryResponse[$aryKeyVal[0]] = $aryKeyVal[1];
					}
				}

				if (isset($aryResponse['status'])){
					$result->setStatus($aryResponse['status']);
				}
				if (isset($aryResponse['tid'])){
					$result->setTid($aryResponse['tid']);
				}

				if (isset($aryResponse['status_desc'])){
					$result->setStatusDesc($aryResponse['status_desc']);
				}

        if (isset($aryResponse['status']) and $aryResponse['status'] != 100) {
          Mage::throwException(Mage::helper('novalnet')->__('Error in payment gateway') .': '.utf8_encode($aryResponse['status_desc']));
        }
				#$this->debug2($result, 'magento_result1.txt');
			} else {
				Mage::throwException(Mage::helper('novalnet')->__('Error in payment gateway'));
			}
		}
        $result->toUtf8();
		if (session_is_registered('tid')){
			$this->debug2($response, 'magento_response2.txt');
			$this->debug2($aryResponse, 'magento_aryResponse2.txt');
		}
		if ($payment){
			$this->debug2($payment, 'magento_payment2.txt');
			$order = $payment->getOrder();
		}

		if ($aryResponse['status'] == 100){
			if (session_is_registered('tid')){
				session_unregister('tid');
			}
			if (session_is_registered('novalnet_tel')){
				session_unregister('novalnet_tel');
			}
		}
		$this->aryResponse = $aryResponse;
        return $result;
    }


    public function assignData($data)
    {
		if(!session_is_registered('tid')){
			$this->debug2($data, 'magento_assignData1.txt');
			$addresses = $this->getQuote()->getAllAddresses();
			$this->checkAmountAllowed();
			$this->aBillingAddress = $this->getBillingAddress($addresses);
			$this->debug2($this, 'magento_assignData_this.txt');
			$this->getFirstCall();
		}else{
			$this->debug2($data, 'magento_assignData2.txt');
		}

        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info=$this->getInfoInstance();

        return $this;
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
            //$this->_checkout = Mage::getSingleton('checkout/type_multishipping');
            $this->_checkout = Mage::getSingleton('checkout/session');
        }
        return $this->_checkout;
    }
   
    public function getTitle() {
        //return $this->getConfigData('title');
		return Mage::helper('novalnet')->__($this->getConfigData('title'));
    }
    
   public function validate()
   {
         parent::validate();
         $info = $this->getInfoInstance();
         return $this;
   }
   public function isPublicIP($value)
   {
        if(!$value || count(explode('.',$value))!=4)
        {
            return false;
        }
        return !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value);
   }

  public function getAmount4Request($amount) {
    $orig_amount = $amount;
    if(preg_match('/[,.]$/', $amount)) {
      $amount = $amount . '00';
    }
    else if(preg_match('/[,.][0-9]$/', $amount)) {
      $amount = $amount . '0';
    }

    $amount = round($amount, 2);
    $amount = str_replace(array('.', ','), array('',''), $amount);
    return$amount;
  }
  public function getNote($aryResponse)
	{
		#todo: Kontoinhaber fehlt
		$note = Mage::helper('novalnet')->__('Please transfer the amount to following account').":<br /><br />\n\n";

		$note.= Mage::helper('novalnet')->__('Account Holder2').": NOVALNET AG<br />\n";
		$note.= Mage::helper('novalnet')->__('Account Number').": ".$aryResponse['invoice_account']."<br />\n";
		$note.= Mage::helper('novalnet')->__('Bank Sorting Code').": ".$aryResponse['invoice_bankcode']."<br />\n";
		$note.= Mage::helper('novalnet')->__('Bank').": ".$aryResponse['invoice_bankname'].', Muenchen<br /><br />'."\n\n"; #.$aryResponse['invoice_bankplace']."\n\n";

		$note.= "IBAN: ".$aryResponse['invoice_iban']."<br />\n";
		$note.= "SWIFT / BIC: ".$aryResponse['invoice_bic']."<br /><br />\n\n";

		$note.= Mage::helper('novalnet')->__('Amount').": ".str_replace('.', ',', $aryResponse['amount'])." EUR<br />\n";
		$note.= Mage::helper('novalnet')->__('Reference').": TID ".$aryResponse['tid']."<br />\n";
		$note.= Mage::helper('novalnet')->__('Please note that the Transfer can only be identified with the above mentioned Reference').'.';
		return$note;
	}

	public function checkAmountAllowed()
	{

    $amount = sprintf('%.2f', $this->getQuote()->getGrandTotal()) * 100;
   		if ($amount >= self::AMOUNT_MIN and $amount <= self::AMOUNT_MAX){
			$this->amount = $amount;
			return true;
		}
		Mage::throwException(Mage::helper('novalnet')->__('Amount below 0.90 Euro and above 10.00 Euro is not accepted'));
	}
	public function debug2($object, $filename, $debug = false)
	{
		if (!$this->debug and !$debug){return;}
		$fh = fopen("/tmp/$filename", 'a+');
		if (gettype($object) == 'object' or gettype($object) == 'array'){
			fwrite($fh, serialize($object));
		}else{
			fwrite($fh, $object);
		}
		fwrite($fh, "<hr />\n");
		fclose($fh);
	}
	public function getFirstCall()
	{
		$url = 'https://payport.novalnet.de/paygate.jsp';
		$request = Mage::getModel('novalnet/novalnet_request');
        $request->setvendor($this->getConfigData('merchant_id'))
        ->setauth_code($this->getConfigData('auth_code'))
        ->setproduct($this->getConfigData('product_id'))
        ->settariff($this->getConfigData('tariff_id'));

		$request->setkey(self::KEY);
		$request->setcurrency(self::CURRENCY);
		$request->setamount($this->amount);

		$request->setfirst_name(utf8_encode($this->aBillingAddress['firstname']))
		->setLast_name(utf8_encode($this->aBillingAddress['lastname']))
		->setstreet(utf8_encode($this->aBillingAddress['street']))
		->setcity(utf8_encode($this->aBillingAddress['city']))
		->setzip($this->aBillingAddress['postcode'])
		->setcountry(utf8_encode($this->aBillingAddress['country']))
		->settel($this->aBillingAddress['telephone'])
		->setfax($this->aBillingAddress['fax'])
		->setremote_ip(Mage::helper('novalnet')->getRealIpAddr())
		->setgender('u')
		->setemail($this->aBillingAddress['email'])
		->setsearch_in_street(1);
		#$request->setinvoice_type(self::PAYMENT_METHOD);

		$this->debug2($request, 'magento_getFirstCall_request.txt');
		$result = $this->_postRequest($request);

		if(!$this->aryResponse){
			Mage::throwException('Params (aryResponse) missing');
		}
		$data = '';
		foreach ($this->aryResponse as $k=>$v){
			$data.= "$k=$v&";
		}
		if (substr($data, -1) == '&'){
			$data = substr($data, 0, -1);
		}

		if(strstr($data, '<novaltel_status>'))
		{
			preg_match('/novaltel_status>?([^<]+)/i', $data, $matches);
			$aryResponse['status'] = $matches[1];

			preg_match('/novaltel_status_message>?([^<]+)/i', $data, $matches);
			$aryResponse['status_desc'] = $matches[1];
		}
		else
		{
			#capture the result and message and other parameters from response data '$data' in an array
			$aryPaygateResponse = explode('&', $data);
			foreach($aryPaygateResponse as $key => $value)
			{
			  if($value!="")
			  {
				$aryKeyVal = explode("=",$value);
				$aryResponse[$aryKeyVal[0]] = $aryKeyVal[1];
			  }
			}
		}

		if((session_is_registered('tid') and $_SESSION['tid'] != '') && $aryResponse['status']==100) #### SECOND CALL -> On successful payment ####
		{
		   #### Redirecting the user to the checkout page ####
		   session_unregister('tid'); #$_SESSION['tid'] = '';
		   if (session_is_registered('novalnet_tel')){
				session_unregister('novalnet_tel');#$_SESSION['novalnet_tel'] = '';
		   }
		}
		else #### On payment failure ####
		{
		   $status = '';
		   $wrong_amount = '';#todo:
		   if($wrong_amount==1){
				$status = '1';
				$aryResponse['status_desc'] = 'novalnet_amount_error';}

		   ### Passing the Error Response from Novalnet's paygate to payment error ###
		   elseif($aryResponse['status']==100 && $aryResponse['tid'])
		   {
				$aryResponse['status_desc']='';
				if(!session_is_registered('tid')){
					session_register('tid');
				}
				if(!session_is_registered('novalnet_tel')){
					session_register('novalnet_tel');
				}

				$_SESSION['tid'] = $aryResponse['tid'];
				$_SESSION['novalnet_tel'] = $aryResponse['novaltel_number'];
				$text = Mage::helper('novalnet')->__('Following steps are required to complete the telephone payment process').':'."\n";
				$text .= Mage::helper('novalnet')->__('Step').'1: ';
				$text .= Mage::helper('novalnet')->__('Please dial this number').': '.preg_replace('/(\d{4})(\d{4})(\d{4})(\d{4})/', "$1 $2 $3 $4", $_SESSION['novalnet_tel']).".\n";
				$text .= Mage::helper('novalnet')->__('Step').'2: ';
				$text .= Mage::helper('novalnet')->__('Please wait for the Signal tone and hangup the reciever').'. ';
				$text .= Mage::helper('novalnet')->__('Please click on continue after your successive call').'.'."\n";
				$text .= '* '. Mage::helper('novalnet')->__('This call costs').' '.($this->amount/100).' Euro ('.Mage::helper('novalnet')->__('inclusive tax').') ';
				$text .= Mage::helper('novalnet')->__('and is only possible from German Landline Telefon connection').'! *';

				$this->text = $text;
				Mage::throwException($text);#show note for client to call...
			}
		   elseif($aryResponse['status']==18){$error = true;}
		   elseif($aryResponse['status']==19)
		   {
				if(!session_is_registered('tid')){
					$_SESSION['tid'] = '';
				}
				if(!session_is_registered('novalnet_tel')){
					$_SESSION['novalnet_tel'] = '';
				}
			}
		   else $status = $aryResponse['status'];

		   ### Passing through the Error Response from Novalnet's paygate into order-info ###
		   #$order->info['comments'] .= '. Novalnet Error Code : '.$aryResponse['status'].', Novalnet Error Message : '.$aryResponse['status_desc'];

			#$payment_error_return = 'payment_error=' . self::CODE. '&error=' . urlencode($aryResponse['status_desc']);
		}
		if ($aryResponse['status']!= 100){
			Mage::throwException(Mage::helper('novalnet')->__('Gateway request error: %s', $aryResponse['status_desc']));
		}
	}
	public function getBillingAddress($addresses)
	{
		$this->debug2($addresses, 'magento_addresses.txt');
		$addresses = serialize($addresses);
		/*"address_type";s:7:"billing";s:5:"email";s:14:"jz@novalnet.de";s:6:"prefix";N;s:9:"firstname";s:7:"Jianguo";s:10:"middlename";N;s:8:"lastname";s:5:"Zhang";s:6:"suffix";N;s:7:"company";s:11:"Novalnet AG";s:6:"street";s:14:"Stiftsbogen 70";s:4:"city";s:8:"M¨¹nchen";s:6:"region";s:6:"Bayern";s:9:"region_id";s:2:"81";s:8:"postcode";s:5:"81375";s:10:"country_id";s:2:"DE";s:9:"telephone";s:12:"089 47027059";s:3:"fax";s:0:"";s:15:"same_as_billing"*/
		$t = preg_match('/\"address\_type\"\;s\:7\:\"billing\"(.{100,1000})\"same\_as\_billing\"/is', $addresses, $aMatch);
		if (!$aMatch or !$aMatch[1]){
			Mage::throwException(Mage::helper('novalnet')->__('Billing Addr. not found'));
		}
		$foundString = $aMatch[1];
		$t = preg_match('/\"firstname\"\;s\:\d*\:\"(.+)\"\;s\:10\:\"middlename\"\;/',$foundString, $aMatch);
		if ($aMatch and $aMatch[1]){
			$aBillingAddress['firstname'] = $aMatch[1];
		}else{
			$aBillingAddress['firstname'] = '';
		}

		$t = preg_match('/\"lastname\"\;s\:\d*\:\"(.+)\"\;s\:6\:\"suffix\"\;/',$foundString, $aMatch);
		if ($aMatch and $aMatch[1]){
			$aBillingAddress['lastname'] = $aMatch[1];
		}else{
			$aBillingAddress['lastname'] = '';
		}

		$t = preg_match('/\"street\"\;s\:\d*\:\"(.+)\"\;s\:4\:\"city\"\;/',$foundString, $aMatch);
		if ($aMatch and $aMatch[1]){
			$aBillingAddress['street'] = $aMatch[1];
		}else{
			$aBillingAddress['street'] = '';
		}

		$t = preg_match('/\"city\"\;s\:\d*\:\"(.+)\"\;s\:6\:\"region\"\;/',$foundString, $aMatch);
		if ($aMatch and $aMatch[1]){
			$aBillingAddress['city'] = $aMatch[1];
		}else{
			$aBillingAddress['city'] = '';
		}

		$t = preg_match('/\"postcode\"\;s\:\d*\:\"(.+)\"\;s\:10\:\"country_id\"\;/',$foundString, $aMatch);
		if ($aMatch and $aMatch[1]){
			$aBillingAddress['postcode'] = $aMatch[1];
		}else{
			$aBillingAddress['postcode'] = '';
		}

		$t = preg_match('/\"email\"\;s\:\d*\:\"(.+)\"\;s\:6\:\"prefix\"\;/',$foundString, $aMatch);
		if ($aMatch and $aMatch[1]){
			$aBillingAddress['email'] = $aMatch[1];
		}else{
			$aBillingAddress['email'] = '';
		}

		$t = preg_match('/\"telephone\"\;s\:\d*\:\"(.+)\"\;s\:3\:\"fax\"\;/',$foundString, $aMatch);
		if ($aMatch and $aMatch[1]){
			$aBillingAddress['telephone'] = $aMatch[1];
		}else{
			$aBillingAddress['telephone'] = '';
		}

		$t = preg_match('/\"country_id\"\;s\:\d*\:\"(.+)\"\;s\:9\:\"telephone\"\;/',$foundString, $aMatch);
		if ($aMatch and $aMatch[1]){
			$aBillingAddress['country'] = $aMatch[1];
		}else{
			$aBillingAddress['country'] = '';
		}

		$t = preg_match('/\"fax\"\;s\:\d*\:\"(.+)\"\;s\:15\:\"same_as_billing\"\;/',$foundString, $aMatch);
		if ($aMatch and $aMatch[1]){
			$aBillingAddress['fax'] = $aMatch[1];
		}else{
			$aBillingAddress['fax'] = '';
		}
		return$aBillingAddress;
	}
	
	public function isAvailable($quote=null)
	{

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
		
		if(!is_null($quote) && parent::isAvailable($quote)){
			$amount = round($quote->getGrandTotal(), 2);
			if( $amount >= 0.9 && $amount <= 10 ) {
				return true;
			}
		}
		return false;
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
}
