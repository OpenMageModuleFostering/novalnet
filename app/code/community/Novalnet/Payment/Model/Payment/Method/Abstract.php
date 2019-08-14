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
 * Part of the Paymentmodule of Novalnet AG
 * https://www.novalnet.de
 * If you have found this script usefull a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Novalnet AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Model_Payment_Method_Abstract extends Mage_Payment_Model_Method_Abstract {

    protected $_isGateway = false;
    protected $_canAuthorize = true;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_canSaveCc = false;
    protected $_isInitializeNeeded = false;
    protected $_mustTransimitInvoicingData = false;
    protected $_mustTransimitInvoicingItemTypes = false;
    protected $_canCancelInvoice = false;
    protected $methodType = '';
    protected $redirectUrl = '';

    var $infoInstance;
	var $_helper;
	var $_dataHelper;

    /**
     * Load Basic Params in constructor
     *
     * @return 
     */
    public function __construct() {
        //Novalnet Basic parameters
		$this->assignUtilities();
        $this->_vendorId = $this->_getConfigData('merchant_id', true);
        $this->_authcode = $this->_getConfigData('auth_code', true);
        $this->_productId = $this->_getConfigData('product_id', true);
        $this->_tariffId = $this->_getConfigData('tariff_id', true);
    }

    /**
     * Check whether payment method can be used
     *
     * @param Mage_Sales_Model_Quote
     * @return bool
     */
    public function isAvailable($quote = null) {
        $getNnDisableTime = "getNnDisableTime" . ucfirst($this->_code); //Dynamic Getter based on payment methods
        $minOrderCount = $this->_getConfigData('orderscount');
        $userGroupId = $this->_getConfigData('user_group_excluded');
        if ($this->_helper->checkOrdersCount($minOrderCount)) {
            return false;
        } else if (!$this->_helper->checkCustomerAccess($userGroupId)) {
            return false;
        } else if (!empty($quote) && !$this->_helper->isModuleActive($quote->getGrandTotal())) {
            return false;
        } else if (time() < $this->_getMethodSession()->$getNnDisableTime()) {
            return false;
        }
        return parent::isAvailable($quote);
    }

    /**
     * Assign Form Data in quote instance
     *
     * @return  Mage_Payment_Model_Abstract Object
     */
    public function assignData($data) {
        $infoInstance = $this->_getInfoInstance();
        $this->_dataHelper->assignNovalnetData($this->_code, $data, $infoInstance);
        return $this;
    }

    /**
     * Validate payment method information object
     *
     * @return  Mage_Payment_Model_Abstract
     */
    public function validate() {
        parent::validate();
        $infoInstance = $this->_getInfoInstance();
        if (!$this->_isPlaceOrder()) {

            $this->validateNovalnetParams();
            //customer_no verification
            $this->_helper->customerNumberValidation();
            $this->_dataHelper->validateNovalnetData($this->_code, $infoInstance);

            //telephone payment data is set
            if ($this->_code != Novalnet_Payment_Model_Config::NN_TELEPHONE &&
                    $this->_getMethodSession(Novalnet_Payment_Model_Config::NN_TELEPHONE)->getNnPhoneTid()) {
                $this->_unsetMethodSession(Novalnet_Payment_Model_Config::NN_TELEPHONE);
            }
        }
        $this->doNovalnetPhoneFirstCall();
		$this->_initiateCallbackProcess($this->_code);
        return $this;
    }

    /**
     * Authorize
     *
     * @param   Varien_Object $orderPayment
     * @param float $amount
     * @return  Mage_Payment_Model_Abstract
     */
    public function authorize(Varien_Object $payment, $amount) {
		$methodSession = $this->_getMethodSession();
        $redirectPayment = array(
            Novalnet_Payment_Model_Config::NN_IDEAL,
            Novalnet_Payment_Model_Config::NN_PAYPAL,
            Novalnet_Payment_Model_Config::NN_SOFORT,
            Novalnet_Payment_Model_Config::NN_CC3D
        );
		if ($this->canAuthorize() && $this->_code && !in_array($this->_code, $redirectPayment)) {
			switch ($this->_code) {
				case Novalnet_Payment_Model_Config::NN_TELEPHONE:
					$requestData = new Varien_Object();
					$option = '<lang>' . strtoupper($this->_helper->getDefaultLanguage()) . '</lang>';
					$result = $this->doNovalnetStatusCall($methodSession->getNnPhoneTid(),NULL, Novalnet_Payment_Model_Config::NOVALTEL_STATUS, $option, $requestData);
					//$result = $result['result'];
					if ($result) {
						$result->setTid($this->_getMethodSession()->getNnPhoneTid());
						$result->setStatus($result->getNovaltelStatus());
						$result->setStatusDesc($result->getNovaltelStatusMessage());

						//For Manual Testing
						//$result->setStatus(100);

						$txnId = $methodSession->getNnPhoneTid();
						/** @@ Update the transaction status and transaction overview **/
						$this->logNovalnetTransactionData($requestData, $result, $txnId);
						$this->_validateSecondCallResponse($result, $payment);
					}
					break;
				default:
					if($this->isCallbackTypeCall()) {
						$result = $methodSession->getNnResponseData();
					} else {
						$storeId = $this->_helper->getMagentoStoreId();
						$request = $this->buildRequest(Novalnet_Payment_Model_Config::POST_NORMAL, $storeId);
						$result  = $this->_postRequest($request);
					}
					$this->_validateNovalnetResponse($result, $payment, $request);
					break;
			}
		}
        return $this;
    }

    /**
     * Capture
     *
     * @param   Varien_Object $orderPayment
     * @param float $amount
     * @return  Mage_Payment_Model_Abstract
     */
    public function capture(Varien_Object $payment, $amount) {
        $redirectPayment = array(
            Novalnet_Payment_Model_Config::NN_IDEAL,
            Novalnet_Payment_Model_Config::NN_PAYPAL,
            Novalnet_Payment_Model_Config::NN_SOFORT,
            Novalnet_Payment_Model_Config::NN_TELEPHONE,
        );

        $getTid = $payment->getTransactionId();
        $responseCodeApproved = Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED;
        if ($this->canCapture() && $this->_code && !in_array($this->_code, $redirectPayment) && !is_null($getTid)) {

            $request = new Varien_Object();
            $storeId = $payment->getOrder()->getStoreId();
            $customerId = $payment->getOrder()->getCustomerId();
            $amount = $this->_helper->getFormatedAmount($payment->getOrder()->getBaseGrandTotal());
            $lastTranId = $this->_helper->makeValidNumber($payment->getLastTransId());
            //$getNovalnetParam = $payment->getMethodInstance();
            $this->_assignNnAuthData($request, $storeId);
            $request->setTid($lastTranId)
                    ->setStatus($responseCodeApproved)
                    ->setEditStatus(true);

            $loadTransStatus = $this->_helper->loadTransactionStatus($lastTranId);
            $transStatus = $loadTransStatus->getTransactionStatus();
            if (!in_array(NULL, $request->toArray()) && !empty($transStatus) && $transStatus != $responseCodeApproved) {

                $buildNovalnetParam = http_build_query($request->getData());
                $response = $this->_dataHelper->setRawCallRequest($buildNovalnetParam, Novalnet_Payment_Model_Config::PAYPORT_URL);
                if ($response->getStatus() == $responseCodeApproved) {
                    $loadTransStatus->setTransactionStatus($responseCodeApproved)
									->save();
                } else {
                    $this->showException('Error in you capture request');
                }
                $this->logNovalnetTransactionData($request, $response, $lastTranId, $customerId, $storeId);
            }
        }
        return $this;
    }

    /**
     * Prepare request to gateway
     *
     * @param string $type
     * @return Mage_Novalnet_Model_Novalnet_Request
     */
    public function buildRequest($type = Novalnet_Payment_Model_Config::POST_NORMAL, $storeId = NULL) {
        $payCode = ucfirst($this->_code);
        $helper = $this->_helper;
        $callbackTelNo = "getNnCallbackTel" . $payCode;
        $callbackEmail = "getNnCallbackEmail" . $payCode;

        if ($type == Novalnet_Payment_Model_Config::POST_NORMAL
                || $type == Novalnet_Payment_Model_Config::POST_CALLBACK) {
            $request = new Varien_Object();
            $amount = $helper->getFormatedAmount($this->_getAmount());
            $billing = $this->_getInfoObject()->getBillingAddress();
            $this->_assignNnAuthData($request, $storeId);
            $request->setTestMode((!$this->_getConfigData('live_mode')) ? 1 : 0)
                    ->setAmount($amount)
                    ->setCurrency($this->_getInfoObject()->getBaseCurrencyCode())
                    ->setCustomerNo($helper->getCustomerId())
                    ->setUseUtf8(1)
                    ->setfirstName($billing->getFirstname())
                    ->setLastName($billing->getLastname())
                    ->setSearchInStreet(1)
                    ->setStreet(implode(',', $billing->getStreet()))
                    ->setCity($billing->getCity())
                    ->setZip($billing->getPostcode())
                    ->setCountry($billing->getCountry())
                    ->setLanguage(strtoupper($helper->getDefaultLanguage()))
                    ->setLang(strtoupper($helper->getDefaultLanguage()))
                    ->setTel($billing->getTelephone())
                    ->setFax($billing->getFax())
                    ->setRemoteIp($helper->getRealIpAddr())
                    ->setGender('u')
                    ->setEmail($this->_getInfoObject()->getCustomerEmail())
                    ->setOrderNo($this->_getOrderId())
                    ->setInput1('order_id')
                    ->setInputval1($this->_getOrderId())
					->setInput2('Shopsystem name / version')
					->setInputval2('Magento / '.$helper->getMagentoVersion())
					->setInput3('Novalnet module version')
					->setInputval3($helper->getNovalnetVersion());
            $this->_setNovalnetParam($request, $this->_code);
        }

        //Callback Method
        if ($type == Novalnet_Payment_Model_Config::POST_CALLBACK) {
            if ($this->getConfigData('callback') == 1) { //PIN By Callback
                $request->setTel($this->getInfoInstance()->$callbackTelNo());
                $request->setPinByCallback(true);
            } else if ($this->getConfigData('callback') == 2) { //PIN By SMS
                $request->setMobile($this->getInfoInstance()->$callbackTelNo());
                $request->setPinBySms(true);
            } else if ($this->getConfigData('callback') == 3) { //Reply By EMail
                $request->setEmail($this->getInfoInstance()->$callbackEmail());
                $request->setReplyEmailCheck(true);
            }
        }
        return $request;
    }

    /**
     * Post request to gateway and return response
     *
     * @param Varien_Object $request
     * @param string $type
     * @return Varien_Object
     */
    protected function _postRequest($request) {
        $result = new Varien_Object();
        $helper = $this->_helper;
        $paymentKey = $helper->getPaymentId($this->_code);
        if ($this->_validateBasicParams() && $helper->checkIsNumeric($paymentKey)
                && $paymentKey == $request->getKey() && $request->getAmount()
				&& $helper->checkIsNumeric($request->getAmount())) {
            $response = $this->_setNovalnetRequestCall($request->getData(), Novalnet_Payment_Model_Config::PAYPORT_URL);
            $result->addData($helper->deformatNvp('&', $response->getBody()));
        } else {
            $this->showException($helper->__('Required parameter not valid') . '!', false);
        }
        return $result;
    }

    /**
     * Assign Novalnet Authentication Data
     *
     * @param Varien_Object $request
     * @param Decimal $amount
     */
    private function _assignNnAuthData(Varien_Object $request, $storeId = NULL) {
        //Reassign the Basic Params Based on store
        $this->_vendorId = $this->_getConfigData('merchant_id', true, $storeId);
        $this->_authcode = $this->_getConfigData('auth_code', true, $storeId);

        $this->_manualCheckValidate($storeId);
        $request->setVendor($this->_vendorId)
                ->setAuthCode($this->_authcode)
                ->setProduct($this->_productId)
                ->setTariff($this->_tariffId)
                ->setKey($this->_helper->getPaymentId($this->_code));
    }

    /**
     * Refund amount
     *
     * @param	Varien_Object $invoicePayment
     * @param	float $amount
     * @return	Mage_Payment_Model_Abstract
     */
    public function refund(Varien_Object $payment, $amount) {
		$orderAmount = $this->_getAmount();
		if($orderAmount !=$amount) {
			$this->showException('Partial amount is not allowed in online refund. Login to Novalnet admin portal to make partial refund');
		}
        if (!$this->canRefund()) {
            $this->showException('Refund action is not available.');
        }

        if ($payment->getRefundTransactionId() && $amount > 0) {
            $request = new Varien_Object();
            $storeId = $payment->getOrder()->getStoreId();
            $customerId = $payment->getOrder()->getCustomerId();
            $refundAmount = $this->_helper->getFormatedAmount($amount);
            $getTid = $this->_helper->makeValidNumber($payment->getLastTransId());
            $this->_assignNnAuthData($request, $storeId);
            $request->setTid($getTid)
                    ->setRefundRequest(true)
                    ->setRefundParam($refundAmount);

            if (!in_array(NULL, $request->toArray())) {

                $buildNovalnetParam = http_build_query($request->getData());
                $response = $this->_dataHelper->setRawCallRequest($buildNovalnetParam, Novalnet_Payment_Model_Config::PAYPORT_URL);
                if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                    $getTransactionStatus = $this->doNovalnetStatusCall($getTid, $storeId);
                    $loadTransaction = $this->_helper->loadTransactionStatus($getTid);
                    $loadTransaction->setTransactionStatus($getTransactionStatus->getStatus())
									->setAmount($this->_helper->getFormatedAmount($getTransactionStatus->getAmount(), 'RAW'))
									->save();

                    $txnid = $response->getTid();
                    $refund_tid = !empty($txnid) ? $txnid : $payment->getLastTransId() . '-refund';
                    if (in_array($this->_code, array(Novalnet_Payment_Model_Config::NN_INVOICE, Novalnet_Payment_Model_Config::NN_PREPAYMENT))) {
						$amountAfterRefund = ($this->_getAmount() - $amount);
						$loadTransaction->setAmount($amountAfterRefund)
										->save();
                    }
                    $payment->setTransactionId($refund_tid)
                            ->setLastTransId($refund_tid)
                            ->save();

                    if ($txnid) { // Only log the novalnet transaction which contains TID
                        $getTransactionStatus = $this->doNovalnetStatusCall($txnid, $storeId);
						$response->setStatus($getTransactionStatus->getStatus());
						$amountAfterRefund = $this->_helper->getFormatedAmount($getTransactionStatus->getAmount(), 'RAW');
                        $this->logNovalnetStatusData($response, $refund_tid, $customerId, $storeId, $amountAfterRefund);
                    }
                } else {
                    $this->showException($response->getStatusDesc(), false);
                }

                $this->logNovalnetTransactionData($request, $response, $payment->getLastTransId(), $customerId, $storeId);
            } else {
                $this->showException('Error in you refund request');
            }
        } else {
            $this->showException('Error in you refund request');
        }
        return $this;
    }

    /**
     * Void payment
     *
     * @param   Varien_Object $invoicePayment
     * @return  Mage_Payment_Model_Abstract
     */
    public function void(Varien_Object $payment) {
        if (!$this->canVoid($payment)) {
            $this->showException('Void action is not available.');
        }

        $request = new Varien_Object();
        $storeId = $payment->getOrder()->getStoreId();
        $customerId = $payment->getOrder()->getCustomerId();
        $getTid = $this->_helper->makeValidNumber($payment->getLastTransId());
        $this->_assignNnAuthData($request, $storeId);
        $request->setTid($getTid)
                ->setStatus(Novalnet_Payment_Model_Config::PAYMENT_VOID_STATUS)
                ->setEditStatus(true);

        if (!in_array(NULL, $request->toArray())) {

            $buildNovalnetParam = http_build_query($request->getData());
            $response = $this->_dataHelper->setRawCallRequest($buildNovalnetParam, Novalnet_Payment_Model_Config::PAYPORT_URL);
            if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {

                $txnid = $response->getTid();
                $void_tid = !empty($txnid) ? $txnid : $payment->getLastTransId() . '-void';

                if (in_array($this->_code, array(Novalnet_Payment_Model_Config::NN_INVOICE, Novalnet_Payment_Model_Config::NN_PREPAYMENT))) {
                    $bank_void_tid = !empty($txnid) ? $txnid : $payment->getLastTransId();
                    $data = unserialize($payment->getAdditionalData());
					$data['NnNoteAmount'] = $this->_dataHelper->getBankDetailsAmount($amountAfterRefund);
                    $data['NnNoteTID'] = $this->_dataHelper->getBankDetailsTID($bank_void_tid);
                    $payment->setAdditionalData(serialize($data));
                }

                $payment->setTransactionId($void_tid)
                        ->setLastTransId($void_tid)
                        ->save();

                $getTransactionStatus = $this->doNovalnetStatusCall($getTid, $storeId);
                $loadTransaction = $this->_helper->loadTransactionStatus($getTid);
                $loadTransaction->setTransactionStatus($getTransactionStatus->getStatus())
								->setAmount($getTransactionStatus->getAmount()) // void amount is zero so set without formating
								->save();
            } else {
                $this->showException('Error in you void request');
            }
            $this->logNovalnetTransactionData($request, $response, $payment->getLastTransId(), $customerId, $storeId);
        } else {
            $this->showException('Error in you void request');
        }
        return $this;
    }

    /**
     * Get Method Session
     *
     * @return  Checkout session
     */
    private function _getMethodSession($paymentCode = NULL) {
        $paymentCode = (!empty($paymentCode)) ? $paymentCode : $this->getCode();
        $checkoutSession = $this->_helper->_getCheckoutSession();
        if (!$checkoutSession->hasData($paymentCode)) {
            $checkoutSession->setData($paymentCode, new Varien_Object());
        }
        return $checkoutSession->getData($paymentCode);
    }

    /**
     * Unset method session
     *
     *  @return  method session
     */
    private function _unsetMethodSession($paymentCode = NULL) {
        $paymentCode = (!empty($paymentCode)) ? $paymentCode : $this->getCode();
        $this->_helper->_getCheckoutSession()->unsetData($paymentCode);
        return $this;
    }

    /**
     * Get the novalnet configuration data
     *
     * @return mixed
     */
    public function _getConfigData($field, $globalMode = false, $store_id = NULL) {
        $storeId = is_null($store_id) ? $this->_helper->getMagentoStoreId() : $store_id;
        $path = $this->_helper->getNovalnetGlobalPath() . $field;
        if ($field == 'live_mode') {
            $getTestmodePaymentMethod = Mage::getStoreConfig($path, $storeId);
            if (!preg_match('/' . $this->_code . '/i', $getTestmodePaymentMethod)) {
                return false;
            }
            return true;
        } elseif (!is_null($field)) {
            return ($globalMode == false) ? trim($this->getConfigData($field, $storeId))
						: trim(Mage::getStoreConfig($path, $storeId));
        }
        return null;
    }

    /**
     * Send order_no to server after the Completion of payment
     *
     * @param array $response
     * @return mixed
     */
    public function doNovalnetPostbackCall($response) {
        //Required Parameters to be passed to the Server
        $request = $result = array();

        $this->_manualCheckValidate();
        $paymentKey = $this->_helper->getPaymentId($this->_code);
        if ($this->_validateBasicParams() && $this->_helper->checkIsNumeric($paymentKey)) {
            $request['vendor'] = $this->_vendorId;
            $request['auth_code'] = $this->_authcode;
            $request['product'] = $this->_productId;
            $request['tariff'] = $this->_tariffId;
            $request['key'] = $paymentKey;
            $request['tid'] = $response->getTid();
            $request['status'] = Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED;
            $request['order_no'] = $this->_getOrderId();

            if ($request['key'] == 27) {
                $request['invoice_ref'] = 'BNR-' . $request['product'] . '-' . $request['order_no'];
            }

            $result = new Varien_Object();
            $response = $this->_setNovalnetRequestCall($request, Novalnet_Payment_Model_Config::PAYPORT_URL);
            $result = $response->getRawBody();
        }
        return $result;
    }

    /**
     * Check the transaction status using API
     *
     * @param array  $request_data
     * @param string $request_url
     * @param string $type
     *
     * @return mixed
     */
    private function _setNovalnetRequestCall($request_data, $request_url, $type = "") {
        if ($request_url == "") {
            $this->showException('Server Request URL is Empty');
            return;
        }
        $httpClientConfig = array('maxredirects' => 0);
        if (((int) $this->_getConfigData('gateway_timeout')) > 0) {
            $httpClientConfig['timeout'] = (int) $this->_getConfigData('gateway_timeout');
        }
        $client = new Varien_Http_Client($request_url, $httpClientConfig);

        if ($type == 'XML') {
            $client->setUri($request_url);
            $client->setRawData($request_data)->setMethod(Varien_Http_Client::POST);
        } else {
            $client->setParameterPost($request_data)->setMethod(Varien_Http_Client::POST);
        }
        $response = $client->request();
        if (!$response->isSuccessful()) {
            $this->showException($this->_helper->__('Gateway request error: %s', $response->getMessage()), false);
        }
        if ($type == 'XML') {
            $result = new Varien_Simplexml_Element($response->getRawBody());
            $response = new Varien_Object($result->asArray());
        }
        return $response;
    }

    /**
     * Set the novalnet parameter based on payment code
     *
     * @param Varien object  $request
     * @param string $paymentCode
     *
     * @return object
     */
    private function _setNovalnetParam(Varien_Object $request, $paymentCode) {
        $infoInstance = $this->getInfoInstance();
        $helper = $this->_helper;
		$dataHelper = $this->_dataHelper;
        $getPaymentData = $this->_getNnPaymentData();
        if ($paymentCode) {
            switch ($paymentCode) {
                case Novalnet_Payment_Model_Config::NN_ELVDE:
                    $request->setBankAccountHolder($getPaymentData->getNnAccountHolder())
                            ->setBankAccount($getPaymentData->getNnAccountNumber())
                            ->setBankCode($getPaymentData->getNnBankSortingCode())
							->setAcdc( ($this->_getConfigData('acdc_check') ? $this->_getConfigData('acdc_check') : 0) );
                    $infoInstance->setNnAccountNumber($helper->doMaskPaymentData($getPaymentData->getNnAccountNumber()))
                            ->setNnBankSortingCode($helper->doMaskPaymentData($getPaymentData->getNnBankSortingCode()));
                    break;
                case Novalnet_Payment_Model_Config::NN_ELVAT:
                    $request->setBankAccountHolder($getPaymentData->getNnAccountHolder())
                            ->setBankAccount($getPaymentData->getNnAccountNumber())
                            ->setBankCode($getPaymentData->getNnBankSortingCode());
                    $infoInstance->setNnAccountNumber($helper->doMaskPaymentData($getPaymentData->getNnAccountNumber()))
                            ->setNnBankSortingCode($helper->doMaskPaymentData($getPaymentData->getNnBankSortingCode()));
                    break;
                case Novalnet_Payment_Model_Config::NN_INVOICE:
                    $paymentDuration = trim($this->_getConfigData('payment_duration'));
                    $dueDate = $helper->setDueDate($paymentDuration);
                    if ($dueDate)
                        $request->setDueDate($dueDate);
                    $request->setInvoiceType(Novalnet_Payment_Model_Config::INVOICE_PAYMENT_METHOD)
                            ->setInvoiceRef('BNR-' . $request->getProduct() . '-' . $this->_getOrderId());
                    break;
                case Novalnet_Payment_Model_Config::NN_PREPAYMENT:
                    $request->setInvoiceType(Novalnet_Payment_Model_Config::PREPAYMENT_PAYMENT_METHOD)
                            ->setInvoiceRef('BNR-' . $request->getProduct() . '-' . $this->_getOrderId());
                    break;
                case Novalnet_Payment_Model_Config::NN_SOFORT:
                case Novalnet_Payment_Model_Config::NN_PAYPAL:
                case Novalnet_Payment_Model_Config::NN_IDEAL:
                    $this->_password = $this->_getConfigData('password', true);
                    $request->setUniqid(uniqid())
                            ->setCountryCode($request->getCountry())
                            ->setSession(session_id());

                    if ($paymentCode == Novalnet_Payment_Model_Config::NN_PAYPAL) {
                        $request->setApiSignature($helper->getEncodedParam($this->_getConfigData('api_sign', true), $this->_password))
                                ->setApiUser($helper->getEncodedParam($this->_getConfigData('api_user', true), $this->_password))
                                ->setApiPw($helper->getEncodedParam($this->_getConfigData('api_password', true), $this->_password));
                    }

                    $this->_dataHelper->assignNovalnetReturnData($request, $this->_code);
                    $this->_dataHelper->importNovalnetEncodeData($request, $this->_password);
                    break;
                case Novalnet_Payment_Model_Config::NN_CC:
                    $request->setCcHolder($dataHelper->novalnetCardDetails('novalnet_cc_owner'))
                            ->setCcNo()
                            ->setCcExpMonth($dataHelper->novalnetCardDetails('novalnet_cc_exp_month'))
                            ->setCcExpYear($dataHelper->novalnetCardDetails('novalnet_cc_exp_year'))
                            ->setCcCvc2($dataHelper->novalnetCardDetails('novalnet_cc_cid'))
                            ->setCcType($dataHelper->novalnetCardDetails('novalnet_cc_type'))
                            ->setPanHash($dataHelper->novalnetCardDetails('novalnet_cc_pan_hash'))
                            ->setUniqueId($dataHelper->novalnetCardDetails('novalnet_cc_unique_id'));
                    break;
                case Novalnet_Payment_Model_Config::NN_CC3D:
                    $payment = $infoInstance->getOrder()->getPayment();
                    $request->setCountryCode($request->getCountry())
                            ->setSession(session_id())
							->setCcHolder(Mage::helper('core')->decrypt($this->_helper->_getCheckoutSession()->getNnCcOwner()))
							->setCcNo(Mage::helper('core')->decrypt($this->_helper->_getCheckoutSession()->getNnCcNumber()))
							->setCcExpMonth(Mage::helper('core')->decrypt($this->_helper->_getCheckoutSession()->getNnCcExpMonth()))
							->setCcExpYear(Mage::helper('core')->decrypt($this->_helper->_getCheckoutSession()->getNnCcExpYear()))
							->setCcCvc2(Mage::helper('core')->decrypt($this->_helper->_getCheckoutSession()->getNnCcCvc()));
                    $dataHelper->assignNovalnetReturnData($request, $this->_code);
                    $request->unsUserVariable_0(); // Unset uservariable as CC3D doesnot requires it
                    break;
            }
        }
        return $request;
    }

    /**
     * Validate the novalnet response
     *
     * @param object $response
     * @param object $payment
     * @param string $request
     *
     * @return mixed
     */
    private function _validateNovalnetResponse($response, $payment, $request = NULL) {
        $result = $response;
        switch ($result->getStatus()) {
            case Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED:
                //set Novalnet Mode
                $ResponseTestMode = $result->getTestMode();
                $txnId = $result->getTid();
                $shopMode = $this->_getConfigData('live_mode');
                $testMode = (((isset($ResponseTestMode) && $ResponseTestMode == 1) || (isset($shopMode) && $shopMode == 0)) ? 1 : 0 );
                $data = array('NnTestOrder' => $testMode);

                if (in_array($this->_code, array(Novalnet_Payment_Model_Config::NN_INVOICE, Novalnet_Payment_Model_Config::NN_PREPAYMENT))) {

                    $data['NnNote'] = $this->_dataHelper->getNote($response, $this->_getConfigData('payment_duration'));
                    $data['NnNoteAmount'] = $this->_dataHelper->getBankDetailsAmount($response->getAmount());
                    $data['NnNoteTID'] = $this->_dataHelper->getBankDetailsTID($txnId);
                    $data['NnNoteTransfer'] = $this->_dataHelper->getBankDetailsTransfer($response);
                }

                /** @@ Update the transaction status and transaction overview **/
                $getTransactionStatus = $this->doNovalnetStatusCall($txnId);
				$result->setStatus($getTransactionStatus->getStatus());
				$this->logNovalnetStatusData($result, $txnId);
                $payment->setStatus(self::STATUS_APPROVED)
                        ->setIsTransactionClosed(false)  // set transaction opend to make payment void
                        ->setAdditionalData(serialize($data))
                        ->save();

                // Capture the payment only if status is 100
                if ($getTransactionStatus->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                    $payment->setTransactionId("$txnId-capture") // Add capture text to make the new transaction
                            ->setParentTransactionId($txnId)
							->setIsTransactionClosed(true) // Close the transaction
                            ->capture(null)
                            ->save();
                }
                $payment->setTransactionId($txnId)
                        ->setLastTransId($txnId)
                        ->setParentTransactionId(null)
                        ->save();

                $this->doNovalnetPostbackCall($result); //Second Call
				if(!$this->isCallbackTypeCall()) {
					$this->logNovalnetTransactionData($request, $result, $txnId, $this->_helper->getCustomerId(), $this->_helper->getMagentoStoreId());
				}
                $statusText = ($result->getStatusText()) ? $result->getStatusText() : $this->_helper->__('successful');
                Mage::getSingleton('core/session')->addSuccess($statusText);
                return $this;
            default:
                $error = ($result->getStatusDesc()) ? $this->_helper->htmlEscape($result->getStatusDesc()) : $this->_helper->__('Error in capturing the payment');
				if(!$this->isCallbackTypeCall()) {
					 $this->logNovalnetTransactionData($request, $result, $result->getTid(), $this->_helper->getCustomerId(), $this->_helper->getMagentoStoreId());
				}
                if ($error !== false) {
                    $this->showException($error, false);
                }
				break;
        }
    }

    /**
     * Make telephone payment first call and display the messages
     *
     * @return
     */
    public function doNovalnetPhoneFirstCall() {
		$methodSession = $this->_getMethodSession();
		$helper = $this->_helper;
		$phoneTid = $methodSession->getNnPhoneTid();
        if ($this->_isPlaceOrder() && ($this->_code == Novalnet_Payment_Model_Config::NN_TELEPHONE)) {
            if (!$this->checkAmountAllowed()) {
                return false;
            }
			if ($phoneTid) {
			   $this->_validateSession();
			}
			$methodSession = $this->_getMethodSession();	// Reaasign method session after values unset
			if (!$phoneTid) {
				//invoke first call
				$storeId = $helper->getMagentoStoreId();
				$request = $this->buildRequest(Novalnet_Payment_Model_Config::POST_NORMAL, $storeId);
				$result = $this->_postRequest($request);
				$methodSession->setOrderAmount($request->getAmount())
							  ->setNnTelOrderNo($request->getOrderNo());

				if (!$result) {
					$this->showException('Params (aryResponse) missing');
				}

				if ($result->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
					// Passing the Error Response from Novalnet's paygate to payment error
					$this->logNovalnetTransactionData($request, $result, $result->getTid(), $helper->getCustomerId(), $helper->getMagentoStoreId());
					$methodSession->setNnPhoneTid($result->getTid());
					$methodSession->setNnPhoneNo($result->getNovaltelNumber());
					$methodSession->setNnPhoneStatusText($result->getStatusDesc());
					$this->_getCheckout()->setNnTelTestOrder($result->getTestMode());
					$text = $helper->__('Following steps are required to complete your payment') . ':' . "\n\n";
					$text .= $helper->__('Step') . ' 1: ';
					$text .= $helper->__('Please call the telephone number displayed') . ': ' . preg_replace('/(\d{4})(\d{4})(\d{4})(\d{4})/', "$1 $2 $3 $4", $methodSession->getNnPhoneNo()) . ".\n";
					$text .= '* ' . $helper->__('This call will cost') . ' ' . str_replace('.', ',', $result->getAmount()) . " " . Mage::app()->getLocale()->currency($result->getCurrency())->getSymbol() . ' (' . $helper->__('inclusive tax') . ') ';
					$text .= $helper->__('and it is possible only for German landline connection') . '!*' . "\n\n";
					$text .= $helper->__('Step') . ' 2: ';
					$text .= $helper->__('Please wait for the beep and then hang up the listeners') . '.' . "\n";
					$text .= $helper->__('After your successful call, please proceed with the payment') . '.' . "\n";

					$this->text = $text;
					$this->showException($text, false); #show note for client to call...
				}else{
					$this->showException($result->getStatusDesc(), false);
				}
			}
        }
    }

    /**
     * Validate the novalnet second call response
     *
     * @param object $result
     * @param object $payment
     *
     * @return mixed
     */
    private function _validateSecondCallResponse($result, $payment) {
        $status = $result->getStatus();
        switch ($status) {
            case Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED:
                $serverResponse = $this->_getCheckout()->getNnTelTestOrder();
                $shopMode = $this->_getConfigData('live_mode');
                $testMode = (((isset($serverResponse) && $serverResponse == 1) || (isset($shopMode) && $shopMode == 0)) ? 1 : 0 );
                $data = array('NnTestOrder' => $testMode);

                $payment->setStatus(self::STATUS_APPROVED)
                        ->setLastTransId($result->getTid())
                        ->setAdditionalData(serialize($data))
                        ->save();
                $this->doNovalnetPostbackCall($result);
                $getTransactionStatus = $this->doNovalnetStatusCall($result->getTid());
                $result->setStatus($getTransactionStatus->getStatus());  // Combine Transaction status in result object
                $this->logNovalnetStatusData($result, $result->getTid());
                $statusText = ($result->getStatusText()) ? $result->getStatusText() : $this->_helper->__('successful');
                Mage::getSingleton('core/session')->addSuccess($statusText);
                $this->_unsetMethodSession();
                return $this;
                break;
            default:
                $error = ($result->getStatusDesc()) ? $this->_helper->htmlEscape($result->getStatusDesc()) : $this->_helper->__('Error in capturing the payment');
                if ($error !== false) {
                    $this->showException($error, false);
                }
        }
    }

    /**
     * Validate the transaction status
     *
     * @param integer $tid
     * @param integer $storeId
     * @param string $reqType
     * @param object $extraOption
     *
     * @return mixed
     */
    public function doNovalnetStatusCall($tid, $storeId = NULL, $reqType = Novalnet_Payment_Model_Config::TRANS_STATUS, $extraOption = NULL, $requestData=NULL) {
        $requestType = ($reqType == Novalnet_Payment_Model_Config::TRANS_STATUS) ? Novalnet_Payment_Model_Config::TRANS_STATUS : $reqType;
        $this->_manualCheckValidate($storeId);
        $request = '<?xml version="1.0" encoding="UTF-8"?>';
        $request .= '<nnxml><info_request>';
        $request .= '<vendor_id>' . $this->_vendorId . '</vendor_id>';
        $request .= '<vendor_authcode>' . $this->_authcode . '</vendor_authcode>';
        $request .= '<request_type>' . $requestType . '</request_type>';
        $request .= '<product_id>' . $this->_productId . '</product_id>';
        $request .= '<tid>' . $tid . '</tid>' . $extraOption;
        $request .= '</info_request></nnxml>';

        if ($this->_validateBasicParams()) {
            $result = $this->_setNovalnetRequestCall($request, Novalnet_Payment_Model_Config::INFO_REQUEST_URL, 'XML');
            if($requestType == 'NOVALTEL_STATUS') { $requestData->setData($request); }
			return $result;
        }
    }

    /**
     * Check whether the cart amount and response server API amount is  valid
     *
     * @return void
     */
    private function _validateSession() {
        $methodSession = $this->_getMethodSession();
        if ($methodSession->hasNnPhoneTid()) {
            if ($methodSession->getOrderAmount() != $this->_helper->getFormatedAmount($this->_getAmount())) {
                $this->_unsetMethodSession();
                $this->showException('You have changed the order amount after receiving telephone number');
            }
        }
    }

    /**
     * Get checkout session
     *
     * @return Mage_Sales_Model_Order
     */
    protected function _getCheckout() {
        if (Mage::app()->getStore()->isAdmin()) {
            return $this->_helper->_getAdminCheckoutSession();
        } else {
            return $this->_helper->_getCheckoutSession();
        }
    }

    /**
     * Check whether the cart amount allowed for this payment
     * Amount between 0.99 & 10.00 EUR
     *
     * @return bool
     */
    public function checkAmountAllowed() {
        $amount = $this->_helper->getFormatedAmount($this->_getAmount());
        if ($amount >= Novalnet_Payment_Model_Config::PHONE_PAYMENT_AMOUNT_MIN and $amount <= Novalnet_Payment_Model_Config::PHONE_PAYMENT_AMOUNT_MAX) {
            return true;
        }
        $this->showException($this->_helper->__('Amounts below 0,99 Euros and above 10,00 Euros cannot be processed and are not accepted') . '!', false);
        return false;
    }

    /**
     * Post request to gateway to Load CC Iframe
     *
     * @return Varien_Object
     */
    public function getNovalnetIframe() {

		$amount =  ($this->_helper->checkIsAdmin()) ? $this->_helper->_getAdminCheckoutSession()->getQuote()->getBaseGrandTotal() : $this->_helper->_getCheckoutSession()->getQuote()->getBaseGrandTotal();
		$storeId = $this->_helper->getMagentoStoreId();
		$this->_manualCheckValidate($storeId, $amount);
        //Required Parameters to be passed to the Server
        $request = array();
        $request['nn_lang_nn'] = strtoupper(substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2));
        $request['nn_vendor_id_nn'] = $this->_vendorId;
        $request['nn_product_id_nn'] = $this->_productId;
        $request['nn_payment_id_nn'] = $this->_helper->getPaymentId($this->_code);

        if ($this->_vendorId && $this->_productId) {
            $result = $this->_setNovalnetRequestCall($request, Novalnet_Payment_Model_Config::CC_URL);
            $result = $result->getRawBody();
        } else {
            $result = $this->_helper->__('Basic Parameter is not valid');
        }
        return $result;
    }

    /**
     * validate novalnet params to proceed checkout
     *
     * @return bool
     */
    public function validateNovalnetParams() {
        $billing = $this->_getInfoObject()->getBillingAddress();
        if (!$this->_validateBasicParams()) {
            $this->showException($this->_helper->__('Basic parameter not valid') . '!', false);
            return false;
        } elseif ($this->_getConfigData('manual_checking_amount') && (!$this->_helper->checkIsNumeric($this->_getConfigData('manual_checking_amount')) || !$this->_helper->checkIsNumeric($this->_getConfigData('second_product_id')) || !$this->_helper->checkIsNumeric($this->_getConfigData('second_tariff_id')))) {
            $this->showException($this->_helper->__('Manual limit amount / Product-ID2 / Tariff-ID2 is not valid') . '!', false);
            return false;
        } elseif (!$this->_helper->validateEmail($this->_getInfoObject()->getCustomerEmail()) || !$billing->getFirstname() || !$billing->getLastname()) {
            $this->showException($this->_helper->__('Customer name/email fields are not valid') . '!', false);
            return false;
        }
        return true;
    }
    
    /**
     * validate manual checklimit and reassign product id and tariff id
     *
     * @param integer $storeId
     * @param float $amount
     */

	private function _manualCheckValidate($storeId = NULL, $amount=NULL) {
        $amount = $this->_helper->getFormatedAmount(($amount) ? $amount : $this->_getAmount());
        $storeId = is_null($storeId) ? $this->_helper->getMagentoStoreId() : $storeId;
        $manualCheckAmt = (int) $this->_getConfigData('manual_checking_amount', false, $storeId);
        $this->_productId = (strlen(trim($this->_getConfigData('second_product_id', false, $storeId))) && $manualCheckAmt && $manualCheckAmt <= $amount) ? trim($this->_getConfigData('second_product_id', false, $storeId)) : trim($this->_getConfigData('product_id', true, $storeId));
        $this->_tariffId = (strlen(trim($this->_getConfigData('second_tariff_id', false, $storeId))) && $manualCheckAmt && $manualCheckAmt <= $amount) ? trim($this->_getConfigData('second_tariff_id', false, $storeId)) : trim($this->_getConfigData('tariff_id', true, $storeId));
    }

    /**
     * validate novalnet basic params
     *
     * @return bool
     */
    private function _validateBasicParams() {
        $helper = $this->_helper;
        if ($helper->checkIsNumeric($this->_vendorId) && $this->_authcode && $helper->checkIsNumeric($this->_productId) && $helper->checkIsNumeric($this->_tariffId)) {
            return true;
        }
        return false;
    }

    /**
     * Check whether callback option is enabled
     *
     */
    public function isCallbackTypeCall() {
        $callbackTid = "hasNnCallbackTid" . ucfirst($this->_code);
        $total = $this->_helper->getFormatedAmount($this->_getAmount());
        $callBackMinimum = (int) $this->_getConfigData('callback_minimum_amount');
        $countryCode = strtoupper($this->_getInfoObject()->getBillingAddress()->getCountryId());

        return $this->_helper->_getCheckoutSession()->$callbackTid()
                || ($this->_getConfigData('callback')
                && ($callBackMinimum ? $total >= $callBackMinimum : true)
                && ($this->_helper->isCallbackTypeAllowed($countryCode)));
    }

    /**
     * Initiate callback process after selecting payment method
     *
     * @return bool
     */
	private function _initiateCallbackProcess($paymentCode) {
		$infoInstance = $this->getInfoInstance();
		$isCallbackTypeCall = $this->isCallbackTypeCall();
		$isPlaceOrder = $this->_isPlaceOrder();
		$callbackPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('fraudCheckPayment');
        if (in_array($paymentCode, $callbackPayment)) {
            $payCode = ucfirst($paymentCode);
            $callbackTid		= "getNnCallbackTid" . $payCode;
            $callbackOrderNo	= "getNnCallbackOrderNo" . $payCode;
            $callbackPin		= "getNnCallbackPin" . $payCode;
            $callbackNewPin		= "getNnNewCallbackPin" . $payCode;
            $setcallbackPin		= "setNnCallbackPin" . $payCode;

            if (!$isPlaceOrder && $isCallbackTypeCall && $this->_getIncrementId() != $this->_getMethodSession()->$callbackOrderNo()) {
                $this->_unsetMethodSession();
            }
            //validate callback session
            $this->_validateCallbackSession();
			$methodSession = $this->_getMethodSession();
            if ($isCallbackTypeCall && $infoInstance->getCallbackPinValidationFlag() && $methodSession->$callbackTid()) {
                $nnCallbackPin = $infoInstance->$callbackPin();
                if (!$infoInstance->$callbackNewPin() && (!$this->_helper->checkIsValid($nnCallbackPin) || empty($nnCallbackPin))) {
                    $this->showException('PIN you have entered is incorrect or empty');
                }
            }
            if ($isCallbackTypeCall && !$isPlaceOrder && $this->_getConfigData('callback') != 3) {
                if ($methodSession->$callbackTid()) {
                    if ($infoInstance->$callbackNewPin()) {
                        $this->_regenerateCallbackPin();
                    } else {
                        $methodSession->$setcallbackPin($infoInstance->$callbackPin());
                    }
                } else {
                    $this->_generateCallback();
                }
            } elseif ($isCallbackTypeCall && !$isPlaceOrder && $this->_getConfigData('callback') == 3) {

                if (!$methodSession->$callbackTid()) {
                    $this->_generateCallback();
                }
            }
            if ($isPlaceOrder) {
                $this->_validateCallbackProcess();
            }
        }
	}

    /**
     * validate order amount is getting changed after callback initiation
     *
     * throw Mage Exception
     */
    private function _validateCallbackSession() {
        $payCode = ucfirst($this->_code);
        $callbackTid = "hasNnCallbackTid" . $payCode;
        $getNnDisableTime = "getNnDisableTime" . $payCode;
        $methodSession = $this->_getMethodSession();
        if ($methodSession->$callbackTid()) {
            if ($methodSession->$getNnDisableTime() && time() > $methodSession->$getNnDisableTime()) {
                $this->_unsetMethodSession();
            } elseif (intval($methodSession->getOrderAmount()) != $this->_helper->getFormatedAmount($this->_getAmount())) {
                $this->_unsetMethodSession();
                if (!$this->_isPlaceOrder() && $this->_getConfigData('callback') != 3) {
                    $this->showException('You have changed the order amount after getting PIN number, please try again with a new call');
                } else if (!$this->_isPlaceOrder() && $this->_getConfigData('callback') == 3) {
                    $this->showException('You have changed the order amount after getting e-mail, please try again with a new call');
                }
            }
        }
    }

    /**
     * Get increment id for callback process
     *
     * @return mixed
     */
    private function _getIncrementId() {
		$storeId = $this->_getHelper()->getMagentoStoreId();
		$orders = Mage::getModel('sales/order')->getCollection()
					->addAttributeToFilter('store_id', $storeId)
					->setOrder('entity_id','DESC');
		$lastIncrementId = $orders->getFirstItem()->getIncrementId();
		if($lastIncrementId) {
			$IncrementId = ++$lastIncrementId;
		} else {
			$IncrementId = $storeId.Mage::getModel('eav/entity_increment_numeric')->getNextId();
		}
		return $IncrementId;
    }

    /**
     * Make callback request and validate response
     *
     */
    private function _generateCallback() {
        $payCode = ucfirst($this->_code);
        $callbackTid = "setNnCallbackTid" . $payCode;
        $callbackOrderNo = "setNnCallbackOrderNo" . $payCode;

        $request = $this->buildRequest(Novalnet_Payment_Model_Config::POST_CALLBACK);
        $response = $this->_postRequest($request);
		$this->logNovalnetTransactionData($request, $response, $response->getTid());
        if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $this->_getMethodSession()
                    ->$callbackTid(trim($response->getTid()))
                    ->setNnTestMode(trim($response->getTestMode()))
                    ->setNnCallbackTidTimeStamp(time())
                    ->setOrderAmount($request->getAmount())
                    ->setNnCallbackSuccessState(true)
                    ->$callbackOrderNo(trim($response->getOrderNo()))
					->setNnResponseData($response);
            if ($this->_getConfigData('callback') == 3) {
                $text = $this->_helper->__('Please reply to the e-mail');
            } elseif ($this->_getConfigData('callback') == 1) {
                $text = $this->_helper->__('You will shortly receive a PIN by phone / SMS. Please enter the PIN in the appropriate text box');
            }
        } else {
            $text = $this->_helper->htmlEscape($response->getStatusDesc());
        }
        $this->showException($text, false);
    }

    /**
     * Regenerate new pin for callback process
     *
     */
    private function _regenerateCallbackPin() {
        $callbackTid = "getNnCallbackTid" . ucfirst($this->_code);
        $methodSession = $this->_getMethodSession();
        $response = $this->doNovalnetStatusCall($methodSession->$callbackTid(), NULL, Novalnet_Payment_Model_Config::TRANSMIT_PIN_AGAIN);
        if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $text = $this->_helper->__('You will shortly receive a PIN by phone / SMS. Please enter the PIN in the appropriate text box');
        } else {
            $text = $this->_helper->htmlEscape($response->getStatusMessage()); //status_message
        }
        $this->showException($text, false);
    }

    /**
     * validate callback response
     *
     */
    private function _validateCallbackProcess() {
        $payCode = ucfirst($this->_code);
        $callbackTid = "getNnCallbackTid" . $payCode;
        $callbackPin = "getNnCallbackPin" . $payCode;
        $setNnDisableTime = "setNnDisableTime" . $payCode;
        $getNnDisableTime = "getNnDisableTime" . $payCode;
        $methodSession = $this->_getMethodSession();
        if ($methodSession->getNnCallbackSuccessState()) {
            if ($this->_getConfigData('callback') == 3) {
                $type = Novalnet_Payment_Model_Config::REPLY_EMAIL_STATUS;
                $extraOption = '';
            } elseif ($this->_getConfigData('callback') == 1) {
                $type = Novalnet_Payment_Model_Config::PIN_STATUS;
                $extraOption = '<pin>' . $methodSession->$callbackPin() . '</pin>';
            }
            $result = $this->doNovalnetStatusCall($methodSession->$callbackTid(), NULL, $type, $extraOption);
            $result->setTid($methodSession->$callbackTid());
            $result->setTestMode($methodSession->getNnTestMode());

            // Analyze the response from Novalnet
            if ($result->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                $methodSession->setNnCallbackSuccessState(false);
            } else {
                if ($result->getStatus() == Novalnet_Payment_Model_Config::METHOD_DISABLE_CODE) {
                    $methodSession->$setNnDisableTime(time() + (30 * 60));
                }
                $error = ($result->getStatusDesc() || $result->getStatusMessage()) ? $this->_helper->htmlEscape($result->getStatusMessage() . $result->getStatusDesc()) : $this->_helper->htmlEscape($result->pin_status['status_message']);
                $this->showException($error, false);
            }
        }
    }

    /**
     * Log novalnet transaction data
     *
     */
    public function logNovalnetTransactionData($request, $response, $txnId, $customerId = NULL, $storeId = NULL) {
        $this->_dataHelper->doRemoveSensitiveData($request, $this->_code);
        $shopUrl = ($response->getMemburl()) ? $response->getMemburl() : $this->_helper->getCurrentSiteUrl();
        $customerId = ($customerId) ? $customerId : $this->_helper->getCustomerId();
        $storeId = ($storeId) ? $storeId : $this->_helper->getMagentoStoreId();
        $modNovalTransactionOverview = $this->_helper->getModelTransactionOverview();
        $modNovalTransactionOverview->setTransactionId($txnId)
                ->setOrderId($this->_getOrderId())
                ->setRequestData(serialize($request->getData()))
                ->setResponseData(serialize($response->getData()))
                ->setCustomerId($customerId)
                ->setStatus($response->getStatus())
                ->setStoreId($storeId)
                ->setShopUrl($shopUrl)
                ->setCreatedDate($this->_helper->getCurrentDateTime())
                ->save();
    }

    /**
     * Log novalnet transaction status data
     *
     */
    public function logNovalnetStatusData($response, $txnId, $customerId = NULL, $storeId = NULL, $amount = NULL) {
	    $shopUrl = ($response->getMemburl()) ? $response->getMemburl() : $this->_helper->getCurrentSiteUrl();
        $customerId = ($customerId) ? $customerId : $this->_helper->getCustomerId();
        $storeId = ($storeId) ? $storeId : $this->_helper->getMagentoStoreId();
        $amount = ($amount) ? $amount : $this->_getAmount();
        $modNovalTransactionStatus = $this->_helper->getModelTransactionStatus();
        $modNovalTransactionStatus->setTransactionNo($txnId)
								->setOrderId($this->_getOrderId())  //order number
								->setTransactionStatus($response->getStatus()) //transaction status code
								->setNcNo($response->getNcNo())
								->setCustomerId($customerId) //customer number
								->setPaymentName($this->_code)   //payment name
								->setAmount($amount)  //amount
								->setRemoteIp($this->_helper->getRealIpAddr()) //remote ip
								->setStoreId($storeId)  //store id
								->setShopUrl($shopUrl)
								->setCreatedDate($this->_helper->getCurrentDateTime()) //created date
								->save();
						
    }
	
    /**
     * validate basic params to load Cc Iframe
     *
     * @return bool
     */

	public function validateNovalnetCcParams() {
        if (!$this->_validateBasicParams()) {
            return false;
        } elseif ($this->_getConfigData('manual_checking_amount') && (!$this->_helper->checkIsNumeric($this->_getConfigData('manual_checking_amount')) || !$this->_helper->checkIsNumeric($this->_getConfigData('second_product_id')) || !$this->_helper->checkIsNumeric($this->_getConfigData('second_tariff_id')))) {
            return false;
        }
        return true;
    }

    /**
     * Get current infoinstance
     *
     * @return Mage_Payment_Model_Method_Abstract
     */
	 
    private function _getInfoInstance() {
        if (!isset($this->infoInstance)) {
            $this->infoInstance = $this->getInfoInstance();
        }
        return $this->infoInstance;
    }

    /**
     * Get current order/quote object
     *
     * @return Mage_Payment_Model_Method_Abstract
     */
    private function _getInfoObject() {
        $info = $this->_getInfoInstance();
        if ($this->_isPlaceOrder()) {
            return $info->getOrder();
        } else {
            return $info->getQuote();
        }
    }

    /**
     * Whether current operation is order placement
     *
     * @return bool
     */
    private function _isPlaceOrder() {
        $info = $this->_getInfoInstance();
        if ($info instanceof Mage_Sales_Model_Quote_Payment) {
            return false;
        } elseif ($info instanceof Mage_Sales_Model_Order_Payment) {
            return true;
        }
    }

    /**
     * Grand total getter
     *
     * @return string
     */
    private function _getAmount() {
        $info = $this->_getInfoInstance();
        if ($this->_isPlaceOrder()) {
            return (double) $info->getOrder()->getBaseGrandTotal();
        } else {
            return (double) $info->getQuote()->getBaseGrandTotal();
        }
    }

    /**
     * Order increment ID getter (either real from order or a reserved from quote)
     *
     * @return string
     */
    private function _getOrderId() {
        $info = $this->_getInfoInstance();
        if ($this->_isPlaceOrder()) {
            return $info->getOrder()->getIncrementId();
        } else {
            return $this->_getIncrementId();
        }
    }

    /**
     * Get payment data for current order
     *
     * @return Mage_Payment_Model_Method_Abstract
     */
    private function _getNnPaymentData() {
        $info = $this->_getInfoInstance();
        if ($this->_isPlaceOrder()) {
            return $info->getOrder()->getPayment();
        } else {
            return $info;
        }
    }

    /**
     * Retrieve model helper
     *
     * @return Novalnet_Payment_Helper_Data
     */
    protected function _getHelper() {
        return Mage::helper('novalnet_payment');
    }

    /**
     * Retrieve Assign data helper
     *
     * @return Novalnet_Payment_Helper_AssignData
     */
    protected function _getDataHelper() {
        return Mage::helper('novalnet_payment/AssignData');
    }

	/**
     * Show expection
     *
	 * @param $string
	 * @param $lang
     * @return Mage::throwException
     */
	public function showException($string, $lang=true){
		if($lang)
			$string = $this->_helper->__($string);

		return Mage::throwException($string);
	}

	/**
     * Assign helper utilities needed for the payment process
     *
     */
	public function assignUtilities(){
		if(!$this->_helper){
			$this->_helper = $this->_getHelper();
		}
		if(!$this->_dataHelper){
			$this->_dataHelper = $this->_getDataHelper();
		}
	}
}
