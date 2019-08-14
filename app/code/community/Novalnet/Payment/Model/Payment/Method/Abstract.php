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
 * Part of the payment module of Novalnet AG
 * https://www.novalnet.de
 * If you have found this script useful a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Copyright (c) Novalnet AG. (https://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Model_Payment_Method_Abstract extends Mage_Payment_Model_Method_Abstract
        implements Mage_Payment_Model_Recurring_Profile_MethodInterface
{
    /**
     * Payment Method features
     * @var bool
     */
    protected $_isGateway = false;
    protected $_canOrder = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseCheckout = true;
    protected $_canSaveCc = false;
    protected $_isInitializeNeeded = false;
    protected $_canManageRecurringProfiles  = true;

    /**
     * TODO: whether a captured transaction may be voided by this gateway
     * This may happen when amount is captured, but not settled
     * @var bool
     */
    protected $_canCancelInvoice = false;

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
        $this->_vendorId = $this->getNovalnetConfig('merchant_id', true);
        $this->_authcode = $this->getNovalnetConfig('auth_code', true);
        $this->_productId = $this->getNovalnetConfig('product_id', true);
        $this->_tariffId = $this->getNovalnetConfig('tariff_id', true);
        $this->_subscribTariffId = $this->getNovalnetConfig('subscrib_tariff_id', true);
        $this->_referrerId = trim($this->getNovalnetConfig('referrer_id', true));
        //Manual Check Limits
        $this->_manualCheckLimit = (int) $this->getNovalnetConfig('manual_checking_amount',true);
    }

    /**
     * Hide the payment method in checkout (without recurring products)
     *
     * @return boolean
     */
    public function canUseCheckout() {
        $quote = Mage::getModel('checkout/cart')->getQuote();
        $redirectPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayments');
        array_push($redirectPayment, Novalnet_Payment_Model_Config::NN_CC);
        if (!empty($quote) && $quote->hasNominalItems()
            && (in_array($this->_code, $redirectPayment)
            || $this->helper->checkIsAdmin())) {
            return false;
        }
        return true;
    }

    /**
     * Validate recurring profile data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @return boolean
     */
    public function validateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
        return true;
    }

    /**
     * Submit recurring profile to the gateway
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param Mage_Payment_Model_Info $paymentInfo
     */
    public function submitRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile, Mage_Payment_Model_Info $paymentInfo)
    {
        if ($profile->getTrialPeriodUnit() && $profile->getTrialPeriodFrequency()) {
            $this->showException($this->helper->__('Trial Billing Cycles are not support novalnet payment'), false);
        }
        $this->helper->getModelRecurring()->getProfileProgress($profile);
    }

    /**
     * Fetch recurring profile details
     *
     * @param string $referenceId
     * @param Varien_Object $result
     * @return boolean
     */
    public function getRecurringProfileDetails($referenceId, Varien_Object $result)
    {
        return true;
    }

    /**
     * Whether can get recurring profile details
     *
     * @return boolean
     */
    public function canGetRecurringProfileDetails()
    {
        return true;
    }

    /**
     * Update recurring profile data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @return boolean
     */
    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
        return true;
    }

    /**
     * Manage status
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfileStatus(Mage_Payment_Model_Recurring_Profile $profile)
    {
        $helper = $this->helper;
        $recuring = $helper->getModelRecurring();
        $orderNo = $recuring->getRecurringOrderNo($profile);
        $order = $recuring->getOrderByIncrementId($orderNo[0]);
        $payment = $order->getPayment();
        $paymentObj = $payment->getMethodInstance();
        $subsId = $payment->getAdditionalInformation('subs_id');
        $customerId = $order->getCustomerId();
        $orderItems = $order->getAllItems();
        $nominalItem = $helper->checkNominalItem($orderItems);
        $getRequest = Mage::app()->getRequest()->getQuery();
        $storeId = $helper->getMagentoStoreId();

        if ($profile->getNewState() == 'canceled') {
            $request = new Varien_Object();
            $paymentObj->assignOrderBasicParams($request, $payment, $storeId, $nominalItem);
            $request->setNnLang(strtoupper($helper->getDefaultLanguage()))
                    ->setCancelSub(1)
                    ->setCancelReason($getRequest['reason'])
                    ->setTid($profile->getReferenceId());
            $buildNovalnetParam = http_build_query($request->getData());
            $recurringCancelUrl = $helper->getPayportUrl('paygate');
            $response = $this->dataHelper->setRawCallRequest($buildNovalnetParam, $recurringCancelUrl, $paymentObj);
            $data = unserialize($payment->getAdditionalData());
            $data['subsCancelReason'] = $getRequest['reason'];
            $payment->setAdditionalData(serialize($data))->save();
            $this->logNovalnetTransactionData($request, $response, $profile->getReferenceId(), $customerId, $storeId, $orderNo);
            if ($response->getStatus() != Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                $this->showException($response->getStatusDesc(), false);
            }
        } elseif ($profile->getNewState() == 'suspended' || $profile->getNewState() == 'active') {
            $this->infoRequestxml($profile->getNewState(), $profile, $subsId, $storeId, $customerId, $orderNo);
        }
    }

    /**
     * Recurring profile Suspend or Activate
     *
     * @param string $action
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param int $subsId
     * @param int $storeId
     * @param int $customerId
     * @param int $orderNo
     * @return null
     */
    private function infoRequestxml($action, $profile, $subsId, $storeId, $customerId, $orderNo)
    {
        if ($action == 'suspended') {
            $pausePeriod = 1;
            $pausePeriodUnit = 'd';
            $suspend = 1;
            $subsIdRequest = $subsId;
        } else {
            $periodInfo = $this->getPeriodValues($profile);
            $pausePeriod = $periodInfo['periodFrequency'];
            $pausePeriodUnit = $periodInfo['periodUnit'];
            $suspend = 0;
            $subsIdRequest = $subsId;
            $subsId = NULL;
        }

        $request = '<?xml version="1.0" encoding="UTF-8"?>';
        $request .= '<nnxml><info_request>';
        $request .= '<vendor_id>' . $this->_vendorId . '</vendor_id>';
        $request .= '<vendor_authcode>' . $this->_authcode . '</vendor_authcode>';
        $request .= '<request_type>' . Novalnet_Payment_Model_Config::SUBS_PAUSE . '</request_type>';
        $request .= '<product_id>' . $this->_productId . '</product_id>';
        $request .= '<tid>' . $profile->getReferenceId() . '</tid>';
        $request .= '<subs_id>' . $subsIdRequest . '</subs_id>';
        $request .= '<pause_period>' . $pausePeriod . '</pause_period>';
        $request .= '<pause_time_unit>' . $pausePeriodUnit . '</pause_time_unit>';
        $request .= '<suspend>' . $suspend . '</suspend>';
        $request .= '</info_request></nnxml>';

        $infoRequestUrl = $this->helper->getPayportUrl('infoport');
        $result = $this->setNovalnetRequestCall($request, $infoRequestUrl, 'XML');
        $xml = simplexml_load_string($request);
        $json = json_encode($xml);
        $array = json_decode($json, TRUE);
        $request = new Varien_Object($array);
        $this->logNovalnetTransactionData($request, $result, $profile->getReferenceId(), $customerId, $storeId, $orderNo, $subsId);
        if ($result->getStatus() != Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $statusDesc = $result->getStatusDesc();
            if ($action == 'suspended') {
                $statusDesc = $result->getSubscriptionPause();
                $statusDesc = $statusDesc['status_message'];
            }
            $this->showException($statusDesc, false);
        }
    }

    /**
     * Get subscription period frequency and unit
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @return array
     */
    public function getPeriodValues($profile) {
        $periodFrequency = $profile->getperiodFrequency();
        $periodUnit = $this->helper->__(ucfirst($profile->getperiodUnit()));
        $day = $this->helper->__('Day');
        $month = $this->helper->__('Month');
        $year = $this->helper->__('Year');
        $periodUnitFormat = array($day => "d", $month => "m", $year => "y");

        if ($periodUnit == 'Semi_month') {
            $tariffPeriod = array('periodFrequency' => '14', 'periodUnit' => 'd');
        } elseif ($periodUnit == 'Week') {
            $tariffPeriod = array('periodFrequency' => ($periodFrequency * 7), 'periodUnit' => 'd');
        } else {
            $tariffPeriod = array('periodFrequency' => $periodFrequency, 'periodUnit' => $periodUnitFormat[$periodUnit]);
        }

        return $tariffPeriod;
    }

    /**
     * Check whether payment method can be used
     *
     * @param Mage_Sales_Model_Quote
     * @return boolean
     */
    public function isAvailable($quote = null)
    {
        $getNnDisableTime = "getNnDisableTime" . ucfirst($this->_code); //Dynamic Getter based on payment methods
        $helper = $this->helper;

        if ($helper->checkOrdersCount($this->getNovalnetConfig('orderscount'))) {
            return false;
        } elseif (!$helper->checkCustomerAccess($this->getNovalnetConfig('user_group_excluded'))) {
            return false;
        } elseif (!empty($quote) && !$quote->hasNominalItems()
            && !$helper->isModuleActive($quote->getGrandTotal())) {
            return false;
        } elseif (time() < $this->_getCheckout()->$getNnDisableTime()) {
            return false;
        }

        $this->paymentRefillValidate();
        // Assign affiliate account information if available.
        $this->loadAffAccDetail();
        return parent::isAvailable($quote);
    }

    /**
     * Assign form data in quote instance
     *
     * @param array $data
     * @return  Mage_Payment_Model_Abstract Object
     */
    public function assignData($data)
    {
        $infoInstance = $this->_getInfoInstance();
        // unset form method session
        $prevPaymentCode = $this->_getCheckout()->getNnPaymentCode();
        if ($prevPaymentCode && $this->_code != $prevPaymentCode) {
            $this->unsetFormMethodSession();
            $this->unsetMethodSession($prevPaymentCode);
        }

        $this->dataHelper->assignNovalnetData($this->_code, $data, $infoInstance);
        return $this;
    }

    /**
     * Validate payment method information object
     *
     * @param null
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
        }

        $callbackPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('fraudCheckPayment');
        if (in_array($this->_code, $callbackPayment)) {
            $this->_initiateCallbackProcess($this->_code, $infoInstance);
        }

        if (!$this->isCallbackTypeCall() && $this->_isPlaceOrder()) {
            $this->_sendRequestToNovalnet();
        }
        return $this;
    }

    /**
     * Payment form refill validation
     *
     * @param null
     * @return null
     */
    public function paymentRefillValidate()
    {
        $helper = $this->helper;
        $checkoutSession = $this->_getCheckout();
        $prevPaymentCode = $checkoutSession->getNnPaymentCode();
        // Check the users (guest or login)
        $customerSession = $helper->getCustomerSession();
        $coreSession = $helper->getCoresession();
        if (!$customerSession->isLoggedIn() && !$coreSession->getGuestloginvalue()) {
            $coreSession->setGuestloginvalue('1');
        } elseif ($coreSession->getGuestloginvalue() && $customerSession->isLoggedIn()) {
            $coreSession->setGuestloginvalue('0');
            $this->unsetFormMethodSession();
            $this->unsetMethodSession($prevPaymentCode);
        }
        // unset form previous payment method session
        $paymentCode = $checkoutSession->getQuote()->getPayment()->getMethod();
        if ($paymentCode && !preg_match("/novalnet/i", $paymentCode) && $prevPaymentCode && $paymentCode != $prevPaymentCode) {
            $this->unsetFormMethodSession();
            $this->unsetMethodSession($prevPaymentCode);
        }

        $paymentSucess = $this->getNovalnetConfig('payment_last_success', true);
        if (!$helper->checkIsAdmin() && $customerSession->isLoggedIn() && empty($paymentCode) && $paymentSucess) {
            $helper->getModelFactory()->getlastSuccesOrderMethod($customerSession->getId(),$checkoutSession);
        }
    }

    /**
     * Assign the Novalnet direct payment methods request
     *
     * @param null
     * @return null
     */
    private function _sendRequestToNovalnet()
    {
        $redirectPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayments');

        if ($this->_code && !in_array($this->_code, $redirectPayment)) {
            $storeId = $this->helper->getMagentoStoreId();
            $request = $this->buildRequest(Novalnet_Payment_Model_Config::POST_NORMAL, $storeId);
            $this->_getCheckout()->setPaymentReqData($request);
        }
    }

    /**
     * Payment capture process
     *
     * @param varien_object $payment
     * @param float $amount
     * @return Mage_Payment_Model_Abstract
     */
    public function capture(Varien_Object $payment, $amount)
    {
        $redirectPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayments');
        $responseCodeApproved = Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED;

        if ($this->canCapture() && !in_array($this->_code, $redirectPayment)) {
            $helper = $this->helper;
            $orderId = $payment->getOrder()->getId();
            $amount = $helper->getAmountCollection($orderId, 1, NULL);
            $lastTranId = $helper->makeValidNumber($payment->getLastTransId());
            $loadTransStatus = $helper->loadTransactionStatus($lastTranId);
            $transStatus = $loadTransStatus->getTransactionStatus();
            if (!empty($transStatus) && $transStatus != $responseCodeApproved) {
                $response = $this->payportRequest($payment, 'capture');
                if ($response->getStatus() == $responseCodeApproved) {
                    $data = unserialize($payment->getAdditionalData());
                    $data['captureTid'] = $payment->getLastTransId();
                    $data['CaptureCreateAt'] = $helper->getCurrentDateTime();
                    $payment->setAdditionalData(serialize($data))->save();
                    $helper->getModelFactory()->captureResponseSave($amount, $loadTransStatus, $transStatus, $payment, $lastTranId);
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
     * @param int $storeId
     * @param float $amountValue
     * @param varien_object $nominalItem
     * @return mixed
     */
    public function buildRequest($type = Novalnet_Payment_Model_Config::POST_NORMAL,
        $storeId = NULL, $amountValue = NULL, $nominalItem = NULL
    ) {
        if ($type == Novalnet_Payment_Model_Config::POST_NORMAL || $type == Novalnet_Payment_Model_Config::POST_CALLBACK) {
            $request = new Varien_Object();
            // set vendor params
            $this->assignNnAuthData($request, $storeId);
            $infoObject = $this->_getInfoObject();
            $orderId = $this->_getOrderId();
            $livemode = (!$this->getNovalnetConfig('live_mode')) ? 1 : 0;
            $helper = $this->helper;
            $modelFactory = $helper->getModelFactory();
            $amount = $nominalItem ? $helper->getFormatedAmount($this->_getCheckout()->getNnRowAmount())
                : ($amountValue ? $amountValue : $helper->getFormatedAmount($this->_getAmount()));
            $modelFactory->requestParams($request, $infoObject, $orderId, $amount, $livemode);

            if ($this->_manualCheckLimit) {
                $this->_manualCheckValidate($request);
            }
            // set reference params
            $this->setReferenceParams($request);
            // set payment type
            $request->setPaymentType($helper->getPaymentType($this->_code));
            // set Novalnet params
            $this->_setNovalnetParam($request, $this->_code);
        }

        // set nominal period params
        if ($nominalItem) {
            $subsequentPeriod = $this->getNovalnetConfig('subsequent_period', true);
            $modelFactory->requestProfileParams($request, $subsequentPeriod);
        }

        // Callback Method
        if ($type == Novalnet_Payment_Model_Config::POST_CALLBACK) {
            $paymentCode = ucfirst($this->_code);
            $callbackTelNo = "getNnCallbackTel" . $paymentCode;

            if ($this->getConfigData('callback') == 1) { //PIN By Callback
                $request->setTel($this->getInfoInstance()->$callbackTelNo());
                $request->setPinByCallback(true);
            } else if ($this->getConfigData('callback') == 2) { //PIN By SMS
                $request->setMobile($this->getInfoInstance()->$callbackTelNo());
                $request->setPinBySms(true);
            }
        }

        return $request;
    }

    /**
     * Set additional reference params
     *
     * @param varien_object $request
     */
    public function setReferenceParams($request) {
        $referenceOne = trim(strip_tags(trim($this->getNovalnetConfig('reference_one'))));
        $referenceTwo = trim(strip_tags(trim($this->getNovalnetConfig('reference_two'))));

        if ($this->_referrerId) {
            $request->setReferrerId($this->_referrerId);
        }

        if ($this->helper->checkIsAdmin()) {
            $adminUserId = Mage::getSingleton('admin/session')->getUser()->getUserId();
            $request->setInput2('admin_user')
                    ->setInputval2($adminUserId);
        }

        if ($referenceOne) {
            $request->setInput3('reference1')
                    ->setInputval3($referenceOne);
        }

        if ($referenceTwo) {
            $request->setInput4('reference2')
                    ->setInputval4($referenceTwo);
        }
    }

    /**
     * Post request to gateway and return response
     *
     * @param varien_object $request
     * @return mixed
     */
    public function postRequest($request)
    {
        $result = new Varien_Object();
        $helper = $this->helper;
        $paymentKey = $helper->getPaymentId($this->_code);
        $quote = $this->_getCheckout()->getQuote();
        $payportUrl = $helper->getPayportUrl('paygate');

        if ($this->_validateBasicParams()) {
            $response = $this->setNovalnetRequestCall($request->getData(), $payportUrl);
            parse_str($response->getBody(), $data);
            $result->addData($data);
        } else {
            $this->showException($helper->__('Required parameter not valid') . '!', false);
        }
        return $result;
    }
    
    /**
     * load order transaction details
     *
     * @param tid 
     * @return integer
     */
    public function updatedAmount($tid)
    {
		$updated_amount = $this->helper->loadTransactionStatus($tid);
        return $updated_amount;
    }

    /**
     * Assign Novalnet authentication Data
     *
     * @param int $storeId
     * @param varien_object $request
     * @return null
     */
    public function assignNnAuthData(Varien_Object $request, $storeId = NULL)
    {
        //Reassign the Basic Params Based on store
        $this->_vendorId = $this->getNovalnetConfig('merchant_id', true, $storeId);
        $this->_authcode = $this->getNovalnetConfig('auth_code', true, $storeId);
        if ($this->_getCheckout()->getQuote()->hasNominalItems()) {
            $this->_tariffId = $this->_subscribTariffId;
        }

        // Assign affiliate account information if available.
        $this->loadAffAccDetail();
        $request->setVendor($this->_vendorId)
                ->setAuthCode($this->_authcode)
                ->setProduct($this->_productId)
                ->setTariff($this->_tariffId)
                ->setKey($this->helper->getPaymentId($this->_code));
    }

    /**
     * Get the Order basic configuration for refund, void, and capture
     *
     * @param varien_object $request
     * @param varien_object $payment
     * @param int $storeId
     * @param varien_object $nominalItem
     * @return null
     */
    public function assignOrderBasicParams(Varien_Object $request, $payment,
        $storeId = NULL, $nominalItem = NULL
    ) {
        $this->assignVendorConfig($payment);
        $getresponseData = unserialize($payment->getAdditionalData());
        // subscription tariff
        if ($nominalItem) {
            $this->_tariffId = $this->_subscribTariffId;
        }
        $this->_vendorId = ($getresponseData['vendor']) ? $getresponseData['vendor']
                    : $this->_vendorId;
        $this->_authcode = ($getresponseData['auth_code']) ? $getresponseData['auth_code']
                    : $this->_authcode;
        $this->_productId = ($getresponseData['product']) ? $getresponseData['product']
                    : $this->_productId;
        $this->_tariffId = ($getresponseData['tariff']) ? $getresponseData['tariff']
                    : $this->_tariffId;
        $key = ($getresponseData['key']) ? $getresponseData['key'] : $this->helper->getPaymentId($this->_code);
        // build basic merchant information
        $request->setVendor($this->_vendorId)
                ->setAuthCode($this->_authcode)
                ->setProduct($this->_productId)
                ->setTariff($this->_tariffId)
                ->setKey($key);
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
            $order = $payment->getOrder();
            $refundAmount = $helper->getFormatedAmount($amount);
            $customerId = $order->getCustomerId();
            $orderItems = $this->getPaymentAllItems($order);
            $nominalItem = $helper->checkNominalItem($orderItems);
            $data = unserialize($payment->getAdditionalData());
            $getTid = $helper->makeValidNumber($payment->getRefundTransactionId());
            $invoicePayment = array(Novalnet_Payment_Model_Config::NN_PREPAYMENT, Novalnet_Payment_Model_Config::NN_INVOICE);

            if (($this->_code == Novalnet_Payment_Model_Config::NN_SEPA)
                || ($nominalItem && (in_array($this->_code, $invoicePayment)))) {
                // get payment last transaction id
                $getTid = $helper->makeValidNumber($payment->getLastTransId());
                if (!empty($data['NnSepaParentTid'])) {
                    $getTid = $data['NnSepaParentTid'];
                }
            }

            if ($this->_code == Novalnet_Payment_Model_Config::NN_INVOICE) {
                $this->_refundValidation($payment, $refundAmount);
            }

            $orderAmount = NULL;
            $modelFactory = $helper->getModelFactory();
            if ($nominalItem) {
                $currency = Mage::app()->getLocale()->currency($this->_getInfoObject()->getBaseCurrencyCode())->getSymbol();
                $orderAmount = $helper->getFormatedAmount($this->_getAmount());
            }
            $response = $this->payportRequest($payment, 'refund', $refundAmount, $getTid, $orderAmount);

            if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                $amountAfterRefund = ($order->getTotalPaid() - $order->getBaseTotalRefunded());
                $response->setAmount($helper->getFormatedAmount($this->_getAmount()));       
                $statusCode = $modelFactory->getTransactionData($getTid, $payment, $amountAfterRefund, 1, NULL, NULL, $response);
                $txnId = $response->getTid();
                $refundTid = !empty($txnId) ? $txnId : $payment->getLastTransId() . '-refund';
                $data['fullRefund'] = ((string)$this->_getAmount() == (string)$amount) ? true : false;

                if (in_array($this->_code, $invoicePayment)) {
                    $data['NnTid'] = $data['NnTid'] . '-refund';
                    $refundTid = $data['NnTid'];
                }

                if ($refundTid) {
                    $refAmount = Mage::helper('core')->currency($amount, true, false);
                    $data = $modelFactory->refundTidData($refAmount, $data, $refundTid, $getTid);
                }
                // For SEPA payment after submitting to bank
                if ($statusCode->getStatus() && $this->_code == Novalnet_Payment_Model_Config::NN_SEPA) {
                    $data['NnSepaParentTid'] = $getTid;
                    if ($statusCode->getStatus() == Novalnet_Payment_Model_Config::PAYMENT_VOID_STATUS
                        && $statusCode->getChildTidInfo()) {
                        parse_str($statusCode->getChildTidInfo(), $resInfo);
                        $data['NnSepaParentTid'] = $resInfo['tid1'];
                    }
                }

                $modelFactory->refundValidateProcess($helper, $payment, $refundTid,$data);
                if ($txnId) {
					$response->setAmount($amount);
                    $modelFactory->getTransactionData($refundTid, $payment, $amountAfterRefund, 2, $refundTid, $order->getCustomerId(), $response);
                }
            } else {
                $this->showException($response->getStatusDesc(), false);
            }
        } else {
            $this->showException('Error in your refund request');
        }
        return $this;
    }

    /**
     * refund validation for InvoicePayment
     *
     * @param varien_object $payment
     * @param float $refundAmount
     * @return  boolean
     */
    private function _refundValidation($payment, $refundAmount)
    {
        $orderId = $payment->getOrder()->getIncrementId();
        $helper = $this->helper;
        $callbackTrans = $helper->loadCallbackValue($orderId);
        $callbackValue = ($callbackTrans && $callbackTrans->getCallbackAmount())
                    ? $callbackTrans->getCallbackAmount() : '';
        $currency = Mage::app()->getLocale()->currency($this->_getInfoObject()->getBaseCurrencyCode())->getSymbol();
        if ($callbackValue < (string) $refundAmount) {
            $this->showException('Maximum amount available to refund is ' . $currency . $helper->getFormatedAmount($callbackValue, 'RAW'));
        }

        $refundedAmount = $helper->getFormatedAmount($payment->getAmountRefunded());
        $totalrefundAmount = $refundedAmount + $refundAmount;
        $availAmount = $callbackValue - $refundedAmount;
        if ($payment->getAmountRefunded() && $callbackValue < (string) $totalrefundAmount) {
            $this->showException('Maximum amount available to refund is ' . $currency . $helper->getFormatedAmount($availAmount, 'RAW'));
        }
        return true;
    }

    /**
     * Void payment
     *
     * @param varien_object $payment
     * @return  Mage_Payment_Model_Abstract
     */
    public function void(Varien_Object $payment)
    {
        if (!$this->canVoid($payment)) {
            $this->showException('Void action is not available.');
        }

        $helper = $this->helper;
        $getTid = $helper->makeValidNumber($payment->getLastTransId());
        $response = $this->payportRequest($payment, 'void');

        if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $txnId = trim($response->getTid());
            $voidTid = !empty($txnId) ? $txnId : $payment->getLastTransId() . '-void';
            $data = unserialize($payment->getAdditionalData());
            $data['voidTid'] = $voidTid;
            $data['voidCreateAt'] = $helper->getCurrentDateTime();

            if (in_array($this->_code, array(Novalnet_Payment_Model_Config::NN_INVOICE,
                        Novalnet_Payment_Model_Config::NN_PREPAYMENT))) {
                $bankVoidTid = !empty($txnId) ? $txnId : $payment->getLastTransId();
                $data['NnNoteTID'] = $this->dataHelper->getReferenceDetails($bankVoidTid, $data, $this->_code);
                $payment->setAdditionalData(serialize($data));
            }
            $payment->setTransactionId($voidTid)
                    ->setLastTransId($voidTid)
                    ->setAdditionalData(serialize($data))
                    ->save();
             $helper->getModelFactory()->getTransactionData($getTid, $payment, NULL, 1, NULL, NULL, $response);
        } else {
            $this->showException('Error in you void request');
        }
        return $this;
    }

    /**
     * Perform Novalnet request for Void, Capture, Refund
     *
     * @param varien_object $payment
     * @param string $requestType
     * @param float $refundAmount
     * @param int $refundTid
     * @param float $cardAmount
     * @return mixed
     */
    private function payportRequest($payment, $requestType, $refundAmount = NULL,
        $refundTid = NULL, $cardAmount = NULL
    ) {
        $request = new Varien_Object();
        $order = $payment->getOrder();
        $storeId = $order->getStoreId();
        $customerId = $order->getCustomerId();
        $orderItems = $this->getPaymentAllItems($order);
        $getTid = ($requestType == 'refund') ? $refundTid : $payment->getLastTransId();
        $helper = $this->helper;
        $nominalItem = $helper->checkNominalItem($orderItems);
        $responseCodeApproved = Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED;
        $this->assignOrderBasicParams($request, $payment, $storeId, $nominalItem);
        $modelFactory = $helper->getModelFactory();
        $totalRefunded = $order->getTotalRefunded();
        $lastTransId = $payment->getLastTransId();

        $modelFactory->requestTypes($request, $requestType, $helper, $getTid, $refundAmount);

        if (!in_array(NULL, $request->toArray())) {
            $buildNovalnetParam = http_build_query($request->getData());
            $payportUrl = $helper->getPayportUrl('paygate');
            $response = $this->dataHelper->setRawCallRequest($buildNovalnetParam, $payportUrl, $payment->getMethodInstance());
            $this->logNovalnetTransactionData($request, $response, $payment->getLastTransId(), $customerId, $storeId);
        } else {
            $this->showException('Error in processing the transactions request');
        }

        if ($response->getStatus() == $responseCodeApproved && $nominalItem) {
            // response transaction id
            $responseTid = $response->getTid();
            if ($requestType == 'void') {
                $modelFactory->saveProfileCancelState($lastTransId);
            } elseif ($requestType == 'refund' && $this->_code == Novalnet_Payment_Model_Config::NN_CC
                    && $totalRefunded == $order->getTotalPaid()){
                $modelFactory->saveProfileCancelState($getTid);
            } elseif ($requestType == 'refund' && $this->_code != Novalnet_Payment_Model_Config::NN_CC
                    && ($order->getGrandTotal() == $totalRefunded || $refundAmount == $cardAmount)) {
                $lastTransId = preg_match("/refund/i", $lastTransId) ? str_replace("-refund",'',$lastTransId) : $lastTransId;
                $modelFactory->saveProfileCancelState($lastTransId);
                $responseTid ? $modelFactory->saveProfileTID($lastTransId,$responseTid) : '';
            } elseif ($requestType == 'refund' && !empty($responseTid)
                    && $this->_code != Novalnet_Payment_Model_Config::NN_CC) {
                $lastTransId = preg_match("/refund/i", $lastTransId) ? str_replace("-refund",'',$lastTransId) : $lastTransId;
                $modelFactory->saveProfileTID($lastTransId,$responseTid);
            }
        }
        return $response;
    }

    /**
     *
     * Get affiliate account/user detail
     *
     * @param null
     * @return mixed
     */
    public function loadAffAccDetail()
    {
        $helper = $this->helper;
        $nnAffId = $helper->getCoresession()->getNnAffId();

        if (empty($nnAffId)) {
            $customerId = $helper->getCustomerId();
            $orderCollection = Mage::getModel('novalnet_payment/affiliateuser')->getCollection()
                                                             ->addFieldToFilter('customer_no', $customerId)
                                                             ->addFieldToSelect('aff_id');
            $nnAffId = $orderCollection->getLastItem()->getAffId()
                        ? $orderCollection->getLastItem()->getAffId() : NULL;
            $helper->getCoresession()->setNnAffId($nnAffId);
        }

        if ($nnAffId) {
            $orderCollection = Mage::getModel('novalnet_payment/affiliate')->getCollection()
                                                             ->addFieldToFilter('aff_id', $nnAffId)
                                                             ->addFieldToSelect('aff_id')
                                                             ->addFieldToSelect('aff_authcode')
                                                             ->addFieldToSelect('aff_accesskey');
            $vendorId = $orderCollection->getLastItem()->getAffId();
            $vendorAuthcode = $orderCollection->getLastItem()->getAffAuthcode();
            $affAccesskey = $orderCollection->getLastItem()->getAffAccesskey();

            // reassign vendor & authcode
            if ($vendorId && $vendorAuthcode) {
                $this->_vendorId = $vendorId;
                $this->_authcode = $vendorAuthcode;
                $this->_getCheckout()->setNnVendor($this->_vendorId)
                                     ->setNnAuthcode($this->_authcode);
            }
        }

        $accessKey = !empty($affAccesskey) ? $affAccesskey : $this->getNovalnetConfig('password', true);
        return $accessKey;
    }

    /**
     * Get Method Session
     *
     * @param string $paymentCode
     * @return mixed
     */
    private function _getMethodSession($paymentCode = NULL)
    {
        $paymentCode = (!empty($paymentCode)) ? $paymentCode : $this->getCode();
        $checkoutSession = $this->helper->getCheckoutSession();
        if (!$checkoutSession->hasData($paymentCode)) {
            $checkoutSession->setData($paymentCode, new Varien_Object());
        }
        return $checkoutSession->getData($paymentCode);
    }

    /**
     * Unset method session
     *
     * @param string $paymentCode
     * @return mixed
     */
    public function unsetMethodSession($paymentCode = NULL)
    {
        $paymentCode = (!empty($paymentCode)) ? $paymentCode : $this->getCode();
        $this->helper->getCheckoutSession()->unsetData($paymentCode);
        return $this;
    }

    /**
     * Get the Novalnet configuration data
     *
     * @param string $field
     * @param boolean $globalMode
     * @param int $storeId
     * @return boolean | null
     */
    public function getNovalnetConfig($field, $globalMode = false, $storeId = NULL)
    {
        $helper = $this->helper;
        $storeId = is_null($storeId) ? $helper->getMagentoStoreId() : $storeId;
        $path = $helper->getNovalnetGlobalPath() . $field;
        if ($field == 'live_mode') {
            $getTestModePaymentMethod = Mage::getStoreConfig($path, $storeId);
            if (!preg_match('/' . $this->_code . '/i', $getTestModePaymentMethod)) {
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
     * Save canceled payment method
     *
     * @param varien_object $result
     * @param varien_object $payment
     * @return null
     */
    public function saveCancelledOrder($result, $payment)
    {
        $order = $payment->getOrder();
        $statusMessage = $result->getStatusMessage() ?  $result->getStatusMessage() : ($result->getStatusDesc() ?  $result->getStatusDesc()
                        : ($result->getStatusText() ?  $result->getStatusText() : $this->helper->__('Payment was not successfull')));                      
        $paystatus = "<b><font color='red'>" . $this->helper->__('Payment Failed') . "</font> - " . $statusMessage . "</b>";
        $data = unserialize($payment->getAdditionalData());
        // Set Novalnet Mode
        $authorizeKey = $data['authorize_key'];
        $serverResponse = ($result->getTestMode() && is_numeric($result->getTestMode()))
                    ? $result->getTestMode()
                    : $this->helper->getDecodedParam($result->getTestMode(), $authorizeKey);
        $shopMode = $this->getNovalnetConfig('live_mode', true);
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
     * Log Affiliate user details
     *
     * @param int $affId
     * @return null
     */
    public function doNovalnetAffUserInfoLog($affId)
    {
        $affiliateUserInfo = $this->helper->getModelAffiliateuser();
        $affiliateUserInfo->setAffId($affId)
                ->setCustomerNo($this->helper->getCustomerId())
                ->setAffOrderNo($this->_getOrderId())
                ->save();
        $this->helper->getCoresession()->unsNnAffId();
    }

    /**
     * Check the transaction status using API
     *
     * @param array  $requestData
     * @param string $requestUrl
     * @param string $type
     * @return mixed
     */
    public function setNovalnetRequestCall($requestData, $requestUrl, $type = "")
    {
        if (!$requestUrl) {
            $this->showException('Server Request URL is Empty');
            return;
        }
        $httpClientConfig = array('maxredirects' => 0);

        if ($this->getNovalnetConfig('use_proxy',true)) {
            $proxyHost = $this->getNovalnetConfig('proxy_host',true);
            $proxyPort = $this->getNovalnetConfig('proxy_port',true);
            if ($proxyHost && $proxyPort) {
                $httpClientConfig['proxy'] = $proxyHost. ':' . $proxyPort;
                $httpClientConfig['httpproxytunnel'] = true;
                $httpClientConfig['proxytype'] = CURLPROXY_HTTP;
                $httpClientConfig['SSL_VERIFYHOST'] = false;
                $httpClientConfig['SSL_VERIFYPEER'] = false;
            }
        }

        $gatewayTimeout = (int) $this->getNovalnetConfig('gateway_timeout',true);
        if ($gatewayTimeout > 0) {
            $httpClientConfig['timeout'] = $gatewayTimeout;
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
     * Set the Novalnet parameter based on payment code
     *
     * @param Varien object  $request
     * @param string $paymentCode
     * @return mixed
     */
    private function _setNovalnetParam(Varien_Object $request, $paymentCode)
    {
        $helper = $this->helper;
        $dataHelper = $this->dataHelper;
        if ($paymentCode) {
            switch ($paymentCode) {
                case Novalnet_Payment_Model_Config::NN_INVOICE:
                    $paymentDuration = trim($this->getNovalnetConfig('payment_duration'));
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
                case Novalnet_Payment_Model_Config::NN_EPS:
                case Novalnet_Payment_Model_Config::NN_GIROPAY:
                    $authorizeKey = $this->loadAffAccDetail();
                    $request->setUniqid(uniqid())
                            ->setSession(session_id())
                            ->setImplementation('PHP');
                    $this->dataHelper->assignNovalnetReturnData($request, $this->_code);
                    $this->dataHelper->importNovalnetEncodeData($request, $authorizeKey);
                    break;
                case Novalnet_Payment_Model_Config::NN_CC:
                    $checkoutSession = $this->_getCheckout();
                    $amount = $helper->getFormatedAmount($this->_getAmount());
                    $authorizeKey = $this->loadAffAccDetail();
                    $request->setUniqid(uniqid())
                            ->setSession(session_id())
                            ->setImplementation('PHP_PCI')
                            ->setVendorId($request->getVendor())
                            ->setVendorAuthcode($request->getAuthCode())
                            ->setTariffId($request->getTariff())
                            ->setProductId($request->getProduct());
                    $request->unsVendor()
                            ->unsAuthCode()
                            ->unsProduct()
                            ->unsTariff();
                    // Add Credit Card 3D Secure payment process params
                    if ($this->getNovalnetConfig('active_cc3d')) {
                        $request->setCc_3d(1);
                    }
                    $dataHelper->assignNovalnetReturnData($request, $this->_code);
                    $this->dataHelper->importNovalnetEncodeData($request, $authorizeKey, 'PHP_PCI');
                    break;
                case Novalnet_Payment_Model_Config::NN_SEPA:
                    $paymentInfo = $dataHelper->novalnetCardDetails('payment');
                    $request->setBankAccountHolder($paymentInfo['account_holder'])
                            ->setSepaHash($dataHelper->novalnetCardDetails('result_sepa_hash'))
                            ->setSepaUniqueId($dataHelper->novalnetCardDetails('result_mandate_unique'))
                            ->setIbanBicConfirmed($dataHelper->novalnetCardDetails('nnsepa_iban_confirmed'));

                    $paymentDuration = trim($this->getNovalnetConfig('sepa_due_date'));
                    $dueDate = (!$paymentDuration) ? date('Y-m-d', strtotime('+7 days'))
                                : date('Y-m-d', strtotime('+' . $paymentDuration . ' days'));
                    $request->setSepaDueDate($dueDate);
                    break;
            }
        }
        return $request;
    }

    /**
     * Validate the Novalnet response
     *
     * @param varien_object $payment
     * @param varien_object $request
     * @return boolean
     */
    public function validateNovalnetResponse($payment, $result = NULL)
    {
        $order = $payment->getOrder();
        $request = $this->_getCheckout()->getPaymentReqData();

        switch ($result->getStatus()) {
            case Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED:
                // Set Novalnet Mode
                $txnId = trim($result->getTid());
                $data = $this->setPaymentAddtionaldata($result, $request);
                if ($this->_code == Novalnet_Payment_Model_Config::NN_SEPA) {
                    // Log sepa refill information for login users
                    $this->sepaPaymentRefill();
                }
                $payment->setStatus(self::STATUS_APPROVED)
                        ->setIsTransactionClosed(false)
                        ->setAdditionalData(serialize($data))
                        ->save();
                $order->setPayment($payment);
                $order->save();
                /* Update the transaction status and transaction overview */
                $result->setStatus($result->getTidStatus());
                $this->logNovalnetStatusData($result, $txnId);

                $helper = $this->helper;
                $captureMode = (version_compare($helper->getMagentoVersion(), '1.6', '<'))
                            ? false : true;

                // Capture the payment only if status is 100
                if ($order->canInvoice() && $result->getTidStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
                        && $this->_code != Novalnet_Payment_Model_Config::NN_PREPAYMENT) {
                    $payment->setTransactionId($txnId) // Add capture text to make the new transaction
                            ->setIsTransactionClosed($captureMode) // Close the transaction
                            ->capture(null)
                            ->save();
                    $setOrderStatus = $this->getNovalnetConfig('order_status') ? $this->getNovalnetConfig('order_status')
                                : Mage_Sales_Model_Order::STATE_PROCESSING; // If after status is empty set default status
                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $setOrderStatus, $this->helper->__('Payment was successful.'), true);
                }

                $payment->setTransactionId($txnId)
                        ->setLastTransId($txnId)
                        ->setParentTransactionId(null)
                        ->save();
                $order->setPayment($payment);
                $order->save();
				$nnAffId = $helper->getCoresession()->getNnAffId();
				if ($nnAffId) {
					$this->doNovalnetAffUserInfoLog($nnAffId);
				}
                if (!$this->isCallbackTypeCall()) {
                    $this->logNovalnetTransactionData($request, $result, $txnId, $this->helper->getCustomerId(), $this->helper->getMagentoStoreId());
                }
                $statusText = ($result->getStatusText()) ? $result->getStatusText() : $helper->__('successful');
                $helper->getCoresession()->addSuccess($statusText);
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
                    $this->helper->getCoresession()->addError($error);
                }
                break;
        }
        return $error;
    }

    /**
     * Set Payment method additional informations
     *
     * @param varien_object $result
     * @param varien_object $request
     * @param array
     */
    public function setPaymentAddtionaldata($result, $request = NULL)
    {
        $txnId = trim($result->getTid());
        // set Novalnet Mode
        $responseTestMode = $result->getTestMode();
        $shopMode = $this->getNovalnetConfig('live_mode');
        $testMode = (((isset($responseTestMode) && $responseTestMode == 1) || (isset($shopMode)
                && $shopMode == 0)) ? 1 : 0 );
        if ($this->_getCheckout()->getQuote()->hasNominalItems()) {
            $this->_tariffId = $this->_subscribTariffId;
        }

        $data = array('NnTestOrder' => $testMode,
            'NnTid' => $txnId,
            'orderNo' => trim($result->getOrderNo()),
            'vendor' => ($request->getVendor()) ? $request->getVendor() : $this->_vendorId,
            'auth_code' => ($request->getAuthCode()) ? $request->getAuthCode() : $this->_authcode,
            'product' => ($request->getProduct()) ? $request->getProduct() : $this->_productId,
            'tariff' => ($request->getTariff()) ? $request->getTariff() : $this->_tariffId,
            'key' => ($request->getKey()) ? $request->getKey() : $this->helper->getPaymentId($this->_code),
        );

        if (in_array($this->_code, array(Novalnet_Payment_Model_Config::NN_INVOICE,
                    Novalnet_Payment_Model_Config::NN_PREPAYMENT))) {
            $dataHelper = $this->dataHelper;
            $data['NnNoteDesc'] = $dataHelper->getNoteDescription();
            $data['NnDueDate'] = $dataHelper->getDueDate($result);
            $data['NnNote'] = $dataHelper->getNote($result);
            $data['NnNoteAmount'] = $dataHelper->getBankDetailsAmount($result->getAmount());
            $data['NnNoteTID'] = $dataHelper->getReferenceDetails($txnId,$data, $this->_code);
        }
        return $data;
    }

    /**
     * Log sepa payment refill information
     *
     * @param null
     * @return null
     */
    public function sepaPaymentRefill()
    {
        $helper = $this->helper;
        $customerLogin = $helper->getCustomerSession()->isLoggedIn();

        if ($customerLogin && !$helper->checkIsAdmin()) {
            $customerId = $helper->getCustomerId();
            $modNovalSepaReFillCollection = $helper->getModelSepaRefill()->getCollection();
            $modNovalSepaReFillCollection->addFieldToFilter('customer_id', $customerId);
            $modNovalSepaRefill = count($modNovalSepaReFillCollection)
                        ? $helper->getModelSepaRefill()->load($customerId, 'customer_id')
                        : $helper->getModelSepaRefill();
            $modNovalSepaRefill->setCustomerId($customerId)
                    ->setPanHash($this->_getCheckout()->getSepaHash())
                    ->setSepaDatetime($helper->getCurrentDateTime())
                    ->save();
        }
    }

    /**
     * Unset Form method session
     *
     * @param null
     * @return null
     */
    public function unsetFormMethodSession()
    {
        $this->_getCheckout()->unsSepaHash()
                ->unsSepaUniqueId()
                ->unsRefilldatavalues()
                ->unsNnPaymentCode()
                ->unsNnVendor()
                ->unsNnAuthcode();
    }

    /**
     * Unset payment method session
     *
     * @param null
     * @return null
     */
    public function unsetPaymentReqResData()
    {
        $this->_getCheckout()->unsNominalRequest()
                ->unsNominalResponse()
                ->unsPaymentReqData()
                ->unsPaymentResData()
                ->unsNnCallbackReqData();
    }

    /**
     * Validate the transaction status
     *
     * @param integer $tid
     * @param varien_object $payment
     * @param string $reqType
     * @param varien_object $extraOption
     * @param varien_object $requestData
     * @param array $params
     * @return mixed
     */
    public function doNovalnetStatusCall($tid, $payment = NULL, $reqType = Novalnet_Payment_Model_Config::TRANS_STATUS, $extraOption
    = NULL, $requestData = NULL, $params = NULL)
    {		
        $requestType = ($reqType == Novalnet_Payment_Model_Config::TRANS_STATUS)
                    ? Novalnet_Payment_Model_Config::TRANS_STATUS : $reqType;

        if ($payment) {
            $this->assignVendorConfig($payment);
        }
        // Callback request data re-assign
        $callbackReqData = $this->_getCheckout()->getNnCallbackReqData();
        if ($callbackReqData) {
            $this->_vendorId  = $callbackReqData->getVendor();
            $this->_authcode  = $callbackReqData->getAuthCode();
            $this->_productId = $callbackReqData->getProduct();
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
            $result = $this->setNovalnetRequestCall($request, $infoRequestUrl, 'XML');
            return $result;
        }
    }

    /**
     * Set the Order basic configuration
     *
     * @param string $payment
     * @return null
     */
    public function assignVendorConfig($payment = NULL)
    {
        // Reassign the Basic Params Based on store
        $getresponseData = $payment ? unserialize($payment->getAdditionalData()) : NULL;
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
     * Get checkout session
     *
     * @param null
     * @return Mage_Sales_Model_Order
     */
    protected function _getCheckout()
    {
        if ($this->helper->checkIsAdmin()) {
            return $this->helper->getAdminCheckoutSession();
        } else {
            return $this->helper->getCheckoutSession();
        }
    }

    /**
     * validate Novalnet params to proceed checkout
     *
     * @param null
     * @return boolean
     */
    public function validateNovalnetParams()
    {
        $infoObject = $this->_getInfoObject();
        $infoObjBilling = $infoObject->getBillingAddress();
        $quoteBilling = $this->_getCheckout()->getQuote()->getBillingAddress();
        $cusEmail = ($quoteBilling->getEmail()) ? $quoteBilling->getEmail() : $infoObject->getCustomerEmail();
        $cusFirstname = ($quoteBilling->getFirstname()) ? $quoteBilling->getFirstname()
                    : $infoObjBilling->getFirstname();
        $cusLastname = ($quoteBilling->getLastname()) ? $quoteBilling->getLastname()
                    : $infoObjBilling->getLastname();
        $helper = $this->helper;
        if (!$this->_validateBasicParams()) {
            $this->showException($helper->__('Please fill in all the mandatory fields') . '!', false);
            return false;
        } elseif (!$helper->validateEmail($cusEmail) || !$cusFirstname || !$cusLastname) {
            $this->showException($helper->__('Customer name/email fields are not valid') . '!', false);
            return false;
        }
        return true;
    }

    /**
     * Validate manual checklimit and reassign product id and tariff id
     *
     * @param varien_object $request
     * @return mixed
     */
    private function _manualCheckValidate($request)
    {
        $checkoutSession = $this->_getCheckout();
        $setOnholdPayments = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('setonholdPayments');
        $amount = $checkoutSession->getQuote()->hasNominalItems()
                ? $this->helper->getFormatedAmount($checkoutSession->getNnRowAmount())
                : $this->helper->getFormatedAmount($this->_getAmount());
        if (in_array($this->_code,$setOnholdPayments) && $this->_manualCheckLimit <= $amount) {
            $request->setOnHold(1);
        }

        return $request;
    }

    /**
     * Validate Novalnet basic params
     *
     * @param null
     * @return boolean
     */
    private function _validateBasicParams()
    {
        $helper = $this->helper;

        if ($helper->checkIsNumeric($this->_vendorId) && $this->_authcode && $helper->checkIsNumeric($this->_productId)
                && $helper->checkIsNumeric($this->_tariffId)) {
            if ($this->_getCheckout()->getQuote()->hasNominalItems() && !$helper->checkIsNumeric($this->_subscribTariffId)) {
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Check whether callback option is enabled
     *
     * @param null
     * @return boolean
     */
    public function isCallbackTypeCall()
    {
        $callbackTid = "hasNnCallbackTid" . ucfirst($this->_code);
        $total = $this->helper->getFormatedAmount($this->_getAmount());
        $callBackMinimum = (int) $this->getNovalnetConfig('callback_minimum_amount');
        $countryCode = strtoupper($this->_getInfoObject()->getBillingAddress()->getCountryId());
        $checkoutSession = $this->_getCheckout();
        $helper = $this->helper;
        if ($checkoutSession->getQuote()->hasNominalItems()) {
            $total = $helper->getFormatedAmount($checkoutSession->getNnRowAmount());
        }

        return ($helper->getCheckoutSession()->$callbackTid() || ($this->getNovalnetConfig('callback')
                && ($callBackMinimum ? $total >= $callBackMinimum : true) && ($helper->isCallbackTypeAllowed($countryCode))));
    }

    /**
     * Initiate callback process after selecting payment method
     *
     * @param string $paymentCode
     * @param mixed $$infoInstance
     * @return null
     */
    private function _initiateCallbackProcess($code, $infoInstance)
    {
        $isCallbackTypeCall = $this->isCallbackTypeCall();
        $methodSession = $this->_getMethodSession();
        $isPlaceOrder = $this->_isPlaceOrder();

        $paymentCode = ucfirst($code);
        $callbackTid = "getNnCallbackTid" . $paymentCode;
        $callbackOrderNo = "getNnCallbackOrderNo" . $paymentCode;
        $callbackPin = "getNnCallbackPin" . $paymentCode;
        $callbackNewPin = "getNnNewCallbackPin" . $paymentCode;
        $setcallbackPin = "setNnCallbackPin" . $paymentCode;

        if (!$isPlaceOrder && $isCallbackTypeCall && $this->_getIncrementId() != $methodSession->$callbackOrderNo()) {
            $this->unsetMethodSession();
        }

        // Validate callback session
        $this->_validateCallbackSession($paymentCode);

        if ($isCallbackTypeCall && $infoInstance->getCallbackPinValidationFlag()
                && $methodSession->$callbackTid()) {
            $nnCallbackPin = $infoInstance->$callbackPin();
            if (!$infoInstance->$callbackNewPin() && empty($nnCallbackPin)) {
                $this->showException('Enter your PIN');
            } elseif (!$infoInstance->$callbackNewPin() && !$this->helper->checkIsValid($nnCallbackPin)) {
                $this->showException('The PIN you entered is incorrect');
            }
        }

        if ($isCallbackTypeCall && !$isPlaceOrder) {
            if ($methodSession->$callbackTid()) {
                $infoInstance->$callbackNewPin() ? $this->_regenerateCallbackPin($methodSession)
                : $methodSession->$setcallbackPin($infoInstance->$callbackPin());
            } elseif (!$methodSession->$callbackTid()) {
                $this->_generateCallback($paymentCode);
            }
        }

        if ($isPlaceOrder) {
            $this->validateCallbackProcess($paymentCode);
        }
    }

    /**
     * Validate order amount is getting changed after callback initiation
     *
     * @param string $paymentCode
     * @return throw Mage Exception
     */
    private function _validateCallbackSession($paymentCode)
    {
        $callbackTid = "hasNnCallbackTid" . $paymentCode;
        $getNnDisableTime = "getNnDisableTime" . $paymentCode;
        $checkoutSession = $this->_getCheckout();
        $methodSession = $this->_getMethodSession();
        $helper = $this->helper;
        $amount = $this->_getCheckout()->getQuote()->hasNominalItems()
                ? $helper->getFormatedAmount($checkoutSession->getNnRowAmount())
                : $helper->getFormatedAmount($this->_getAmount());

        if ($methodSession->$callbackTid()) {
            if ($checkoutSession->$getNnDisableTime() && time() > $checkoutSession->$getNnDisableTime()) {
                $this->unsetMethodSession();
            } elseif ($methodSession->getOrderAmount() != $amount) {
                $this->unsetMethodSession();
                if (!$this->_isPlaceOrder()) {
                    $this->showException('The order amount has been changed, please proceed with the new order');
                }
            }
        }
    }

    /**
     * Get increment id for callback process
     *
     * @param null
     * @return int
     */
    private function _getIncrementId()
    {
        $storeId = $this->helper->getMagentoStoreId();
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
     * @param string $paymentCode
     * @return throw Mage Exception
     */
    private function _generateCallback($paymentCode)
    {
        $callbackTid = "setNnCallbackTid" . $paymentCode;
        $callbackOrderNo = "setNnCallbackOrderNo" . $paymentCode;
        $checkoutSession = $this->_getCheckout();
        $nominalItem = $checkoutSession->getQuote()->hasNominalItems();

        if ($nominalItem) {
            $request = $this->buildRequest(Novalnet_Payment_Model_Config::POST_CALLBACK, NULL, NULL, $nominalItem);
            $response = $this->postRequest($request);
            $checkoutSession->setNominalRequest($request)
                            ->setNominalResponse($response);
        } else {
            $request = $this->buildRequest(Novalnet_Payment_Model_Config::POST_CALLBACK);
            $response = $this->postRequest($request);
            $checkoutSession->setPaymentReqData($request)
                            ->setPaymentResData($response);
            $this->logNovalnetTransactionData($request, $response, $response->getTid());
        }

        $checkoutSession->setNnCallbackReqData($request);
        if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $this->_getMethodSession()
                        ->$callbackTid(trim($response->getTid()))
                        ->setNnTestMode(trim($response->getTestMode()))
                        ->setNnCallbackTidTimeStamp(time())
                        ->setOrderAmount($request->getAmount())
                        ->setNnCallbackSuccessState(true)
                        ->$callbackOrderNo(trim($response->getOrderNo()));
            if($this->getNovalnetConfig('callback') == 2) {
                $text = $this->helper->__('You will shortly receive an SMS containing your transaction PIN to complete the payment');
            } else {
                $text = $this->helper->__('You will shortly receive a transaction PIN through phone call to complete the payment');
            }
        } else {
            $text = $this->helper->htmlEscape($response->getStatusDesc());
        }
        $this->showException($text, false);
    }

    /**
     * Regenerate new pin for callback process
     *
     * @param mixed $methodSession
     * @return throw Mage Exception
     */
    private function _regenerateCallbackPin($methodSession)
    {
        $callbackTid = "getNnCallbackTid" . ucfirst($this->_code);
        $response = $this->doNovalnetStatusCall($methodSession->$callbackTid(), NULL, Novalnet_Payment_Model_Config::TRANSMIT_PIN_AGAIN);

        if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $text = $this->helper->__('You will shortly receive an SMS containing your transaction PIN to complete the payment');
        } else {
            $text = $this->helper->htmlEscape($response->getStatusMessage()); // Status_message
        }

        $this->showException($text, false);
    }

    /**
     * Validate callback response
     *
     * @param string $paymentCode
     * @param mixed $methodSession
     * @return null
     */
    public function validateCallbackProcess($paymentCode)
    {
        $callbackTid = "getNnCallbackTid" . $paymentCode;
        $callbackPin = "getNnCallbackPin" . $paymentCode;
        $setNnDisableTime = "setNnDisableTime" . $paymentCode;
        $methodSession = $this->_getMethodSession();

        if ($methodSession->getNnCallbackSuccessState()) {
            $type = Novalnet_Payment_Model_Config::PIN_STATUS;
            $extraOption = '<pin>' . $methodSession->$callbackPin() . '</pin>';
            $result = $this->doNovalnetStatusCall($methodSession->$callbackTid(), NULL, $type, $extraOption);
            $result->setTid($methodSession->$callbackTid());
            $result->setTestMode($methodSession->getNnTestMode());

            // Analyze the response from Novalnet
            if ($result->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                $methodSession->setNnCallbackSuccessState(false);
            } else {
                if ($result->getStatus() == Novalnet_Payment_Model_Config::METHOD_DISABLE_CODE) {
                    $this->_getCheckout()->$setNnDisableTime(time() + (30 * 60));
                }
                $error = ($result->getStatusDesc() || $result->getStatusMessage())
                            ? $this->helper->htmlEscape($result->getStatusMessage() . $result->getStatusDesc())
                            : $this->helper->htmlEscape($result->pinStatus['status_message']);
                $this->showException($error, false);
            }
        }
    }

    /**
     * Log Novalnet transaction data
     *
     * @param varien_object $request
     * @param varien_object $response
     * @param int $txnId
     * @param int $customerId
     * @param int $storeId
     * @param int $orderNo
     * @param int $subsId
     * @return null
     */
    public function logNovalnetTransactionData($request = NULL, $response = NULL, $txnId,
        $customerId = NULL, $storeId = NULL, $orderNo = NULL, $subsId = NULL
    ) {
        $this->dataHelper->doRemoveSensitiveData($request, $this->_code);
        $helper = $this->helper;
        $shopUrl = ($response->getMemburl()) ? $response->getMemburl() : $helper->getCurrentSiteUrl();
        if ($orderNo == NULL) {
            $orderNo = $this->_getOrderId();
        }

        $customerId = ($customerId) ? $customerId : $helper->getCustomerId();
        $storeId = ($storeId) ? $storeId : $helper->getMagentoStoreId();
        $modNovalTransactionOverview = $helper->getModelTransactionOverview();
        $modNovalTransactionOverview->setTransactionId($txnId)
                ->setOrderId($orderNo)
                ->setRequestData(serialize($request->getData()))
                ->setResponseData(base64_encode(serialize($response->getData())))
                ->setCustomerId($customerId)
                ->setStatus($response->getStatus())
                ->setStoreId($storeId)
                ->setShopUrl($shopUrl);
        if ($subsId) {
            $modNovalTransactionOverview->setAdditionalData($helper->getCurrentDateTime());
        }
        $modNovalTransactionOverview->setCreatedDate($helper->getCurrentDateTime())
                ->save();
    }

    /**
     * Log Novalnet transaction status data
     *
     * @param varien_object $response
     * @param int $txnId
     * @param int $customerId
     * @param int $storeId
     * @param float $amount
     * @return null
     */
    public function logNovalnetStatusData($response, $txnId, $customerId = NULL,
        $storeId = NULL, $amount = NULL
    ) {
        $helper = $this->helper;
        $shopUrl = ($response->getMemburl()) ? $response->getMemburl() : $helper->getCurrentSiteUrl();
        $customerId = ($customerId) ? $customerId : $helper->getCustomerId();
        $storeId = ($storeId) ? $storeId : $helper->getMagentoStoreId();
        $amount = ($amount) ? $amount : $response->getAmount();
        $modNovalTransactionStatus = $helper->getModelTransactionStatus();
        $modNovalTransactionStatus->setTransactionNo($txnId)
                ->setOrderId($this->_getOrderId())  // Order number
                ->setTransactionStatus($response->getStatus()) // Transaction status code
                ->setNcNo($response->getNcNo())
                ->setCustomerId($customerId) // Customer number
                ->setPaymentName($this->_code)   // Payment name
                ->setAmount($amount)  // Amount
                ->setRemoteIp($helper->getRealIpAddr()) // Remote ip
                ->setStoreId($storeId)  // Store id
                ->setShopUrl($shopUrl)
                ->setCreatedDate($helper->getCurrentDateTime()) // Created date
                ->save();
    }

    /**
     * Log Novalnet payment response data
     *
     * @param varien_object $response
     * @param int $orderId
     * @return null
     */
    public function doTransactionOrderLog($response, $orderId)
    {
        $helper = $this->helper;
        $modNovalTransactionOverview = $helper->getModelTransactionOverview()->loadByAttribute('order_id', $orderId);
        $modNovalTransactionOverview->setTransactionId($response->gettid())
                ->setResponseData(base64_encode(serialize($response->getData())))
                ->setCustomerId($helper->getCustomerId())
                ->setStatus($response->getstatus()) // Transaction status code
                ->setStoreId($helper->getMagentoStoreId())
                ->setShopUrl($helper->getCurrentSiteUrl())
                ->save();
    }

    /**
     * Get current infoinstance
     *
     * @param null
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
     * @param null
     * @return Mage_Payment_Model_Method_Abstract
     */
    private function _getInfoObject()
    {
        $info = $this->_getInfoInstance();
        return ($this->_isPlaceOrder()) ? $info->getOrder() : $info->getQuote();
    }

    /**
     * Whether current operation is order placement
     *
     * @param null
     * @return boolean
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
     * Get grand total amount
     *
     * @param null
     * @return double
     */
    private function _getAmount()
    {
        $info = $this->_getInfoInstance();
        return ($this->_isPlaceOrder())
                ? (double) $info->getOrder()->getBaseGrandTotal()
                : (double) $info->getQuote()->getBaseGrandTotal();
    }

    /**
     * Order increment ID getter (either real from order or a reserved from quote)
     *
     * @param null
     * @return int
     */
    private function _getOrderId()
    {
        $info = $this->_getInfoInstance();
        return ($this->_isPlaceOrder()) ? $info->getOrder()->getIncrementId()
                : $this->_getIncrementId();
    }

    /**
     * Get payment data for current order
     *
     * @param null
     * @return mixed
     */
    private function _getNnPaymentData()
    {
        $info = $this->_getInfoInstance();
        return ($this->_isPlaceOrder()) ? $info->getOrder()->getPayment() : $info;
    }

    /**
     * Retrieve model helper
     *
     * @param null
     * @return Novalnet_Payment_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('novalnet_payment');
    }

    /**
     * Retrieve Assign data helper
     *
     * @param null
     * @return Novalnet_Payment_Helper_AssignData
     */
    protected function _getDataHelper()
    {
        return Mage::helper('novalnet_payment/AssignData');
    }

    /**
     * Show expection
     *
     * @param string $text
     * @param boolean $lang
     * @return Mage::throwException
     */
    public function showException($text, $lang = true)
    {
        if ($lang) {
            $text = $this->helper->__($text);
        }
        return Mage::throwException($text);
    }

    /**
     * Assign helper utilities needed for the payment process
     *
     * @param null
     * @return Novalnet helper
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
     * Get Billing Address
     *
     * @param null
     * @return string
     */
    private function _getBillingAddress()
    {
        $info = $this->getInfoInstance();
        if ($this->_isPlaceOrder()) {
            return $info->getOrder()->getBillingAddress();
        } else {
            return $info->getQuote()->getBillingAddress();
        }
    }

    /**
     * Get all order items
     *
     * @param varien_object $order
     * @return mixed
     */
    private function getPaymentAllItems($order)
    {
        $orderItems = $order->getAllItems();
        return $orderItems;
    }

    /**
     * Get redirect URL
     *
     * @param none
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        $redirectPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayments');

        if ($this->_code == Novalnet_Payment_Model_Config::NN_CC) {
            $actionUrl = $this->helper->getUrl(Novalnet_Payment_Model_Config::CC_IFRAME_URL);
        } elseif(in_array($this->_code, $redirectPayment)) {
            $actionUrl = $this->helper->getUrl(Novalnet_Payment_Model_Config::GATEWAY_REDIRECT_URL);
        } else {
            $actionUrl = $this->helper->getUrl(Novalnet_Payment_Model_Config::GATEWAY_DIRECT_URL);
        }
        return $actionUrl;
    }

}
