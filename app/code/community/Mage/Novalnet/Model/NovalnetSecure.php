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


class Mage_Novalnet_Model_NovalnetSecure extends Mage_Payment_Model_Method_Cc
{
	const CGI_URL = 'https://payport.novalnet.de/global_pci_payport';
	const RESPONSE_DELIM_CHAR = '&';
	const RESPONSE_CODE_APPROVED = 100;
    /**
    * unique internal payment method identifier
    * 
    * @var string [a-z0-9_]
    */
    protected $_code = 'novalnet_secure';
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
		if ($order->getCustomerNote())
		{
			#$note  = '<br />';
			$note  = Mage::helper('novalnet')->__('Comment').': ';
			$note .= $order->getCustomerNote();
			$order->setCustomerNote($note);
			$order->setCustomerNoteNotify(true);
		}

        $session = Mage::getSingleton('checkout/session');
        $session->setCcNumber(Mage::helper('core')->encrypt($payment->getCcNumber()));
        $session->setCcCid(Mage::helper('core')->encrypt($payment->getCcCid()));
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
        $billing = $this->getOrder()->getBillingAddress();
        $payment = $this->getOrder()->getPayment();
        $fieldsArr = array();
        $session = Mage::getSingleton('checkout/session');
        $paymentInfo = $this->getInfoInstance();
        $order = $this->getOrder();
        $fieldsArr['vendor'] = $this->getConfigData('merchant_id');
        $fieldsArr['auth_code'] = $this->getConfigData('auth_code');
        $fieldsArr['key'] = 6;
        $fieldsArr['product'] = $this->getConfigData('product_id');
        $fieldsArr['tariff'] = $this->getConfigData('tariff_id');
        $fieldsArr['amount'] = ($order->getBaseGrandTotal()*100);
        
        $fieldsArr['currency'] = $order->getOrderCurrencyCode();
        $fieldsArr['first_name'] = $billing->getFirstname();
        $fieldsArr['last_name'] = $billing->getLastname();
        $fieldsArr['email'] = $this->getOrder()->getCustomerEmail();
        $fieldsArr['street'] = $billing->getStreet(1);
        $fieldsArr['search_in_street'] = 1;
        $fieldsArr['city'] = $billing->getCity();
        $fieldsArr['zip'] = $billing->getPostcode();
        $fieldsArr['country_code'] = $billing->getCountry();
        $fieldsArr['lang'] = $billing->getLang();
        #$fieldsArr['remote_ip'] = $order->getRemoteIp();
		$fieldsArr['remote_ip'] = $this->getRealIpAddr();
        $fieldsArr['tel'] = $billing->getTelephone();
        $fieldsArr['fax'] = $billing->getFax();
        $fieldsArr['birth_date'] = $order->getRemoteIp();
        $fieldsArr['session'] = session_id();
        $fieldsArr['cc_holder'] = $payment->getCcOwner();
        $fieldsArr['cc_no'] = Mage::helper('core')->decrypt($session->getCcNumber()) ;
        $fieldsArr['cc_exp_month'] = $payment->getCcExpMonth();
        $fieldsArr['cc_exp_year'] = $payment->getCcExpYear();
        $fieldsArr['cc_cvc2'] = Mage::helper('core')->decrypt($session->getCcCid());
        $fieldsArr['return_url'] = Mage::getUrl('novalnet/secure/success', array('_secure' => true));
        $fieldsArr['return_method'] = 'POST';
        $fieldsArr['error_return_url'] = Mage::getUrl('novalnet/secure/success', array('_secure' => true));;
        $fieldsArr['error_return_method'] = 'POST';
        $fieldsArr['input1']= 'order_id';
        $fieldsArr['inputval1'] = $paymentInfo->getOrder()->getRealOrderId();
        $session->setCcNumber('');
        $session->setCcCid();
        $request = '';
        foreach ($fieldsArr as $k=>$v) {
            $request .= '<' . $k . '>' . $v . '</' . $k . '>';
        }
        return $fieldsArr;
    }
    
    public function getOrderPlaceRedirectUrl()
    {
          return Mage::getUrl('novalnet/secure/redirect');
    }
    
    public function getNovalnetSecureUrl()
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
}