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
 * @copyright  Copyright (c) 2008-2010 Novalnet AG
 * @version    1.0.0
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Mage_Novalnet_Model_NovalnetInstantbanktransfer extends Mage_Payment_Model_Method_Abstract #Mage_Payment_Model_Method_Cc
{
	const CGI_URL                = 'https://payport.novalnet.de/online_transfer_payport';
  const PAYMENT_METHOD         = 'Instant Bank Transfer';
	const RESPONSE_DELIM_CHAR    = '&';
	const RESPONSE_CODE_APPROVED = 100;
  const KEY                    = 33;
  var   $password;

	private $_debug = false; #todo: set to false for live system
    /**
    * unique internal payment method identifier
    * 
    * @var string [a-z0-9_]
    */
    protected $_code          = 'novalnetInstantbanktransfer';#path = magento\app\code\community\Mage\Novalnet\Model\novalnetInstantbanktransfer.php
    protected $_formBlockType = 'novalnet/instantbanktransfer_form';#path = magento\app\design\frontend\default\default\template\novalnet\instantbanktransfer\form.phtml
    protected $_infoBlockType = 'novalnet/instantbanktransfer_info';

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
    protected $_canCapture              = false;

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
    protected $_canUseForMultishipping  = false;

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
      $order = $payment->getOrder();
      $note  = $order->getCustomerNote();
      if ($note){
        $note  = '<br />'.Mage::helper('novalnet')->__('Comment').': ';
        $note .= $order->getCustomerNote();
      }
      if ( !$this->getConfigData('live_mode') ){
        $note .= '<br /><b><font color="red">'.strtoupper(Mage::helper('novalnet')->__('Testorder')).'</font></b>';
      }
      $order->setComment($note);
      $order->setCustomerNote($note);
      $order->setCustomerNoteNotify(true);
      $order->save();
      #$this->debug2($order, $filename='magent_ibt.txt', true);

      $session = Mage::getSingleton('checkout/session');
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
   
  
    
    public function getBookingReference()
    {
        return $this->getConfigData('booking_reference');
    }
    
  
    public function getTitle()
    {
        return Mage::helper('novalnet')->__($this->getConfigData('title'));
    }
    
    public function getOrder()
    {
        if (!$this->_order) {
            $paymentInfo = $this->getInfoInstance();
            $this->_order = Mage::getModel('sales/order')
                            ->loadByIncrementId($paymentInfo->getOrder()->getRealOrderId());
        }
        return $this->_order;
    }
    
    public function getFormFields()
    {
        $this->password = $this->getConfigData('password');
        $_SESSION['mima'] = $this->password;
        $billing = $this->getOrder()->getBillingAddress();
        $payment = $this->getOrder()->getPayment();
        $fieldsArr = array();
        $session = Mage::getSingleton('checkout/session');
        $paymentInfo = $this->getInfoInstance();
        $order = $this->getOrder();

              $note  = $order->getCustomerNote();
              if ($note){
                $note = '<br />'.Mage::helper('novalnet')->__('Comment').': '.$note;
              }
              if ( !$this->getConfigData('live_mode') ){
                $note .= '<br /><b><font color="red">'.strtoupper(Mage::helper('novalnet')->__('Testorder')).'</font></b>';
              }
              $order->setComment($note);
              $order->setCustomerNote($note);
              $order->setCustomerNoteNotify(true);

        $fieldsArr['key']        = self::KEY;
        $fieldsArr['vendor']     = $this->getConfigData('merchant_id');
        $fieldsArr['auth_code']  = $this->encode($this->getConfigData('auth_code'),  $this->password);
        $fieldsArr['product']    = $this->encode($this->getConfigData('product_id'), $this->password);
        $fieldsArr['tariff']     = $this->encode($this->getConfigData('tariff_id'),  $this->password);
        $fieldsArr['amount']     = $this->encode(($order->getBaseGrandTotal()*100),  $this->password);
        #$fieldsArr['test_mode']  = $this->encode($this->getConfigData('test_mode'),  $this->password);
        $fieldsArr['test_mode']  = $this->encode((!$this->getConfigData('live_mode'))? 1: 0,  $this->password);
        $fieldsArr['uniqid']     = $this->encode(uniqid(),                           $this->password);

        $hParams['auth_code'] = $fieldsArr['auth_code'];
        $hParams['product_id']= $fieldsArr['product'];
        $hParams['tariff']    = $fieldsArr['tariff'];
        $hParams['amount']    = $fieldsArr['amount'];
        $hParams['test_mode'] = $fieldsArr['test_mode'];
        $hParams['uniqid']    = $fieldsArr['uniqid'];

        $fieldsArr['hash']       = $this->hash($hParams, $this->password);
        $fieldsArr['currency']   = $order->getOrderCurrencyCode();
        $fieldsArr['first_name'] = $billing->getFirstname();
        $fieldsArr['last_name']  = $billing->getLastname();
        $fieldsArr['email']      = $this->getOrder()->getCustomerEmail();
        $fieldsArr['street']     = $billing->getStreet(1);
        $fieldsArr['search_in_street']    = 1;
        $fieldsArr['city']                = $billing->getCity();
        $fieldsArr['zip']                 = $billing->getPostcode();
        $fieldsArr['country_code']        = $billing->getCountry();
        $fieldsArr['lang']                = $billing->getLang();
        $fieldsArr['remote_ip']           = $this->getRealIpAddr();
        $fieldsArr['tel']                 = $billing->getTelephone();
        $fieldsArr['fax']                 = $billing->getFax();
        $fieldsArr['birth_date']          = $order->getRemoteIp();
        $fieldsArr['session']             = session_id();
        $fieldsArr['return_url']          = Mage::getUrl('novalnet/instantbanktransfer/success', array('_instantbanktransfer' => true));
        $fieldsArr['return_method']       = 'POST';
        $fieldsArr['error_return_url']    = Mage::getUrl('novalnet/instantbanktransfer/success', array('_instantbanktransfer' => true));
        $fieldsArr['error_return_method'] = 'POST';
        $fieldsArr['input1']              = 'order_id';
        $fieldsArr['inputval1']           = $paymentInfo->getOrder()->getRealOrderId();
        $fieldsArr['user_variable_0']     = str_replace(array('http://', 'www.'), array('', ''), $_SERVER['SERVER_NAME']);

      #on Clicking onto <Weiter> after choice of payment type
        /*
        payment[method]=novalnetInstantbanktransfer
        payment[cc_type]=VI
        payment[cc_owner]=Zhang
        payment[cc_number]=4200000000000000
        payment[cc_exp_month]=1
        payment[cc_exp_year]=2012
        payment[cc_cid]=123
      */
      #$fieldsArr['payment[method]'] = 'novalnetInstantbanktransfer';

      ############## INSTANT BANK Transfer specific parameters
      $fieldsArr['user_variable_0'] = str_replace('http://', '', Mage::getBaseUrl());

      $request = '';
      foreach ($fieldsArr as $k => $v) {
          $request .= '<' . $k . '>' . $v . '</' . $k . '>';
      }
      return $fieldsArr;
    }

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('novalnet/instantbanktransfer/redirect', array('_secure' => true));#path: magento\app\code\community\Mage\Novalnet\Block\Instantbanktransfer\redirect.php
        #Mage::log("getOrderPlaceRedirectUrl called");
    }
    
    public function getNovalnetInstantbanktransferUrl()
    {
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

	public function assignData($data)#this mehtode will be called twice: once after choice of payment, once after klicking on <Place Order>
	{
		return $this;
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
  /*order of func
    19:30:01 redirectAction<hr />controller
    19:30:03 getFormFields<hr />
    19:30:47 successAction<hr />controller
    19:30:47 _checkReturnedPost<hr />controller
    19:30:48 capture<hr />
  */
}