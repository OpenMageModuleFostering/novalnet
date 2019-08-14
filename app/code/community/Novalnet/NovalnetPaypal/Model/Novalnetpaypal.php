<?php
class Novalnet_NovalnetPaypal_Model_NovalnetPaypal extends Mage_Payment_Model_Method_Abstract
{
  /**
  * Availability options
  */
  protected $_code          = 'novalnetpaypal';
  protected $_paymentMethod = 'novalnetpaypal';
  protected $_formBlockType = 'novalnetpaypal/form_novalnetpaypal';
  protected $_infoBlockType = 'novalnetpaypal/info_novalnetpaypal';
  protected $_isGateway               = false;
  protected $_canAuthorize            = true;
  protected $_canCapture              = false;#important; default: false
  protected $_canCapturePartial       = false;
  protected $_canRefund               = false;
  protected $_canVoid                 = false;
  protected $_canUseInternal          = false;
  protected $_canUseCheckout          = true;
  protected $_canUseForMultishipping  = true;
  protected $_isInitializeNeeded      = true;  
  protected $password = '';
  const KEY           = 34;

	/**
     * Instantiate state and set it to state object
     * @param string $paymentAction
     * @param Varien_Object
     */
    public function initialize($paymentAction, $stateObject)
    {
    }  
  
	public function _construct()
    {
        parent::_construct();
        $this->_init('novalnetpaypal/novalnetpaypal');
    }
	
	public function getUrl(){
		return $this->getConfigData('url');
	}
	
	 /**
     * Return redirect block type
     *
     * @return string
     */
    public function getRedirectBlockType()
    {
        return $this->_redirectBlockType;
    }

    /**
     * Return payment method type string
     *
     * @return string
     */
    public function getPaymentMethodType()
    {
        return $this->_paymentMethod;
    }


   /**
	* Get redirect URL
	*
	* @return Mage_Payment_Helper_Data
	*/
	public function getOrderPlaceRedirectUrl()
    {
		$return_url = Mage::getUrl('novalnetpaypal/novalnetpaypal/redirect');
		//file_put_contents(__FILE__.'assignData.txt','I am in getOrderPlaceRedirectUrl function'.print_r($return_url, true));
      return Mage::getUrl('novalnetpaypal/novalnetpaypal/redirect');
    }
	
	public function assignData($data)
    {
       	if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
		//$temp = $info->getData();
			//unset($temp['method_instance']);
		//file_put_contents(__FILE__.'assignData.txt','I am in assignData function'.print_r($temp, true));
        return $this;
    }
	
	public function getSecurityKey(){
		return uniqid(rand(), true);
	}
	
	public function validate()
    {
        parent::validate();

        return $this;
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
            }elseif(!$this->getConfigData('merchant_id') || !$this->getConfigData('auth_code') || !$this->getConfigData('product_id') || !$this->getConfigData('tariff_id') || !$this->getConfigData('paypal_api_signature') || !$this->getConfigData('paypal_api_username') || !$this->getConfigData('paypal_api_password') || !$this->getConfigData('password')) {
				return false;
			}
		return parent::isAvailable();
	}
	
	public function getFormFields()
  	{
	$billing  			= $this->getOrder()->getBillingAddress();
	$security 			= $this->getSecurityKey();
	$this->getOrder()->getPayment()->setSuSecurity($security)->save();
	$order            	= $this->getOrder();
    $this->password   	= $this->getConfigData('password');
    #$_SESSION['mima'] = $this->password;#todo: ?????	
	$session          	= Mage::getSingleton('checkout/session');
	$session->setNovalnetPassword($this->password);
	$paymentInfo      	= $this->getInfoInstance();

    $fieldsArr = array();
          $note  = $order->getCustomerNote();
          if ($note){
            $note = '<br />'.Mage::helper('novalnetpaypal')->__('Comment').': '.$note;
          }
          if ( !$this->getConfigData('live_mode') ){
            $note .= '<br /><b><font color="red">'.strtoupper(Mage::helper('novalnetpaypal')->__('Testorder')).'</font></b>';
          }
	$order->setComment($note);
	$order->setCustomerNote($note);
	$order->setCustomerNoteNotify(true);
    $fieldsArr['key']       = self::KEY;
    $fieldsArr['vendor']    = $this->getConfigData('merchant_id');
    $fieldsArr['auth_code'] = $this->encode($this->getConfigData('auth_code'),              $this->password);
    $fieldsArr['product']   = $this->encode($this->getConfigData('product_id'),             $this->password);
    $fieldsArr['tariff']    = $this->encode($this->getConfigData('tariff_id'),              $this->password);
    $fieldsArr['amount']    = $this->encode(( round($order->getBaseGrandTotal(), 2) * 100), $this->password);
    $fieldsArr['test_mode'] = $this->encode((!$this->getConfigData('live_mode'))? 1: 0,     $this->password);
    $fieldsArr['uniqid']    = $this->encode(uniqid(),                                       $this->password);

    $hParams['auth_code'] 	= $fieldsArr['auth_code'];
    $hParams['product_id']	= $fieldsArr['product'];
    $hParams['tariff']    	= $fieldsArr['tariff'];
    $hParams['amount']    	= $fieldsArr['amount'];
    $hParams['test_mode'] 	= $fieldsArr['test_mode'];
    $hParams['uniqid']    	= $fieldsArr['uniqid'];

    $fieldsArr['hash']       		= $this->hash($hParams, $this->password);
    $fieldsArr['currency']   		= $order->getOrderCurrencyCode();
    $fieldsArr['first_name'] 		= $billing->getFirstname();
    $fieldsArr['last_name']  		= $billing->getLastname();
    $fieldsArr['email']      		= $this->getOrder()->getCustomerEmail();
    $fieldsArr['street']     		= $billing->getStreet(1);
    $fieldsArr['search_in_street']  = 1;
    $fieldsArr['city']       		= $billing->getCity();
    $fieldsArr['zip']        		= $billing->getPostcode();
    $fieldsArr['country_code']      = $billing->getCountry();
    $fieldsArr['lang']              = substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);
    $fieldsArr['remote_ip']         = $this->getRealIpAddr();
    $fieldsArr['tel']               = $billing->getTelephone();
    $fieldsArr['fax']               = $billing->getFax();
    //$fieldsArr['birth_date']        = $order->getRemoteIp();
    $fieldsArr['session']           = session_id();
    $fieldsArr['return_url']        = Mage::getUrl('novalnetpaypal/novalnetpaypal/return', array('_novalnetpaypal' => true));
    $fieldsArr['return_method']       = 'POST';
    $fieldsArr['error_return_url']    = Mage::getUrl('novalnetpaypal/novalnetpaypal/error', array('_novalnetpaypal' => true));
    $fieldsArr['error_return_method'] = 'POST';
	$fieldsArr['order_no']            = $paymentInfo->getOrder()->getRealOrderId();
    $fieldsArr['input1']              = 'order_id';
    $fieldsArr['inputval1']           = $paymentInfo->getOrder()->getRealOrderId();
    $fieldsArr['user_variable_0'] 	  = str_replace(array('https://', 'http://'), array('',''), Mage::getBaseUrl());
	$fieldsArr['api_signature']		  = $this->encode($this->getConfigData('paypal_api_signature'), $this->password);
	$fieldsArr['api_user']			  =	$this->encode($this->getConfigData('paypal_api_username'), $this->password); 
	$fieldsArr['api_pw']			  = $this->encode($this->getConfigData('paypal_api_password'), $this->password);
	
    $request = '';
    foreach ($fieldsArr as $k => $v) {
      $request .= '<' . $k . '>' . $v . '</' . $k . '>';
    }
		//echo '<pre>';
		//print_r($fieldsArr); exit;
    return $fieldsArr;
  }

	/**
     * Get quote
     *
     * @return Mage_Sales_Model_Order
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
	
	/**
     * Get order model
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $paymentInfo = $this->getInfoInstance();
            $this->_order = Mage::getModel('sales/order')
                            ->loadByIncrementId($paymentInfo->getOrder()->getRealOrderId());
        }
        return $this->_order;
    }
      public function isPublicIP($value)
	{
        if(!$value || count(explode('.',$value))!=4)
        {
            return false;
        }
        return !preg_match('~^((0|10|172\.16|192\.168|169\.254|255|127\.0)\.)~', $value);
	}
	public function getRealIpAddr()
	{
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) and $this->isPublicIP($_SERVER['HTTP_X_FORWARDED_FOR']))
		{
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
		}
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) and $iplist=explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            if($this->isPublicIP($iplist[0])) return $iplist[0];
        }
        if (isset($_SERVER['HTTP_CLIENT_IP']) and $this->isPublicIP($_SERVER['HTTP_CLIENT_IP']))
		{
			return $_SERVER['HTTP_CLIENT_IP'];
		}
        if (isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) and $this->isPublicIP($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
		{
			return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
		}
        if (isset($_SERVER['HTTP_FORWARDED_FOR']) and $this->isPublicIP($_SERVER['HTTP_FORWARDED_FOR']) )
		{
			return $_SERVER['HTTP_FORWARDED_FOR'];
		}
		return $_SERVER['REMOTE_ADDR'];
	}

	private function debug2($object, $filename, $debug = false)
	{
		if (!$this->debug and !$debug){return;}
		$fh = fopen("/tmp/$filename", 'a+');
		if (gettype($object) == 'object' or gettype($object) == 'array'){
			fwrite($fh, serialize($object));
		}else{
			fwrite($fh, date('H:i:s').' '.$object);
		}
		fwrite($fh, "<hr />\n");
		fclose($fh);
	}
  function encode($data, $key)
  {
    $data = trim($data);
    if ($data == '') return'Error: no data';
    if (!function_exists('base64_decode') or !function_exists('pack') or !function_exists('crc32')){return'Error: func n/a';}

    try {
      $crc = sprintf('%u', crc32($data));# %u is a must for ccrc32 returns a signed value
      $data = $crc."|".$data;
      $data = bin2hex($data.$key);
      $data = strrev(base64_encode($data));
    }catch (Exception $e){
      echo('Error: '.$e);
    }
    return $data;
  }
  function decode($data, $key)
  {
    $data = trim($data);
    if ($data == '') {return'Error: no data';}
    if (!function_exists('base64_decode') or !function_exists('pack') or !function_exists('crc32')){return'Error: func n/a';}

    try {
      $data =  base64_decode(strrev($data));
      $data = pack("H".strlen($data), $data);
      $data = substr($data, 0, stripos($data, $key));
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
  function hash($h, $key)#$h contains encoded data
  {
    if (!$h) return'Error: no data';
    if (!function_exists('md5')){return'Error: func n/a';}
    #Mage::throwException(Mage::helper('novalnet')->__("$h[auth_code].$h[product_id].$h[tariff].$h[amount].$h[test_mode].$h[uniqid].strrev($key)").'!');
    return md5($h['auth_code'].$h['product_id'].$h['tariff'].$h['amount'].$h['test_mode'].$h['uniqid'].strrev($key));
  }
  function checkHash($request, $key)
  {
    if (!$request) return false; #'Error: no data';
    $h['auth_code']  = $request['auth_code'];#encoded
    $h['product_id'] = $request['product'];#encoded
    $h['tariff']     = $request['tariff'];#encoded
    $h['amount']     = $request['amount'];#encoded
    $h['test_mode']  = $request['test_mode'];#encoded
    $h['uniqid']     = $request['uniqid'];#encoded

    if ($request['hash2'] != $this->hash($h, $key)){
      return false;
    }
    return true;
  }
}