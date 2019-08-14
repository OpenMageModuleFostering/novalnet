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
 * Part of the Payment module of Novalnet AG
 * https://www.novalnet.de
 * If you have found this script useful a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Novalnet AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Model_Payment_Method_Abstract extends Mage_Payment_Model_Method_Abstract
{

    protected $_isGateway = false;
    protected $_canAuthorize = false;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_canSaveCc = false;
    protected $_isInitializeNeeded = false;
    protected $_mustTransimitInvoicingData = false;
    protected $_mustTransimitInvoicingItemTypes = false;
    protected $_canCancelInvoice = false;
    protected $_methodType = '';
    protected $_redirectUrl = '';
    var $infoInstance;
    var $helper;
    var $dataHelper;

    /**
     * Load Basic Params in constructor
     *
     * @return
     */
    public function __construct()
    {
        //Novalnet Basic parameters
        $this->assignUtilities();
        $this->_vendorId = $this->_getConfigData('merchant_id', true);
        $this->_authcode = $this->_getConfigData('auth_code', true);
        $this->_productId = $this->_getConfigData('product_id', true);
        $this->_tariffId = $this->_getConfigData('tariff_id', true);
        $this->_referrerId = $this->_getConfigData('referrer_id', true);
    }

    /**
     * Check whether payment method can be used
     *
     * @param Mage_Sales_Model_Quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        $getNnDisableTime = "getNnDisableTime" . ucfirst($this->_code); //Dynamic Getter based on payment methods
        $minOrderCount = $this->_getConfigData('orderscount');
        $userGroupId = $this->_getConfigData('user_group_excluded');

        if ($this->helper->checkOrdersCount($minOrderCount)) {
            return false;
        } else if (!$this->helper->checkCustomerAccess($userGroupId)) {
            return false;
        } else if (!empty($quote) && !$this->helper->isModuleActive($quote->getGrandTotal())) {
            return false;
        } else if (time() < $this->_getMethodSession()->$getNnDisableTime()) {
            return false;
        }
        $this->paymentRefillValidate();

        return parent::isAvailable($quote);
    }

    /**
     * Validate payment method information object
     *
     * @return  Mage_Payment_Model_Abstract
     */
    public function validate()
    {
        parent::validate();
        $infoInstance = $this->_getInfoInstance();
        if (!$this->_isPlaceOrder()) {

            // validate the Novalnet basic params and billing informations
            $this->validateNovalnetParams();
            //customer_no verification
            $this->helper->customerNumberValidation();
            // validate the form payment method values
            $this->dataHelper->validateNovalnetData($this->_code, $infoInstance);

            // unset the telephone payment data
            if ($this->_code != Novalnet_Payment_Model_Config::NN_TELEPHONE &&
                    $this->_getMethodSession(Novalnet_Payment_Model_Config::NN_TELEPHONE)->getNnPhoneTid()) {
                $this->unsetMethodSession(Novalnet_Payment_Model_Config::NN_TELEPHONE);
            }

            if ($this->_code == Novalnet_Payment_Model_Config::NN_TELEPHONE) {
                $this->doNovalnetPhoneFirstCall();
            }
        }

        $callbackPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('fraudCheckPayment');
        if (in_array($this->_code, $callbackPayment) || ($this->_code == 'novalnetCc'
                && $this->_getConfigData('active_cc3d') != 1)) {
            $this->_initiateCallbackProcess($this->_code);
        }

        if (!$this->isCallbackTypeCall() && $this->_isPlaceOrder()) {
            $this->_sendRequestToNovalnet();
        }
        return $this;
    }

    /**
     * Payment form refill validation
     *
     */
    public function paymentRefillValidate()
    {
        // set method session
        $this->_getMethodSession();
        $prevPaymentCode = $this->_getCheckout()->getNnPaymentCode();
        // check the users (guest or login)
        $customerSession = $this->helper->getCustomerSession();
        $coreSession = $this->helper->getCoresession();
        if (!$customerSession->isLoggedIn() && $coreSession->getGuestloginvalue()
                == '') {
            $coreSession->setGuestloginvalue('1');
        } elseif ($coreSession->getGuestloginvalue() == '1' && $customerSession->isLoggedIn()) {
            $coreSession->setGuestloginvalue('0');
            $this->unsetMethodSession($prevPaymentCode);
        }

        // unset form previous payment method session
        $paymentCode = $this->_getCheckout()->getQuote()->getPayment()->getMethod();
        if ($paymentCode && !preg_match("/novalnet/i", $paymentCode) && $prevPaymentCode && $paymentCode != $prevPaymentCode) {
            $this->unsetMethodSession($prevPaymentCode);
        }
    }

    /**
     *  Assign Form Data in quote instance
     *
     * @return  Mage_Payment_Model_Abstract Object
     */
    public function assignData($data)
    {
        $infoInstance = $this->_getInfoInstance();
        // unset form previous payment method session
        $prevPaymentCode = $this->_getCheckout()->getNnPaymentCode();
        if ($prevPaymentCode && $this->_code != $prevPaymentCode) {
            $this->unsetMethodSession($prevPaymentCode);
        }
        $this->dataHelper->assignNovalnetData($this->_code, $data, $infoInstance);
        return $this;
    }

    /**
     * Assign the Novalnet direct payment methods request
     *
     */
    private function _sendRequestToNovalnet()
    {
        $redirectPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayments');
        array_push($redirectPayment, Novalnet_Payment_Model_Config::NN_TELEPHONE);
        if ($this->_code && !in_array($this->_code, $redirectPayment)) {

            if ($this->_code == Novalnet_Payment_Model_Config::NN_CC && !$this->helper->checkIsAdmin()
                    && $this->_getConfigData('active_cc3d') == 1) {
                return false;
            }

            $storeId = $this->helper->getMagentoStoreId();
            $request = $this->buildRequest(Novalnet_Payment_Model_Config::POST_NORMAL, $storeId);
            $methodSession = $this->_getMethodSession($this->_code);
            $methodSession->setPaymentReqData($request);
        }
    }

    /**
     * Capture
     *
     * @param   Varien_Object $payment
     * @param float $amount
     * @return  Mage_Payment_Model_Abstract
     */
    public function capture(Varien_Object $payment, $amount)
    {
        $redirectPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayments');
        array_push($redirectPayment, Novalnet_Payment_Model_Config::NN_TELEPHONE);
        $responseCodeApproved = Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED;

        if ($this->canCapture() && $this->_code && !in_array($this->_code, $redirectPayment)) {
            $lastTranId = $this->helper->makeValidNumber($payment->getLastTransId());
            $loadTransStatus = $this->helper->loadTransactionStatus($lastTranId);
            $transStatus = $loadTransStatus->getTransactionStatus();

            if (!empty($transStatus) && $transStatus != $responseCodeApproved) {

                //Send capture request to Payport
                $response = $this->_performPayportRequest($payment, 'capture');
                if ($response->getStatus() == $responseCodeApproved) {
                    $loadTransStatus->setTransactionStatus($responseCodeApproved)
                            ->save();

                    $magentoVersion = $this->_getHelper()->getMagentoVersion();
                    // make capture transaction open for lower versions to make refund
                    if (version_compare($magentoVersion, '1.6', '<')) {
                        $payment->setIsTransactionClosed(false)
                                ->save();
                    }
                    if ($transStatus != $responseCodeApproved) {
                        $transMode = (version_compare($magentoVersion, '1.6', '<'))
                                    ? false : true;
                        $payment->setTransactionId($lastTranId)
                                ->setIsTransactionClosed($transMode);
                        $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, null, false);
                        $transaction->setParentTxnId(null)
                                ->save();
                    }
                } else {
                    $this->showException('Error in you capture request');
                }
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
    public function buildRequest($type = Novalnet_Payment_Model_Config::POST_NORMAL,
        $storeId = NULL
    ) {
        $payCode = ucfirst($this->_code);
        $helper = $this->helper;
        $callbackTelNo = "getNnCallbackTel" . $payCode;
        $callbackEmail = "getNnCallbackEmail" . $payCode;
        $refernceOne = trim(strip_tags(trim($this->_getConfigData('reference_one'))));
        $refernceTwo = trim(strip_tags(trim($this->_getConfigData('reference_two'))));

        if ($type == Novalnet_Payment_Model_Config::POST_NORMAL || $type == Novalnet_Payment_Model_Config::POST_CALLBACK) {
            $request = new Varien_Object();
            $amount = $helper->getFormatedAmount($this->_getAmount());
            $billing = $this->_getInfoObject()->getBillingAddress();
            $email = $billing->getEmail() ? $billing->getEmail() : $this->_getInfoObject()->getCustomerEmail();
            $this->assignNnAuthData($request, $storeId);
            $request->setTestMode((!$this->_getConfigData('live_mode')) ? 1 : 0)
                    ->setAmount($amount)
                    ->setCurrency($this->_getInfoObject()->getBaseCurrencyCode())
                    ->setCustomerNo($helper->getCustomerId())
                    ->setUseUtf8(1)
                    ->setFirstName($billing->getFirstname())
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
                    ->setEmail($email)
                    ->setOrderNo($this->_getOrderId())
                    ->setSystemUrl(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB))
                    ->setSystemIp($helper->getServerAddr())
                    ->setSystemName('magento')
                    ->setSystemVersion($helper->getMagentoVersion() . '-' . $helper->getNovalnetVersion())
                    ->setInput1('order_id')
                    ->setInputval1($this->_getOrderId());

            if ($this->_referrerId) {
                $request->setReferrerId($this->_referrerId);
            }
            if ($refernceOne) {
                $request->setInput2('reference1')
                        ->setInputval2($refernceOne);
            }
            if ($refernceTwo) {
                $request->setInput3('reference2')
                        ->setInputval3($refernceTwo);
            }
            if ($helper->checkIsAdmin()) {
                $adminUserId = Mage::getSingleton('admin/session')->getUser()->getUserId();
                $request->setInput4('admin_user')
                        ->setInputval4($adminUserId);
            }
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
    public function postRequest($request)
    {
        $result = new Varien_Object();
        $helper = $this->helper;
        $paymentKey = $helper->getPaymentId($this->_code);
        $payportUrl = $helper->getPayportUrl('paygate');
        if ($this->_validateBasicParams() && $helper->checkIsNumeric($paymentKey)
                && $paymentKey == $request->getKey() && $request->getAmount() && $helper->checkIsNumeric($request->getAmount())) {
            $response = $this->_setNovalnetRequestCall($request->getData(), $payportUrl);
            parse_str($response->getBody(), $data);
            $result->addData($data);
        } else {
            $this->showException($helper->__('Required parameter not valid') . '!', false);
        }
        return $result;
    }

    /**
     * Do XML call request to server
     *
     * @param Varien_Object $requestData
     * @param String $requestUrl
     * @return Mage_Payment_Model_Abstract Object
     */
    public function setRawCallRequest($requestData, $requestUrl)
    {
        $httpClientConfig = array('maxredirects' => 0);
        $client = new Varien_Http_Client($requestUrl, $httpClientConfig);
        $client->setRawData($requestData)->setMethod(Varien_Http_Client::POST);
        $response = $client->request();
        if (!$response->isSuccessful()) {
            Mage::throwException($this->_getHelper()->__('Gateway request error: %s', $response->getMessage()));
        }
        $result = new Varien_Object();
        parse_str($response->getBody(), $data);
        $result->addData($data);
        return $result;
    }

    /**
     * Assign Novalnet Authentication Data
     *
     * @param Varien_Object $request
     * @param int $storeId
     */
    public function assignNnAuthData(Varien_Object $request, $storeId = NULL)
    {
        //Reassign the Basic Params Based on store
        $this->_vendorId = $this->_getConfigData('merchant_id', true, $storeId);
        $this->_authcode = $this->_getConfigData('auth_code', true, $storeId);

        $this->_manualCheckValidate($storeId);
        $request->setVendor($this->_vendorId)
                ->setAuthCode($this->_authcode)
                ->setProduct($this->_productId)
                ->setTariff($this->_tariffId)
                ->setKey($this->helper->getPaymentId($this->_code));
    }

    /**
     * Set the Order basic configuration
     *
     * @param string $payment
     */
    public function assignVendorConfig($payment = NULL)
    {
        //Reassign the Basic Params Based on store
        $getresponseData = NULL;
        if ($payment) {
            $getresponseData = unserialize($payment->getAdditionalData());
        }
        $this->_vendorId = ($getresponseData['vendor']) ? $getresponseData['vendor']
                    : $this->_vendorId;
        $this->_authcode = ($getresponseData['auth_code']) ? $getresponseData['auth_code']
                    : $this->_authcode;
        $this->_productId = ($getresponseData['product']) ? $getresponseData['product']
                    : $this->_productId;
        $this->_tariffId = ($getresponseData['tariff']) ? $getresponseData['tariff']
                    : $this->_tariffId;
    }

    /**
     * Refund amount
     *
     * @param   Varien_Object $payment
     * @param   float $amount
     * @return  Mage_Payment_Model_Abstract
     */
    public function refund(Varien_Object $payment, $amount)
    {
        if (!$this->canRefund()) {
            $this->showException('Refund action is not available.');
        }

        if ($payment->getRefundTransactionId() && $amount > 0) {
            $helper = $this->helper;
            $refundAmount = $helper->getFormatedAmount($amount);
            $getTid = $helper->makeValidNumber($payment->getRefundTransactionId());
            $data = unserialize($payment->getAdditionalData());
            if ($this->_code == Novalnet_Payment_Model_Config::NN_SEPA) {
                $getTid = $helper->makeValidNumber($payment->getLastTransId());
                if (isset($data['NnSepaParentTid']) && $data['NnSepaParentTid']) {
                    $getTid = $data['NnSepaParentTid'];
                }
            }

            if ($this->_code == Novalnet_Payment_Model_Config::NN_INVOICE) {
                $this->_refundValidation($payment, $refundAmount);
            }
            //Send refund request to Payport
            $response = $this->_performPayportRequest($payment, 'refund', $refundAmount, $getTid);

            if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                $getTransactionStatus = $this->doNovalnetStatusCall($getTid, $payment, Novalnet_Payment_Model_Config::TRANS_STATUS);
                $loadTransaction = $helper->loadTransactionStatus($getTid);
                $loadTransaction->setTransactionStatus($getTransactionStatus->getStatus())
                        ->setAmount($helper->getFormatedAmount($getTransactionStatus->getAmount(), 'RAW'))
                        ->save();
                $data['fullRefund'] = ((string)$this->_getAmount() == (string)$amount) ? true : false;
                $txnid = $response->getTid(); // response tid
                $refundTid = !empty($txnid) ? $txnid : $payment->getLastTransId() . '-refund';

                if (in_array($this->_code, array(Novalnet_Payment_Model_Config::NN_INVOICE,
                            Novalnet_Payment_Model_Config::NN_PREPAYMENT))) {
                    $amountAfterRefund = ($this->_getAmount() - $payment->getOrder()->getBaseTotalRefunded());
                    $loadTransaction->setAmount($amountAfterRefund)
                            ->save();
                    $data['NnTid'] = $data['NnTid'] . '-refund';
                    $refundTid = $data['NnTid'];
                }

                if ($refundTid) {
                    $refAmount = Mage::helper('core')->currency($amount, true, false);
                    if (!isset($data['refunded_tid'])) {
                        $refundedTid = array('refunded_tid'=> array($refundTid => array('reftid' => $refundTid , 'refamount' => $refAmount , 'reqtid' => $getTid)));
                        $data = array_merge($data, $refundedTid);
                    } else {
                        $data['refunded_tid'][$refundTid]['reftid'] = $refundTid;
                        $data['refunded_tid'][$refundTid]['refamount'] = $refAmount;
                        $data['refunded_tid'][$refundTid]['reqtid'] = $getTid;
                    }
                }

                // For SEPA payment after submitting to bank
                if ($getTransactionStatus->getStatus() && $this->_code == Novalnet_Payment_Model_Config::NN_SEPA) {
                    $data['NnSepaParentTid'] = $getTid;
                    if ($getTransactionStatus->getStatus() == Novalnet_Payment_Model_Config::PAYMENT_VOID_STATUS
                        && $getTransactionStatus->getChildTidInfo()) {
                        parse_str($getTransactionStatus->getChildTidInfo(), $resInfo);
                        $data['NnSepaParentTid'] = $resInfo['tid1'];
                    }
                }

                $magentoVersion = $this->_getHelper()->getMagentoVersion();
                // make capture transaction open for lower versions to make refund
                if (version_compare($magentoVersion, '1.6', '<')) {
                    $order = $payment->getOrder();
                    $canRefundMore = $order->canCreditmemo();

                    $payment->setTransactionId($refundTid)
                            ->setLastTransId($refundTid)
                            ->setAdditionalData(serialize($data))
                            ->setIsTransactionClosed(1) // refund initiated by merchant
                            ->setShouldCloseParentTransaction(!$canRefundMore)
                            ->save();
                } else {
                    $payment->setTransactionId($refundTid)
                            ->setLastTransId($refundTid)
                            ->setAdditionalData(serialize($data))
                            ->save();
                }

                if ($txnid) { // Only log the novalnet transaction which contains TID
                    if ($this->_code == Novalnet_Payment_Model_Config::NN_SEPA) {
                        $amount = ($this->_getAmount() - $payment->getOrder()->getBaseTotalRefunded());
                    }
                    $getTransactionStatus = $this->doNovalnetStatusCall($txnid, $payment, Novalnet_Payment_Model_Config::TRANS_STATUS);
                    $response->setStatus($getTransactionStatus->getStatus());
                    $helper->doTransactionStatusSave($response, $getTransactionStatus, $payment, $amount, $loadTransaction->getCustomerId);
                }
            } else {
                $this->showException($response->getStatusDesc(), false);
            }
        } else {
            $this->showException('Error in you refund request');
        }
        return $this;
    }

    /**
     * refund validation for InvoicePayment
     *
     * @return  boolean
     */
    private function _refundValidation($payment, $refundAmount)
    {
        $orderId = $payment->getOrder()->getIncrementId();
        $callbackTrans = $this->helper->loadCallbackValue($orderId);
        $callbackValue = $callbackTrans && $callbackTrans->getCallbackAmount() != NULL
                    ? $callbackTrans->getCallbackAmount() : '';
        $currency = Mage::app()->getLocale()->currency($this->_getInfoObject()->getBaseCurrencyCode())->getSymbol();
        if ($callbackValue < (string) $refundAmount) {
            $this->showException('Maximum amount available to refund is ' . $currency . $this->helper->getFormatedAmount($callbackValue, 'RAW'));
        }
        $refundedAmount = $this->helper->getFormatedAmount($payment->getAmountRefunded());
        $totalrefundAmount = $refundedAmount + $refundAmount;
        $availAmount = $callbackValue - $refundedAmount;
        if ($payment->getAmountRefunded() && $callbackValue < (string) $totalrefundAmount) {
            $this->showException('Maximum amount available to refund is ' . $currency . $this->helper->getFormatedAmount($availAmount, 'RAW'));
        }
        return true;
    }

    /**
     * Perform Novalnet request for Void, Capture, Refund
     *
     * @param   Varien_Object $payment
     * @return  $response
     */
    private function _performPayportRequest($payment, $requestType,
        $refundAmount = NULL, $refundTid = NULL
    ) {
        $request = new Varien_Object();
        $helper = $this->helper;
        $storeId = $helper->getMagentoStoreId();
        $customerId = $payment->getOrder()->getCustomerId();
        $getTid = ($requestType == 'refund') ? $refundTid : $payment->getLastTransId();
        $this->assignVendorConfig($payment);
        $getresponseData = unserialize($payment->getAdditionalData());
        $key = ($getresponseData['key']) ? $getresponseData['key'] : $helper->getPaymentId($this->_code);
        $request->setVendor($this->_vendorId)
                ->setAuthCode($this->_authcode)
                ->setProduct($this->_productId)
                ->setTariff($this->_tariffId)
                ->setKey($key)
                ->setTid($helper->makeValidNumber($getTid));
        if ($requestType == 'void' || $requestType == 'capture') {
            $request->setEditStatus(true);
            $setStatus = ($requestType == 'capture') ? Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
                        : Novalnet_Payment_Model_Config::PAYMENT_VOID_STATUS;
            $request->setStatus($setStatus);
        } else {
            $request->setRefundRequest(true)
                    ->setRefundParam($refundAmount);
        }
        if (!in_array(NULL, $request->toArray())) {
            $buildNovalnetParam = http_build_query($request->getData());
            $payportUrl = $helper->getPayportUrl('paygate');
            $response = $this->setRawCallRequest($buildNovalnetParam, $payportUrl);
            $this->logNovalnetTransactionData($request, $response, $payment->getLastTransId(), $customerId, $storeId);
        } else {
            $this->showException('Error in processing the transactions request');
        }
        return $response;
    }

    /**
     * Void payment
     *
     * @param   Varien_Object $payment
     * @return  Mage_Payment_Model_Abstract
     */
    public function void(Varien_Object $payment)
    {
        if (!$this->canVoid($payment)) {
            $this->showException('Void action is not available.');
        }

        //Send void request to Payport
        $response = $this->_performPayportRequest($payment, 'void');

        if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {

            $txnid = $response->getTid();
            $voidTid = !empty($txnid) ? $txnid : $payment->getLastTransId() . '-void';
            $invoicePayment = (in_array($this->_code, array(Novalnet_Payment_Model_Config::NN_INVOICE,
                        Novalnet_Payment_Model_Config::NN_PREPAYMENT)));
            $data = unserialize($payment->getAdditionalData());
            $data['voidTid'] = $voidTid;
            if ($invoicePayment) {
                $bankVoidTid = !empty($txnid) ? $txnid : $payment->getLastTransId();
                $data['NnNoteTID'] = $this->dataHelper->getBankDetailsTID($bankVoidTid);
            }

            $payment->setTransactionId($voidTid)
                    ->setLastTransId($voidTid)
                    ->setAdditionalData(serialize($data))
                    ->save();
            $getTid = $this->helper->makeValidNumber($payment->getLastTransId());
            $getTransactionStatus = $this->doNovalnetStatusCall($getTid, $payment, Novalnet_Payment_Model_Config::TRANS_STATUS);
            $transAmount = $getTransactionStatus->getAmount();
            if ($invoicePayment) {
                $transAmount = $this->helper->getFormatedAmount($getTransactionStatus->getAmount(), 'RAW');
            }
            $loadTransaction = $this->helper->loadTransactionStatus($getTid);
            $loadTransaction->setTransactionStatus($getTransactionStatus->getStatus())
                    ->setAmount($transAmount)
                    ->save();
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
    private function _getMethodSession($paymentCode = NULL)
    {
        $paymentCode = (!empty($paymentCode)) ? $paymentCode : $this->getCode();
        $checkoutSession = $this->_getCheckout();
        if (!$checkoutSession->hasData($paymentCode)) {
            $checkoutSession->setData($paymentCode, new Varien_Object());
        }
        return $checkoutSession->getData($paymentCode);
    }

    /**
     * Unset method session
     *
     * @return  method session
     */
    public function unsetMethodSession($paymentCode = NULL)
    {
        $paymentCode = (!empty($paymentCode)) ? $paymentCode : $this->getCode();
        $checkoutSession = $this->_getCheckout();
        $checkoutSession->unsetData($paymentCode);
        if ($checkoutSession->getNnPaymentCode()) {
            $checkoutSession->unsNnPaymentCode();
        }
        return $this;
    }

    /**
     * Get the novalnet configuration data
     *
     * @return mixed
     */
    public function _getConfigData($field, $globalMode = false, $storeId = NULL)
    {
        $storeId = is_null($storeId) ? $this->helper->getMagentoStoreId() : $storeId;
        $path = $this->helper->getNovalnetGlobalPath() . $field;
        if ($field == 'live_mode') {
            $getTestmodePayments = Mage::getStoreConfig($path, $storeId);
            if (!preg_match('/' . $this->_code . '/i', $getTestmodePayments)) {
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
    public function doNovalnetPostbackCall($response)
    {
        //Required Parameters to be passed to the Server
        $request = $result = array();
        $helper = $this->helper;
        $paymentKey = $helper->getPaymentId($this->_code);
        $payportUrl = $helper->getPayportUrl('paygate');
        if ($this->_validateBasicParams() && $this->helper->checkIsNumeric($paymentKey)) {
            $request = array(
                'vendor' => $this->_vendorId,
                'auth_code' => $this->_authcode,
                'product' => $this->_productId,
                'tariff' => $this->_tariffId,
                'key' => $paymentKey,
                'tid' => $response->getTid(),
                'status' => Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED,
                'order_no' => $this->_getOrderId()
            );
            if ($request['key'] == 27) {
                $request['invoice_ref'] = 'BNR-' . $request['product'] . '-' . $request['order_no'];
            }
            $result = new Varien_Object();
            $response = $this->_setNovalnetRequestCall($request, $payportUrl);
            $result = $response->getRawBody();
        }
        return $result;
    }

    /**
     * Check the transaction status using API
     *
     * @param array  $requestData
     * @param string $requestUrl
     * @param string $type
     *
     * @return mixed
     */
    private function _setNovalnetRequestCall($requestData, $requestUrl, $type = "")
    {
        if ($requestUrl == "") {
            $this->showException('Server Request URL is Empty');
            return;
        }
        $httpClientConfig = array('maxredirects' => 0);
        if (((int) $this->_getConfigData('gateway_timeout')) > 0) {
            $httpClientConfig['timeout'] = (int) $this->_getConfigData('gateway_timeout');
        }
        $client = new Varien_Http_Client($requestUrl, $httpClientConfig);

        if ($type == 'XML') {
            $client->setUri($requestUrl);
            $client->setRawData($requestData)->setMethod(Varien_Http_Client::POST);
        } else {
            $client->setParameterPost($requestData)->setMethod(Varien_Http_Client::POST);
        }
        $response = $client->request();
        if (!$response->isSuccessful()) {
            $this->showException($this->helper->__('Gateway request error: %s', $response->getMessage()), false);
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
    private function _setNovalnetParam(Varien_Object $request, $paymentCode)
    {
        $helper = $this->helper;
        $dataHelper = $this->dataHelper;
        if ($paymentCode) {
            $methodSession = $this->_getCheckout()->getData($paymentCode);
            switch ($paymentCode) {
                case Novalnet_Payment_Model_Config::NN_INVOICE:
                    $paymentDuration = trim($this->_getConfigData('payment_duration'));
                    $dueDate = $helper->setDueDate($paymentDuration);
                    if ($dueDate) {
                        $request->setDueDate($dueDate);
                    }
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

                    $this->dataHelper->assignNovalnetReturnData($request, $this->_code);
                    $this->dataHelper->importNovalnetEncodeData($request, $this->_password);
                    break;
                case Novalnet_Payment_Model_Config::NN_CC:
                    $request->setCcHolder($methodSession->getNnCcOwner())
                            ->setCcNo()
                            ->setCcExpMonth($methodSession->getNnCcExpMonth())
                            ->setCcExpYear($methodSession->getNnCcExpYear())
                            ->setCcCvc2($methodSession->getNnCcCvc())
                            ->setCcType($methodSession->getNnCcType())
                            ->setPanHash($methodSession->getCcPanHash())
                            ->setUniqueId($methodSession->getCcUniqueId());
                    if ($this->_code == Novalnet_Payment_Model_Config::NN_CC && !$helper->checkIsAdmin()
                            && $this->_getConfigData('active_cc3d') == 1) {
                        $this->_password = $this->_getConfigData('password', true);
                        $amount = $helper->getFormatedAmount($this->_getAmount());
                        $request->setCountryCode($request->getCountry())
                                ->setSession(session_id())
                                ->setencodedAmount($helper->getEncodedParam($amount, $this->_password));
                        $dataHelper->assignNovalnetReturnData($request, $this->_code);
                        $request->unsUserVariable_0(); // Unset uservariable as CC3D doesnot requires it
                    }
                    break;
                case Novalnet_Payment_Model_Config::NN_SEPA:
                    $request->setBankAccountHolder($methodSession->getSepaHolder())
                            ->setBankAccount()
                            ->setBankCode()
                            ->setBic()
                            ->setIban()
                            ->setSepaHash($methodSession->getSepaHash())
                            ->setSepaUniqueId($methodSession->getSepaUniqueId())
                            ->setIbanBicConfirmed($methodSession->getIbanConfirmed());

                    $paymentDuration = trim($this->_getConfigData('sepa_due_date'));
                    $dueDate = (!$paymentDuration) ? date('Y-m-d', strtotime('+7 days'))
                                : date('Y-m-d', strtotime('+' . $paymentDuration . ' days'));
                    $request->setSepaDueDate($dueDate);

                    break;
            }
        }
        return $request;
    }

    /**
     * Validate the novalnet response
     *
     * @param object $payment
     *
     * @return mixed
     */
    public function validateNovalnetResponse($payment, $result = NULL)
    {
        $order = $payment->getOrder();
        $request = $this->_getMethodSession()->getPaymentReqData();
        $orderId = isset($result) && $result->getOrderNo() ? $result->getOrderNo()
                    : $order->getIncrementId();

        switch ($result->getStatus()) {
            case Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED:
                $txnId = trim($result->getTid());
                $data = $this->_setPaymentAddtionaldata($result);
                /** @@ Update the transaction status and transaction overview * */
                $payment->setStatus(self::STATUS_APPROVED)
                        ->setAdditionalData(serialize($data))
                        ->save();
                $order->setPayment($payment);
                $order->save();

                $getTransactionStatus = $this->doNovalnetStatusCall($txnId, $payment);
                $result->setStatus($getTransactionStatus->getStatus());
                $this->helper->doTransactionStatusSave($result, $getTransactionStatus, $payment);
                // check magento version
                $magentoVersion = $this->_getHelper()->getMagentoVersion();
                $captureMode = (version_compare($magentoVersion, '1.6', '<')) ? false
                            : true;
                $responseCodeApproved = ($getTransactionStatus->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED);

                // Capture the payment only if status is 100
                if ($order->canInvoice() && $responseCodeApproved && $this->_code
                        != Novalnet_Payment_Model_Config::NN_PREPAYMENT) {
                    $payment->setTransactionId($txnId) // Add capture text to make the new transaction
                            ->setIsTransactionClosed($captureMode) // Close the transaction
                            ->capture(null)
                            ->save();
                    $orderStatus = $this->_getConfigData('order_status')
                                   ? $this->_getConfigData('order_status') : Mage_Sales_Model_Order::STATE_PROCESSING; // If after status is empty set default status
                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $orderStatus, $this->helper->__('Payment was successful.'), true);
                }
                $payment->setTransactionId($txnId)
                        ->setLastTransId($txnId)
                        ->setParentTransactionId(null)
                        ->save();
                $order->setPayment($payment);
                $order->save();

                $this->doNovalnetPostbackCall($result); //Second Call
                if (!$this->isCallbackTypeCall()) {
                    $this->logNovalnetTransactionData($request, $result, $txnId, $this->helper->getCustomerId(), $this->helper->getMagentoStoreId());
                }
                $statusText = ($result->getStatusText()) ? $result->getStatusText()
                            : $this->helper->__('successful');
                Mage::getSingleton('core/session')->addSuccess($statusText);
                $error = false;
                break;
            default:
                $error = ($result->getStatusDesc()) ? $this->helper->htmlEscape($result->getStatusDesc())
                            : $this->helper->__('Error in capturing the payment');
                if (!$this->isCallbackTypeCall()) {
                    $this->logNovalnetTransactionData($request, $result, $result->getTid(), $this->helper->getCustomerId(), $this->helper->getMagentoStoreId());
                }
                if ($error !== false) {
                    $this->saveCancelledOrder($result, $payment);
                    Mage::getSingleton('core/session')->addError($error);
                }
                break;
        }
        return $error;
    }

    /**
     * payment method cancellation process
     *
     */
    public function saveCancelledOrder($result, $payment)
    {
        $order = $payment->getOrder();

        $statusMessage = $result->getStatusMessage() ?  $result->getStatusMessage() : ($result->getStatusDesc() ?  $result->getStatusDesc()
                        : ($result->getStatusText() ?  $result->getStatusText() : $this->helper->__('Payment was not successfull')));

        $paystatus = "<b><font color='red'>" . $this->helper->__('Payment Failed') . "</font> - " . $statusMessage . "</b>";
        $data = unserialize($payment->getAdditionalData());
        //set Novalnet Mode
        $authorizeKey = $this->_getConfigData('password', true);
        $serverResponse = ($result->getTestMode() && is_numeric($result->getTestMode()))
                    ? $result->getTestMode()
                    : $this->helper->getDecodedParam($result->getTestMode(), $authorizeKey);
        $shopMode = $this->_getConfigData('live_mode', true);
        $testMode = (((isset($serverResponse) && $serverResponse == 1) || (isset($shopMode)
                && $shopMode == 0)) ? 1 : 0 );
        $data['NnTestOrder'] = $testMode;
        $data['NnComments'] = $paystatus;
        $payment->setLastTransId($result->getTid())
                ->setAdditionalData(serialize($data))
                ->save();
        $order->registerCancellation($statusMessage)
                ->save();
    }

    /**
     * Set Payment method additional informations
     *
     * @return array
     */
    private function _setPaymentAddtionaldata($result)
    {
        $txnId = trim($result->getTid());
        $request = $this->_getMethodSession()->getPaymentReqData();
        //set Novalnet Mode
        $responseTestMode = $result->getTestMode();
        $shopMode = $this->_getConfigData('live_mode');
        $testMode = (((isset($responseTestMode) && $responseTestMode == 1) || (isset($shopMode)
                && $shopMode == 0)) ? 1 : 0 );
        $data = array('NnTestOrder' => $testMode,
            'NnTid' => $txnId,
            'vendor' => ($request->getVendor()) ? $request->getVendor() : $this->_vendorId,
            'auth_code' => ($request->getAuthCode()) ? $request->getAuthCode() : $this->_authcode,
            'product' => ($request->getProduct()) ? $request->getProduct() : $this->_productId,
            'tariff' => ($request->getTariff()) ? $request->getTariff() : $this->_tariffId,
            'key' => ($request->getKey()) ? $request->getKey() : $this->helper->getPaymentId($this->_code),
        );
        if (in_array($this->_code, array(Novalnet_Payment_Model_Config::NN_INVOICE,
                    Novalnet_Payment_Model_Config::NN_PREPAYMENT))) {
            $data['NnNote'] = $this->dataHelper->getNote($result);
            $data['NnNoteAmount'] = $this->dataHelper->getBankDetailsAmount($result->getAmount());
            $data['NnNoteTID'] = $this->dataHelper->getBankDetailsTID($txnId);
        }
        return $data;
    }

    /**
     * Make telephone payment first call and display the messages
     *
     */
    public function doNovalnetPhoneFirstCall()
    {
        $methodSession = $this->_getMethodSession();
        $helper = $this->helper;
        $phoneTid = $methodSession->getNnPhoneTid();
        if ($this->_code == Novalnet_Payment_Model_Config::NN_TELEPHONE) {
            if (!$this->checkAmountAllowed()) {
                return false;
            }
            if ($phoneTid) {
                $this->_validateSession();
            }
            $methodSession = $this->_getMethodSession();    // Reassign method session after values unset
            if (!$phoneTid) {
                //invoke first call
                $storeId = $helper->getMagentoStoreId();
                $request = $this->buildRequest(Novalnet_Payment_Model_Config::POST_NORMAL, $storeId);
                $result = $this->postRequest($request);
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
                    $text .= '* ' . $helper->__('This call will cost') . ' ' . str_replace('.', ',', $result->getAmount()) . " " . Mage::app()->getLocale()->currency($result->getCurrency())->getSymbol() . ' (' . $helper->__('including VAT') . ') ';
                    $text .= $helper->__('and it is possible only for German landline connection') . '!*' . "\n\n";
                    $text .= $helper->__('Step') . ' 2: ';
                    $text .= $helper->__('Please wait for the beep and then hang up the listeners') . '.' . "\n";
                    $text .= $helper->__('After your successful call, please proceed with the payment') . '.' . "\n";

                    $this->text = $text;
                    $this->showException($text, false); #show note for client to call...
                } else {
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
    public function validateSecondCallResponse($result, $payment, $paymentCode)
    {
        $status = $result->getStatus();
        switch ($status) {
            case Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED:
                $serverResponse = $this->_getCheckout()->getNnTelTestOrder();
                $shopMode = $this->_getConfigData('live_mode');
                $testMode = (((isset($serverResponse) && $serverResponse == 1) || (isset($shopMode)
                        && $shopMode == 0)) ? 1 : 0 );
                $data = array('NnTestOrder' => $testMode);

                $payment->setStatus(self::STATUS_APPROVED)
                        ->setTransactionId($result->getTid())
                        ->setLastTransId($result->getTid())
                        ->setAdditionalData(serialize($data))
                        ->save();
                $this->doNovalnetPostbackCall($result);
                $getTransactionStatus = $this->doNovalnetStatusCall($result->getTid());
                $result->setStatus($getTransactionStatus->getStatus());  // Combine Transaction status in result object
                $this->helper->doTransactionStatusSave($result, $getTransactionStatus, $payment);
                $statusText = ($result->getStatusText()) ? $result->getStatusText()
                            : $this->helper->__('successful');
                Mage::getSingleton('core/session')->addSuccess($statusText);
                $error = false;
                break;
            default:
                $error = ($result->getStatusDesc()) ? $this->helper->htmlEscape($result->getStatusDesc())
                            : $this->helper->__('Error in capturing the payment');
                if ($error !== false) {
                    $this->saveCancelledOrder($result, $payment);
                    Mage::getSingleton('core/session')->addError($error);
                }
                break;
        }
        return $error;
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
    public function doNovalnetStatusCall($tid, $payment = NULL, $reqType = Novalnet_Payment_Model_Config::TRANS_STATUS,
        $extraOption = NULL, $requestData = NULL
    ) {

        $requestType = ($reqType == Novalnet_Payment_Model_Config::TRANS_STATUS)
                    ? Novalnet_Payment_Model_Config::TRANS_STATUS : $reqType;
        if ($payment != NULL) {
            $this->assignVendorConfig($payment);
        } else {
            $this->_manualCheckValidate();
        }

        $request = '<?xml version="1.0" encoding="UTF-8"?>';
        $request .= '<nnxml><info_request>';
        $request .= '<vendor_id>' . $this->_vendorId . '</vendor_id>';
        $request .= '<vendor_authcode>' . $this->_authcode . '</vendor_authcode>';
        $request .= '<request_type>' . $requestType . '</request_type>';
        $request .= '<product_id>' . $this->_productId . '</product_id>';
        $request .= '<tid>' . $tid . '</tid>' . $extraOption;
        $request .= '</info_request></nnxml>';

        if ($this->_vendorId && $this->_authcode && $this->_productId) {
            $infoRequestUrl = $this->helper->getPayportUrl('infoport');
            $result = $this->_setNovalnetRequestCall($request, $infoRequestUrl, 'XML');

            if ($requestType == 'NOVALTEL_STATUS') {
                $requestData->setData($request);
            }
            return $result;
        }
    }

    /**
     * Check whether the cart amount and response server API amount is  valid
     *
     * @return void
     */
    private function _validateSession()
    {
        $methodSession = $this->_getMethodSession();
        if ($methodSession->hasNnPhoneTid()) {
            if ($methodSession->getOrderAmount() != $this->helper->getFormatedAmount($this->_getAmount())) {
                $this->unsetMethodSession();
                $this->showException($this->helper->__('You have changed the order amount after receiving telephone number'));
            }
        }
    }

    /**
     * Get checkout session
     *
     * @return Mage_Sales_Model_Order
     */
    protected function _getCheckout()
    {
        if ($this->helper->checkIsAdmin()) {
            return $this->helper->_getAdminCheckoutSession();
        } else {
            return $this->helper->_getCheckoutSession();
        }
    }

    /**
     * Check whether the cart amount allowed for this payment
     * Amount between 0.99 & 10.00 EUR
     *
     * @return bool
     */
    public function checkAmountAllowed()
    {
        $amount = $this->helper->getFormatedAmount($this->_getAmount());
        if ($amount >= Novalnet_Payment_Model_Config::PHONE_PAYMENT_AMOUNT_MIN and $amount
                <= Novalnet_Payment_Model_Config::PHONE_PAYMENT_AMOUNT_MAX) {
            return true;
        }
        $this->showException($this->helper->__('Amounts below 0,99 Euros and above 10,00 Euros cannot be processed and are not accepted') . '!', false);
        return false;
    }

    /**
     * Post request to gateway to Load CC Iframe
     *
     * @return Varien_Object
     */
    public function getNovalnetIframe()
    {
        $helper = $this->helper;
        $amount = ($helper->checkIsAdmin()) ? $helper->_getAdminCheckoutSession()->getQuote()->getBaseGrandTotal()
                    : $helper->_getCheckoutSession()->getQuote()->getBaseGrandTotal();
        $storeId = $helper->getMagentoStoreId();
        $methodSession = $this->_getMethodSession($this->_code);
        $this->_manualCheckValidate($storeId, $amount);
        //Required Parameters to be passed to the Server
        $request = array();

        if ($this->_code == Novalnet_Payment_Model_Config::NN_SEPA) {
            $billing = $this->_getCheckout()->getQuote()->getBillingAddress();
            $sepaRefill = $this->_getConfigData('sepa_refill');
            $url = $helper->getPayportUrl('sepa');
            $request = array(
                'lang' => strtoupper(substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2)),
                'vendor_id' => $this->_vendorId,
                'product_id' => $this->_productId,
                'payment_id' => $helper->getPaymentId($this->_code),
                'country' => $billing->getCountryId(),
                'authcode' => $this->_authcode,
                'panhash' => $sepaRefill ? $methodSession->getSepaHash() : '',
                'fldVdr' => ($sepaRefill && $methodSession->getSepaHash() && $methodSession->getSepaFieldValidator())
                            ? $methodSession->getSepaFieldValidator() : '',
                'name' => utf8_decode($billing->getFirstname()) . ' ' . utf8_decode($billing->getLastname()),
                'comp' => utf8_decode($billing->getCompany()),
                'address' => utf8_decode(implode(',', $billing->getStreet())),
                'zip' => $billing->getPostcode(),
                'city' => utf8_decode($billing->getCity()),
                'email' => $this->_getCheckout()->getQuote()->getCustomerEmail()
            );
        } else {
            $ccRefill = $this->_getConfigData('cc_refill');
            $url = $helper->getPayportUrl('cc');
            $request = array(
                'nn_lang_nn' => strtoupper(substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2)),
                'nn_vendor_id_nn' => $this->_vendorId,
                'nn_authcode_nn' => $this->_authcode,
                'nn_product_id_nn' => $this->_productId,
                'nn_payment_id_nn' => $helper->getPaymentId($this->_code),
                'nn_hash' => $ccRefill ? $methodSession->getCcPanHash() : '',
                'fldVdr' => ($ccRefill && $methodSession->getCcPanHash() && $methodSession->getCcFieldValidator())
                            ? $methodSession->getCcFieldValidator() : '',
            );
        }
        if ($this->_vendorId && $this->_productId) {
            $response = $this->_setNovalnetRequestCall($request, $url);
            $result = $response->getRawBody();
        } else {
            $result = $helper->__('Basic parameter not valid');
        }
        return $result;
    }

    /**
     * validate novalnet params to proceed checkout
     *
     * @return bool
     */
    public function validateNovalnetParams()
    {
        $infoObjBilling = $this->_getInfoObject()->getBillingAddress();
        $quoteBilling = $this->_getCheckout()->getQuote()->getBillingAddress();
        $cusEmail = ($quoteBilling->getEmail()) ? $quoteBilling->getEmail() : $this->_getInfoObject()->getCustomerEmail();
        $cusFirstname = ($quoteBilling->getFirstname()) ? $quoteBilling->getFirstname()
                    : $infoObjBilling->getFirstname();
        $cusLastname = ($quoteBilling->getLastname()) ? $quoteBilling->getLastname()
                    : $infoObjBilling->getLastname();
        $manualCheckAmt = (int) $this->_getConfigData('manual_checking_amount');
        $helper = $this->helper;
        if (!$this->_validateBasicParams()) {
            $this->showException($helper->__('Basic parameter not valid') . '!', false);
            return false;
        } elseif ($manualCheckAmt && (!$helper->checkIsNumeric($manualCheckAmt) || !$helper->checkIsNumeric($this->_getConfigData('second_product_id'))
                || !$helper->checkIsNumeric($this->_getConfigData('second_tariff_id')))) {
            $this->showException($helper->__('Manual limit amount / Product-ID2 / Tariff-ID2 is not valid') . '!', false);
            return false;
        } elseif (!$helper->validateEmail($cusEmail) || !$cusFirstname || !$cusLastname) {
            $this->showException($helper->__('Customer name/email fields are not valid') . '!', false);
            return false;
        }
        return true;
    }

    /**
     * validate manual check-limit and reassign product id and tariff id
     *
     * @param integer $storeId
     * @param float $amount
     */
    private function _manualCheckValidate($storeId = NULL, $amount = NULL)
    {
        $amount = $this->helper->getFormatedAmount(($amount) ? $amount : $this->_getAmount());
        $storeId = is_null($storeId) ? $this->helper->getMagentoStoreId() : $storeId;
        $manualCheckAmt = (int) $this->_getConfigData('manual_checking_amount', false, $storeId);
        $this->_productId = (strlen(trim($this->_getConfigData('second_product_id', false, $storeId)))
                && $manualCheckAmt && $manualCheckAmt <= $amount) ? trim($this->_getConfigData('second_product_id', false, $storeId))
                    : trim($this->_getConfigData('product_id', true, $storeId));
        $this->_tariffId = (strlen(trim($this->_getConfigData('second_tariff_id', false, $storeId)))
                && $manualCheckAmt && $manualCheckAmt <= $amount) ? trim($this->_getConfigData('second_tariff_id', false, $storeId))
                    : trim($this->_getConfigData('tariff_id', true, $storeId));
    }

    /**
     * validate novalnet basic params
     *
     * @return bool
     */
    private function _validateBasicParams()
    {
        $helper = $this->helper;
        if ($helper->checkIsNumeric($this->_vendorId) && $this->_authcode && $helper->checkIsNumeric($this->_productId)
                && $helper->checkIsNumeric($this->_tariffId)) {
            return true;
        }
        return false;
    }

    /**
     * Check whether callback option is enabled
     *
     */
    public function isCallbackTypeCall()
    {
        $callbackTid = "hasNnCallbackTid" . ucfirst($this->_code);
        $total = $this->helper->getFormatedAmount($this->_getAmount());
        $callBackMinimum = (int) $this->_getConfigData('callback_minimum_amount');
        $countryCode = strtoupper($this->_getInfoObject()->getBillingAddress()->getCountryId());

        return $this->helper->_getCheckoutSession()->$callbackTid() || ($this->_getConfigData('callback')
                && ($callBackMinimum ? $total >= $callBackMinimum : true) && ($this->helper->isCallbackTypeAllowed($countryCode)));
    }

    /**
     * Initiate callback process after selecting payment method
     *
     * @return bool
     */
    private function _initiateCallbackProcess($paymentCode)
    {
        $infoInstance = $this->getInfoInstance();
        $isCallbackTypeCall = $this->isCallbackTypeCall();
        $isPlaceOrder = $this->_isPlaceOrder();

        $payCode = ucfirst($paymentCode);
        $callbackTid = "getNnCallbackTid" . $payCode;
        $callbackOrderNo = "getNnCallbackOrderNo" . $payCode;
        $callbackPin = "getNnCallbackPin" . $payCode;
        $callbackNewPin = "getNnNewCallbackPin" . $payCode;
        $setcallbackPin = "setNnCallbackPin" . $payCode;

        if (!$isPlaceOrder && $isCallbackTypeCall && $this->_getIncrementId() && $this->_getMethodSession()->$callbackOrderNo()
            && ($this->_getIncrementId() != $this->_getMethodSession()->$callbackOrderNo())) {
            $this->unsetMethodSession();
        }
        //validate callback session
        $this->_validateCallbackSession();
        $methodSession = $this->_getMethodSession();
        if ($isCallbackTypeCall && $infoInstance->getCallbackPinValidationFlag()
                && $methodSession->$callbackTid()) {
            $nnCallbackPin = $infoInstance->$callbackPin();
            if (!$infoInstance->$callbackNewPin() && (!$this->helper->checkIsValid($nnCallbackPin)
                    || empty($nnCallbackPin))) {
                $this->showException('PIN you have entered is incorrect or empty!');
            }
        }
        if (!$isPlaceOrder && $isCallbackTypeCall && $this->_getConfigData('callback')
                != 3) {
            if ($methodSession->$callbackTid()) {
                if ($infoInstance->$callbackNewPin()) {
                    $this->_regenerateCallbackPin();
                } else {
                    $methodSession->$setcallbackPin($infoInstance->$callbackPin());
                }
            } else {
                $this->_generateCallback();
            }
        } elseif (!$isPlaceOrder && $isCallbackTypeCall && $this->_getConfigData('callback')
                == 3) {

            if (!$methodSession->$callbackTid()) {
                $this->_generateCallback();
            }
        }
        if ($isPlaceOrder) {
            $this->_validateCallbackProcess();
        }
    }

    /**
     * validate order amount is getting changed after callback initiation
     *
     * throw Mage Exception
     */
    private function _validateCallbackSession()
    {
        $payCode = ucfirst($this->_code);
        $callbackTid = "hasNnCallbackTid" . $payCode;
        $getNnDisableTime = "getNnDisableTime" . $payCode;
        $methodSession = $this->_getMethodSession();
        if ($methodSession->$callbackTid()) {
            if ($methodSession->$getNnDisableTime() && time() > $methodSession->$getNnDisableTime()) {
                $this->unsetMethodSession();
            } elseif ($methodSession->getOrderAmount() != $this->helper->getFormatedAmount($this->_getAmount())) {
                $this->unsetMethodSession();
                if (!$this->_isPlaceOrder() && $this->_getConfigData('callback')
                        != 3) {
                    $this->showException('You have changed the order amount after getting PIN number, please try again with a new call');
                } else if (!$this->_isPlaceOrder() && $this->_getConfigData('callback')
                        == 3) {
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
    private function _getIncrementId()
    {
        $storeId = $this->_getHelper()->getMagentoStoreId();
        $orders = Mage::getModel('sales/order')->getCollection()
                ->addAttributeToFilter('store_id', $storeId)
                ->setOrder('entity_id', 'DESC');
        $lastIncrementId = $orders->getFirstItem()->getIncrementId();
        if ($lastIncrementId) {
            $incrementId = ++$lastIncrementId;
        } else {
            $incrementId = $storeId . Mage::getModel('eav/entity_increment_numeric')->getNextId();
        }
        return $incrementId;
    }

    /**
     * Make callback request and validate response
     *
     */
    private function _generateCallback()
    {
        $payCode = ucfirst($this->_code);
        $callbackTid = "setNnCallbackTid" . $payCode;
        $callbackOrderNo = "setNnCallbackOrderNo" . $payCode;

        $request = $this->buildRequest(Novalnet_Payment_Model_Config::POST_CALLBACK);
        $response = $this->postRequest($request);
        $this->logNovalnetTransactionData($request, $response, $response->getTid());
        if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $this->_getMethodSession()
                    ->$callbackTid(trim($response->getTid()))
                    ->setNnTestMode(trim($response->getTestMode()))
                    ->setNnCallbackTidTimeStamp(time())
                    ->setOrderAmount($request->getAmount())
                    ->setNnCallbackSuccessState(true)
                    ->$callbackOrderNo(trim($response->getOrderNo()))
                    ->setPaymentResData($response)
                    ->setPaymentReqData($request);
            if ($this->_getConfigData('callback') == 3) {
                $text = $this->helper->__('Please reply to the e-mail');
            } else {
                $text = $this->helper->__('You will shortly receive a PIN by phone / SMS. Please enter the PIN in the appropriate text box');
            }
        } else {
            $text = $this->helper->htmlEscape($response->getStatusDesc());
        }
        $this->showException($text, false);
    }

    /**
     * Regenerate new pin for callback process
     *
     */
    private function _regenerateCallbackPin()
    {
        $callbackTid = "getNnCallbackTid" . ucfirst($this->_code);
        $methodSession = $this->_getMethodSession();
        $response = $this->doNovalnetStatusCall($methodSession->$callbackTid(), NULL, Novalnet_Payment_Model_Config::TRANSMIT_PIN_AGAIN);
        if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $text = $this->helper->__('You will shortly receive a PIN by phone / SMS. Please enter the PIN in the appropriate text box');
        } else {
            $text = $this->helper->htmlEscape($response->getStatusMessage()); //status_message
        }
        $this->showException($text, false);
    }

    /**
     * validate callback response
     *
     */
    private function _validateCallbackProcess()
    {
        $payCode = ucfirst($this->_code);
        $callbackTid = "getNnCallbackTid" . $payCode;
        $callbackPin = "getNnCallbackPin" . $payCode;
        $setNnDisableTime = "setNnDisableTime" . $payCode;
        $methodSession = $this->_getMethodSession();

        if ($methodSession->getNnCallbackSuccessState()) {
            if ($this->_getConfigData('callback') == 3) {
                $type = Novalnet_Payment_Model_Config::REPLY_EMAIL_STATUS;
                $extraOption = '';
            } else {
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
                $error = ($result->getStatusDesc() || $result->getStatusMessage())
                            ? $this->helper->htmlEscape($result->getStatusMessage() . $result->getStatusDesc())
                            : $this->helper->htmlEscape($result->pinStatus['status_message']);
                $this->showException($error, false);
            }
        }
    }

    /**
     * Log novalnet transaction data
     *
     */
    public function logNovalnetTransactionData($request, $response, $txnId,
        $customerId = NULL, $storeId = NULL
    ) {
        $this->dataHelper->doRemoveSensitiveData($request, $this->_code);
        $shopUrl = ($response->getMemburl()) ? $response->getMemburl() : $this->helper->getCurrentSiteUrl();
        $customerId = ($customerId) ? $customerId : $this->helper->getCustomerId();
        $storeId = ($storeId) ? $storeId : $this->helper->getMagentoStoreId();
        $modNnTransOverview = $this->helper->getModelTransactionOverview();
        $modNnTransOverview->setTransactionId($txnId)
                ->setOrderId($this->_getOrderId())
                ->setRequestData(serialize($request->getData()))
                ->setResponseData(serialize($response->getData()))
                ->setCustomerId($customerId)
                ->setStatus($response->getStatus())
                ->setStoreId($storeId)
                ->setShopUrl($shopUrl)
                ->setCreatedDate($this->helper->getCurrentDateTime())
                ->save();
    }

    /**
     * validate basic params to load Cc & SEPA Iframe
     *
     * @return bool
     */
    public function validateNovalnetIframeParams()
    {
        $helper = $this->helper;
        $amount = $helper->getFormatedAmount($this->_getAmount());
        $manualCheckAmt = (int) $this->_getConfigData('manual_checking_amount');
        if (!$helper->checkIsNumeric($this->_vendorId) || !$this->_authcode || !$helper->checkIsNumeric($this->_productId)) {
            return false;
        } elseif ($manualCheckAmt && (!$helper->checkIsNumeric($manualCheckAmt) || ($amount
                > $manualCheckAmt) && !$helper->checkIsNumeric($this->_getConfigData('second_product_id')))) {
            return false;
        }
        $this->_manualCheckValidate();
        return true;
    }

    /**
     * Get current info-instance
     *
     * @return Mage_Payment_Model_Method_Abstract
     */
    private function _getInfoInstance()
    {
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
    private function _getInfoObject()
    {
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
    private function _isPlaceOrder()
    {
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
    private function _getAmount()
    {
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
    private function _getOrderId()
    {
        $info = $this->_getInfoInstance();
        if ($this->_isPlaceOrder()) {
            return $info->getOrder()->getIncrementId();
        } else {
            return $this->_getIncrementId();
        }
    }

    /**
     * Retrieve model helper
     *
     * @return Novalnet_Paymenthelper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('novalnet_payment');
    }

    /**
     * Retrieve Assign data helper
     *
     * @return Novalnet_Paymenthelper_AssignData
     */
    protected function _getDataHelper()
    {
        return Mage::helper('novalnet_payment/AssignData');
    }

    /**
     * Show exception
     *
     * @param $string
     * @param $lang
     * @return Mage::throwException
     */
    public function showException($string, $lang = true)
    {
        if ($lang) {
            $string = $this->helper->__($string);
        }

        return Mage::throwException($string);
    }

    /**
     * Assign helper utilities needed for the payment process
     *
     */
    public function assignUtilities()
    {
        if (!$this->helper) {
            $this->helper = $this->_getHelper();
        }
        if (!$this->dataHelper) {
            $this->dataHelper = $this->_getDataHelper();
        }
    }

    /**
     * Get redirect URL
     *
     * @return Mage_Paymenthelper_Data
     */
    public function getOrderPlaceRedirectUrl()
    {
        $redirectPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayments');
        if ((in_array($this->_code, $redirectPayment)) || ($this->_code == Novalnet_Payment_Model_Config::NN_CC
                && !$this->helper->checkIsAdmin() && $this->_getConfigData('active_cc3d')
                == 1)) {
            $actionUrl = $this->_getHelper()->getUrl(Novalnet_Payment_Model_Config::GATEWAY_REDIRECT_URL);
        } else {
            $actionUrl = $this->_getHelper()->getUrl(Novalnet_Payment_Model_Config::GATEWAY_DIRECT_URL);
        }
        return $actionUrl;
    }

}
