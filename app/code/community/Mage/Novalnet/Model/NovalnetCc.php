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


class Mage_Novalnet_Model_NovalnetCc extends Mage_Payment_Model_Method_Cc
{
  const CGI_URL = 'https://payport.novalnet.de/paygate.jsp';
  const PAYMENT_METHOD = 'Credit Card';
  const RESPONSE_DELIM_CHAR = '&';
  const RESPONSE_CODE_APPROVED = 100;
  var   $_debug = false;
    /**
    * unique internal payment method identifier
    * 
    * @var string [a-z0-9_]
    */
    protected $_code = 'novalnetCc';
    protected $_formBlockType = 'novalnet/cc_form';
	protected $_infoBlockType = 'novalnet/cc_info';

  
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
    protected $_canVoid                 = true;

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

      $order = $payment->getOrder();
      $note  = $order->getCustomerNote();
      if ($note){
        $note = '<br />'.Mage::helper('novalnet')->__('Comment').': '.$note;
      }
      if ( !$this->getConfigData('live_mode') ){
        $note .= '<br /><b><font color="red">'.strtoupper(Mage::helper('novalnet')->__('Testorder')).'</font></b>';
      }
      $order->setCustomerNote($note);
      $order->setCustomerNoteNotify(true);

    	$request = $this->_buildRequest($payment);
    	$result = $this->_postRequest($request);
    	
        if ($result->getStatus() == self::RESPONSE_CODE_APPROVED) {
            $payment->setStatus(self::STATUS_APPROVED);
            $payment->setCcTransId($result->getTid());
            $payment->setLastTransId($result->getTid());
        }
        else {
            if ($result->getStatusDesc()) {
                $error = Mage::helper('novalnet')->htmlEscape($result->getStatusDesc());
            }else {
                $error = Mage::helper('paygate')->__('Error in capturing the payment');
            }
        }

        if ($error !== false) {
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
   
    protected function _buildRequest(Varien_Object $payment)
    {
        $order = $payment->getOrder();

 		$request = Mage::getModel('novalnet/novalnet_request');

        $request->setvendor($this->getConfigData('merchant_id'))
            ->setauth_code($this->getConfigData('auth_code'))
            ->setkey('6')
            ->setproduct($this->getConfigData('product_id'))
            ->settariff($this->getConfigData('tariff_id'))
            ->settest_mode((!$this->getConfigData('live_mode'))? 1: 0);

        if($payment->getAmount()){
            $request->setamount($payment->getAmount()*100);
        }

        if (!empty($order)) {
            $request->setinput1($order->getIncrementId());

            $billing = $order->getBillingAddress();
            /*$street = preg_split("/(\d)/",$billing->getStreet(1),2,PREG_SPLIT_DELIM_CAPTURE);
            if (!isset($street[1])){$street[1]='';}
            if (!isset($street[2])){$street[2]='';}
            if (!$street[0]){$street[0] = $street[1].$street[2];}
            if (!$street[0])
            {
                Mage::throwException(Mage::helper('novalnet')->__('Street missing'));
            }*/
            if (!$billing->getStreet(1)){Mage::throwException(Mage::helper('novalnet')->__('Street missing'));}
            if (!empty($billing)) {
                $request->setfirst_name($billing->getFirstname())
                    ->setlast_name($billing->getLastname())
                    ->setsearch_in_street(1)
                    ->setstreet($billing->getStreet(1))
                    ->setcity($billing->getCity())
                    ->setzip($billing->getPostcode())
                    ->setcountry($billing->getCountry())
                    ->settel($billing->getTelephone())
                    ->setfax($billing->getFax())
                    ->setremote_ip($this->getRealIpAddr())
                    ->setgender('u')
                    ->setemail($order->getCustomerEmail());
            }#->setremote_ip($order->getRemoteIp())
             #->setstreet($street[0])
             #->sethouse_no($street[1].$street[2])
        }

                if($payment->getCcNumber()){
                    $request->setcc_no($payment->getCcNumber())
                        ->setcc_exp_month($payment->getCcExpMonth())
                        ->setcc_exp_year($payment->getCcExpYear())
                        ->setcc_cvc2($payment->getCcCid());
                    if ($payment->getCcOwner()!=""){
                    	$request->setcc_holder($payment->getCcOwner());
                    }
                }
 

        return $request;
    }
    
    public function getBookingReference()
    {
        return $this->getConfigData('booking_reference');
    }
    
    protected function _postRequest(Varien_Object $request)
    {
        $result = Mage::getModel('novalnet/novalnet_result');

        $client = new Varien_Http_Client();

        $uri = $this->getConfigData('cgi_url');
        $client->setUri($uri ? $uri : self::CGI_URL);
        $client->setConfig(array(
            'maxredirects'=>0,
            'timeout'=>30,
            //'ssltransport' => 'tcp',
        ));
        $request->toLatin1();
        $client->setParameterPost($request->getData());
        $client->setMethod(Zend_Http_Client::POST);
        try {
            $response = $client->request();
        } catch (Exception $e) {
            $result->setResponseCode(-1)
                ->setResponseReasonCode($e->getCode())
                ->setResponseReasonText($e->getMessage());
            Mage::throwException(
                Mage::helper('paygate')->__('Gateway request error: %s', $e->getMessage())
            );
        }

        $responseBody = $response->getBody();

        $r = explode(self::RESPONSE_DELIM_CHAR, $responseBody);

        $responseBody = $response->getBody();
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
          /*if ( $this->getConfigData('live_mode') == 0 ){
                $result->setTestmode(strtoupper(Mage::helper('paygate')->__('Testorder')));
    	    }*/

        } else {
             Mage::throwException(Mage::helper('paygate')->__('Error in payment gateway'));
        }
        $result->toUtf8();
        return $result;
    }
    public function getTitle()
    {
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
  public function debug2($object, $filename, $debug)
	{
		if (!$this->_debug and !$debug){return;}
		$fh = fopen("/tmp/$filename", 'a+');
		if (gettype($object) == 'object' or gettype($object) == 'array'){
			fwrite($fh, serialize($object));
		}else{
			fwrite($fh, date('Y-m-d H:i:s').' '.$object);
		}
		fwrite($fh, "<hr />\n");
		fclose($fh);
	}
}