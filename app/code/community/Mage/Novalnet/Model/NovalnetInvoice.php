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
    const CGI_URL = 'https://payport.novalnet.de/paygate.jsp';
    const PAYMENT_METHOD = 'Invoice';
    const RESPONSE_DELIM_CHAR = '&';
    const RESPONSE_CODE_APPROVED = 100;
    const KEY = 27;
	const STATUS_PENDING = 'PENDING';
    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */
    protected $_code = 'novalnetInvoice';
    protected $_formBlockType = 'novalnet/invoice_form';
    protected $_infoBlockType = 'novalnet/invoice_info';
    protected $due_date = '';
	private $debug = true;#todo: set to fals for live system
     
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
        $request = $this->_buildRequest($payment);
        $result = $this->_postRequest($request,$payment);

        if ($result->getStatus() == self::RESPONSE_CODE_APPROVED) {
            #$payment->setStatus(self::STATUS_APPROVED);
			$payment->setStatus(self::STATUS_PENDING);
            $payment->setCcTransId($result->getTid());
            $payment->setLastTransId($result->getTid());
            $payment->setNnAccountNumber(substr($payment->getNnAccountNumber(),0,-4)."XXXX");
            $payment->setNnBankSortingCode(substr($payment->getNnBankSortingCode(),0,-3)."XXX");
            $id = $payment->getNnId();
            $quote_payment = $this->getQuote()->getPaymentById($id);
			if ($quote_payment)#to avoid error msg. in admin interface
			{
				$quote_payment->setNnAccountNumber(substr($payment->getNnAccountNumber(),0,-4)."XXXX");
				$quote_payment->setNnBankSortingCode(substr($payment->getNnBankSortingCode(),0,-3)."XXX");
				$quote_payment->save();
			}
        }
        else {
            if ($result->getStatusDesc()) {
                $error = Mage::helper('novalnet')->htmlEscape($result->getStatusDesc());
            }else {
                $error = Mage::helper('novalnet')->__('Error in capturing the payment');
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
        $due_date_string = $this->getDuedateParam();
        $request = Mage::getModel('novalnet/novalnet_request');


        $request->setvendor($this->getConfigData('merchant_id'))
        ->setauth_code($this->getConfigData('auth_code'))
        ->setproduct($this->getConfigData('product_id'))
        ->settariff($this->getConfigData('tariff_id'));

        $request->setcurrency($order->getOrderCurrency());

        if($payment->getAmount()){
            #$request->setamount($payment->getAmount()*100);
            $request->setamount($this->getAmount4Request($payment->getAmount()));
        }

        if (!empty($order)) {
            $request->setinput1($order->getIncrementId());
            $billing = $order->getBillingAddress();
            if (!$billing->getStreet(1)){Mage::throwException(Mage::helper('novalnet')->__('Street missing'));}
            if (!empty($billing)) {
                $request->setfirst_name($billing->getFirstname())
                ->setLast_name($billing->getlastname())
                ->setsearch_in_street(1)
                ->setstreet($billing->getStreet(1))
                ->setcity($billing->getCity())
                ->setzip($billing->getPostcode())
                ->setcountry($billing->getCountry())
                ->settel($billing->getTelephone())
                ->setfax($billing->getFax())
                ->setremote_ip($this->getRealIpAddr())
                ->setgender('u')
                ->setemail($order->getCustomerEmail())
                ->setsearch_in_street(1);
                #->setremote_ip($order->getRemoteIp())
                #->sethouse_no($street[1].$street[2])
                #->setstreet($street[0].$street[1].$street[2])
            }
        }
        /*$request->setbank_account_holder($payment->getNnAccountHolder())
        ->setbank_account($payment->getNnAccountNumber())
        ->setbank_code($payment->getNnBankSortingCode())*/
        $request->setkey(self::KEY);
        $request->setinvoice_type(self::PAYMENT_METHOD.$due_date_string);
        return $request;
    }

    protected function _postRequest(Varien_Object $request, Varien_Object $payment)
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
            Mage::helper('novalnet')->__('Gateway request error: %s', $e->getMessage())
            );
        }

        $responseBody = $response->getBody();

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
             
        } else {
            Mage::throwException(
            Mage::helper('novalnet')->__('Error in payment gateway')
            );
        }
        $result->toUtf8();
        $note = $this->getNote($aryResponse);
        $order = $payment->getOrder();
		if ($order->getCustomerNote())
		{
			$note .= '<br /><br />';
			$note .= Mage::helper('novalnet')->__('Comment').': ';
			$note .= $order->getCustomerNote();
		}
        $order->setCustomerNote($note);
        $order->setCustomerNoteNotify(true);
        #$fh = fopen('/temp/magento.txt', 'w');fwrite($fh, $note);
        #Mage::throwException($order->getEmailCustomerNote());#todo:
        return $result;
    }


    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info=$this->getInfoInstance();
        $info->setNnElvCountry($data->getElvCountry())
        ->setNnAccountHolder($data->getAccountHolder())
        ->setNnAccountNumber($data->getAccountNumber())
        ->setNnBankSortingCode($data->getBankSortingCode());
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
   
    public function getTitle()
    {
        return Mage::helper('novalnet')->__($this->getConfigData('title'));
    }
    
   public function validate()
   {
         parent::validate();
         $info = $this->getInfoInstance();
         $nnAccountNumber = $info->getNnAccountNumber();
         $nnBankSortingCode = $info->getNnBankSortingCode();
         $nnAccountNumber = preg_replace('/[\-\s]+/', '', $nnAccountNumber);
         $info->setNnAccountNumber($nnAccountNumber);
         $nnBankSortingCode = preg_replace('/[\-\s]+/', '', $nnBankSortingCode);
         $info->setNnBankSortingCode($nnBankSortingCode);
         if (preg_match("/\D/",$nnAccountNumber)){
             Mage::throwException(Mage::helper('novalnet')->__('This is not a valid account number.'));
         }
         if (preg_match("/\D/",$nnBankSortingCode)){
             Mage::throwException(Mage::helper('novalnet')->__('This is not a valid bank sorting code.'));
         }
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
    public function getAmount4Request($amount)
    {
        if(preg_match('/[,.]$/', $amount))
        {
          $amount = $amount . '00';
        }
        else if(preg_match('/[,.][0-9]$/', $amount))
        {
          $amount = $amount . '0';
        }
        $orig_amount = $amount;

        $amount = str_replace(array('.', ','), array('',''), $amount);
        return$amount;
    }
    public function getDuedateParam()
    {
        $payment_duration = $this->getConfigData('payment_duration');
        $due_date = '';
        $due_date_string = '';
        if($payment_duration)
        {
            $due_date = date("d.m.Y",mktime(0,0,0,date("m"),date("d")+$payment_duration,date("Y")));
            $due_date_string = '&due_date='.date("Y-m-d",mktime(0,0,0,date("m"),date("d")+$payment_duration,date("Y")));
        }
        
        if($due_date)
        {
            #return Mage::app()->getTranslator()->translate(func_get_args());#todo:
            $this->due_date = $due_date;
        }
        /*else
        {
            $order->info['comments'] = '<BR><B>'.MODULE_PAYMENT_NOVALNET_INVOICE_TEXT_TRANSFER_INFO.'</B><BR><BR>';
        }*/
          return$due_date_string;
    }
    public function getNote($aryResponse)
    {
        #todo: Kontoinhaber fehlt
		$note = Mage::helper('novalnet')->__('Please transfer the amount at the latest, untill').' '.$this->due_date.' '.Mage::helper('novalnet')->__('to following account').":<br /><br />\n\n";

		$note.= Mage::helper('novalnet')->__('Account Holder2').": NOVALNET AG<br />\n";
		$note.= Mage::helper('novalnet')->__('Account Number').": ".$aryResponse['invoice_account']."<br />\n";
		$note.= Mage::helper('novalnet')->__('Bank Sorting Code').": ".$aryResponse['invoice_bankcode']."<br />\n";
		$note.= Mage::helper('novalnet')->__('Bank').": ".$aryResponse['invoice_bankname'].', Muenchen<br /><br />'."\n\n"; #.$aryResponse['invoice_bankplace']."\n\n";

		$note.= "IBAN: ".$aryResponse['invoice_iban']."<br />\n";
		$note.= "SWIFT / BIC: ".$aryResponse['invoice_bic']."<br /><br />\n\n";

		$note.= Mage::helper('novalnet')->__('Amount').": ".str_replace('.', ',', $aryResponse['amount'])." EUR<br />\n";
		$note.= Mage::helper('novalnet')->__('Reference').": TID ".$aryResponse['tid']."<br />\n";
		$note.= Mage::helper('novalnet')->__('Please note that the Transfer can only be identified with the above mentioned Reference').'.';

		/*
		aryResponce:
		status=>100
		nc_no=>2200224420635320
		tid=>11934900002719418
		memburl=>http://magento.gsoftpro.de/
		login=>
		password=>
		end_date=>
		amount=>1204.97 => 1204,97
		invoice_account=>660983147
		invoice_bankcode=>70020270
		invoice_iban=>DE55700202700660983147
		invoice_bic=>HYVEDEMMXXX
		invoice_bankname=>Hypovereinsbank
		invoice_bankplace=>M¨¹nchen
		*/
		return$note;
    }

	private function debug2($object, $filename)
	{
		if (!$this->debug){return;}
		$fh = fopen("/tmp/$filename", 'a+');
		if (gettype($object) == 'object' or gettype($object) == 'array'){
			fwrite($fh, serialize($object));
		}else{
			fwrite($fh, date('H:i:s').' '.$object);
		}
		fwrite($fh, "<hr />\n");
		fclose($fh);
	}
}
