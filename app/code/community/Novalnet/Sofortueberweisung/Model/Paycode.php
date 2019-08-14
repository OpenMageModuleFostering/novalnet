<?php
class Novalnet_Sofortueberweisung_Model_Paycode extends Mage_Payment_Model_Method_Abstract
{
  /**
  * Availability options
  */
  protected $_code = 'paycode';   
  protected $_paymentMethod = 'paycode';

  protected $_formBlockType = 'sofortueberweisung/form_paycode';
  protected $_infoBlockType = 'sofortueberweisung/info_paycode';

  const STATUS_UNKNOWN    = 'UNKNOWN';
  const STATUS_APPROVED   = 'APPROVED';
  const STATUS_ERROR      = 'ERROR';
  const STATUS_DECLINED   = 'DECLINED';
  const STATUS_VOID       = 'VOID';
  const STATUS_SUCCESS    = 'SUCCESS';

  protected $_isGateway               = false;
  protected $_canAuthorize            = true;
  protected $_canCapture              = false;
  protected $_canCapturePartial       = false;
  protected $_canRefund               = false;
  protected $_canVoid                 = false;
  protected $_canUseInternal          = false;
  protected $_canUseCheckout          = true;
  protected $_canUseForMultishipping  = true;	
  protected $_supportedLocales        = array('en', 'de', 'fr');

  public function _construct()
  {
      parent::_construct();
      $this->_init('sofortueberweisung/paycode');
  }

  public function getUrl(){
    return $this->getConfigData('url');
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
	
	public function getSecurityKey(){
		return uniqid(rand(), true);
	}
	
	public function assignData($data)
    {
       	if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
        $info->setSuAccountNumber($data->getSuAccountNumber())
				->setSuBankCode($data->getSuBankCode())
				->setSuPaycode($data->getSuPaycode())
				->setSuIban($data->getSuIban())
				->setSuBic($data->getSuBic())
				->setSuHolder($data->getSuHolder());

        return $this;
    }
	
	public function validate()
    {
        parent::validate();
		
		if (!$this->getQuote()->getPayment()->getSuHolder()) {
            Mage::throwException(Mage::helper('sofortueberweisung')->__('Please fill out the account holder'));
        }
		if (!$this->getQuote()->getPayment()->getSuBankCode()) {
            Mage::throwException(Mage::helper('sofortueberweisung')->__('Please fill out the account number'));
        }
		if (!$this->getQuote()->getPayment()->getSuBankCode()) {
            Mage::throwException(Mage::helper('sofortueberweisung')->__('Please fill out the bank code'));
        }
        return $this;
    }
	
	/**
	* Send authorize request to gateway
	*
	* @param   Varien_Object $payment
	* @param   decimal $amount
	* @return  Mage_Paygate_Model_Authorizenet
	*/
    public function authorize(Varien_Object $payment, $amount)
    {/*
		$payment->setAmount($this->getQuote()->getGrandTotal());
		$error = false;	
		$security 	= $this->getSecurityKey();
		$payment->setSuSecurity($security);
		
		if($payment->getAmount()){			
			
			$amount		= number_format($this->getQuote()->getGrandTotal(),2,'.','');
			$billing	= $this->getQuote()->getBillingAddress();
						
			$locale = explode('_', Mage::app()->getLocale()->getLocaleCode());			
			if (is_array($locale) && !empty($locale) && in_array($locale[0], $this->_supportedLocales))
				$locale = strtoupper($locale[0]);
			else
				$locale = strtoupper($this->getDefaultLocale());
			
            $params = Array(
				'user_id'				=> $this->getConfigData('customer'),
	    		'project_id' 			=> $this->getConfigData('project'),
				'amount' 				=> $amount,				
				'reason_1' 				=> Mage::helper('sofortueberweisung')->__('Order: ').$this->getQuote()->getReservedOrderId(),
				'reason_2' 				=> '',
				'sender_holder' 		=> $payment->getSuHolder(),
				'sender_account_number' => $payment->getSuAccountNumber(),
				'sender_bank_code' 		=> $payment->getSuBankCode(),
				'sender_bank_bic' 		=> $payment->getSuBic(),
				'sender_iban' 			=> $payment->getSuIban(),
				'sender_country_id'  	=> $billing->getCountry(),			
				'user_variable_0' 		=> $this->getQuote()->getReservedOrderId(),
				'user_variable_1' 		=> $payment->getSuSecurity(),
				'user_variable_2' 		=> '',
				'user_variable_3' 		=> '',
				'user_variable_4' 		=> '',
				'user_variable_5' 		=> '',
				'expires' 				=> $this->getConfigData('expires'),
				'language_id'			=> $locale,				
			);
            
			if($this->getConfigData('check_input_yesno') == 1)
				$params['hash'] = md5(implode('|',$params).'|'.$this->getConfigData('project_pswd'));
			
			$result = $this->_postRequest($params);

			if(strstr($result,'Errors') === false){
				$payment->setSuPaycode($result);
				$payment->setStatus(self::STATUS_APPROVED);
			}else{
				$error = Mage::helper('sofortueberweisung')->__('Please check your account data.');
			}
        }else{
            $error = Mage::helper('sofortueberweisung')->__('Invalid amount for authorization.');
        }

        if ($error !== false) {
            Mage::throwException($error);
        }*/

        return $this;
    }

	protected function _postRequest($request)
    {
        $client = new Varien_Http_Client();	

        $client->setUri($this->getUrl());
        $client->setConfig(array(
            'maxredirects'=>2,
            'timeout'=>60,
        ));

		$client->setParameterGet($request);
        $client->setMethod(Zend_Http_Client::GET);
        try {
			$response = $client->request();
        } catch (Exception $e) {
            Mage::throwException(
                Mage::helper('sofortueberweisung')->__('Gateway request error: %s', $e->getMessage())
            );
        }

		$responseBody = $response->getBody();

        return $responseBody;
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
	
}