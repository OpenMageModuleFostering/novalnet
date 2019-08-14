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
 * @category  Novalnet
 * @package   Novalnet_Payment
 * @copyright Copyright (c) Novalnet AG. (https://www.novalnet.de)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Model_Service_Api_Request extends Novalnet_Payment_Model_Service_Abstract
{

    /**
     * Prepare payment request params
     *
     * @param  Varien_Object $infoObject
     * @param  string|null   $code
     * @param  int|null      $amount
     * @return Varien_Object $request
     */
    public function getPayportParams($infoObject, $code = null, $amount = null)
    {
        $this->code = ($code !== null) ? $code : $this->code;
        $request = new Varien_Object();
        $this->getVendorInfo($request); // Get Novalnet merchant authentication informations
        $this->getBillingInfo($request, $infoObject); // Get customer billing infromations
        $this->getCommonInfo($request, $infoObject, $amount); // Get common params for Novalnet payment api request
        $this->getPaymentInfo($request); // Get needed Novalnet payment params
        return $request;
    }

    /**
     * Assign Novalnet authentication Data
     *
     * @param  Varien_Object $request
     * @return Varien_Object $request
     */
    public function getVendorInfo(Varien_Object $request)
    {
        $testMode = $this->getNovalnetConfig('live_mode', true); // Novalnet payment mode configuration
        $request->setVendor($this->vendorId)
            ->setAuthCode($this->authcode)
            ->setProduct($this->productId)
            ->setTariff($this->tariffId)
            ->setKey($this->_helper->getPaymentId($this->code))
            ->setTestMode(!$testMode ? 1 : 0);

        $checkoutSession = $this->_helper->getCheckoutSession(); // Get checkout session
        if ($checkoutSession->getQuote()->hasNominalItems()) {
            $request->setTariff($this->recurringTariffId);
        }

        return $request;
    }

    /**
     * Get end-customer billing informations
     *
     * @param  Varien_Object $request
     * @param  Varien_Object $info
     * @return mixed
     */
    public function getBillingInfo(Varien_Object $request, $info)
    {
        $infoObject = $this->_getInfoObject($info); // Get current payment object informations
        $billing = $infoObject->getBillingAddress(); // Get end-customer billing address
        $shipping = !$infoObject->getIsVirtual() ? $infoObject->getShippingAddress() : '';
        // Get company param if exist either billing/shipping address
        $company = $billing->getCompany() ? $billing->getCompany()
            : ($shipping && $shipping->getCompany() ? $shipping->getCompany() : '');
        $request->setFirstName($billing->getFirstname())
            ->setLastName($billing->getLastname())
            ->setEmail($billing->getEmail() ? $billing->getEmail() : $infoObject->getCustomerEmail())
            ->setCity($billing->getCity())
            ->setZip($billing->getPostcode())
            ->setTel($billing->getTelephone())
            ->setFax($billing->getFax())
            ->setSearchInStreet(1)
            ->setStreet(implode(',', $billing->getStreet()))
            ->setCountry($billing->getCountry())
            ->setCountryCode($billing->getCountry());
        $request = $company ? $request->setCompany($company) : $request; // Set company param if exist
    }

    /**
     * Get common params for Novalnet payment API request
     *
     * @param  Varien_Object $request
     * @param  Varien_Object $info
     * @param  int|null      $amount
     * @return mixed
     */
    public function getCommonInfo(Varien_Object $request, $info, $amount = null)
    {
        $this->getReferenceParams($request);  // Get payment reference params like referer id/reference value
        $infoObject = $this->_getInfoObject($info);  // Get current payment object informations
        $amount = $amount ? $amount : $this->_helper->getFormatedAmount($this->_getAmount($info));
        $vendorScriptUrl = Mage::getStoreConfig('novalnet_global/merchant_script/vendor_script_url');
        $request->setAmount($amount)
            ->setCurrency($infoObject->getBaseCurrencyCode())
            ->setCustomerNo($this->_helper->getCustomerId())
            ->setLang(strtoupper($this->_helper->getDefaultLanguage()))
            ->setRemoteIp($this->_helper->getRealIpAddr())
            ->setSystemIp($this->_helper->getServerAddr())
            ->setSystemUrl($this->_helper->getBaseUrl())
            ->setSystemName('Magento')
            ->setSystemVersion($this->_helper->getMagentoVersion() . '-' . $this->_helper->getNovalnetVersion())
            ->setOrderNo($this->getOrderId($info));

        if ($vendorScriptUrl) {
            $request->setNotifyUrl($vendorScriptUrl); // Get vendor script url
        }

        if ($this->manualCheckLimit) { // Manual checking limit for payment onHold process
            $this->manualCheckValidate($request); // Check whether payment using onHold or not
        }
    }

    /**
     * Set additional payment reference params
     *
     * @param  Varien_Object $request
     * @return mixed
     */
    public function getReferenceParams(Varien_Object $request)
    {
        $referenceOne = trim(strip_tags($this->getNovalnetConfig('reference_one')));
        // Assign reference value if exist
        !empty($referenceOne) ? $request->setInput1('reference1')->setInputval1($referenceOne) : '';
        $referenceTwo = trim(strip_tags($this->getNovalnetConfig('reference_two')));
        // Assign reference value if exist
        !empty($referenceTwo) ? $request->setInput2('reference2')->setInputval2($referenceTwo) : '';
        $referrerId = trim($this->getNovalnetConfig('referrer_id', true)); // Novalnet merchant reference id
        !empty($referrerId) ? $request->setReferrerId($referrerId) : ''; // Assign referer id if exist
        $adminUserId = ($this->_helper->checkIsAdmin())
            ? Mage::getSingleton('admin/session')->getUser()->getUserId() : '';
        // Assign admin order reference value if exist
        !empty($adminUserId) ? $request->setInput3('admin_user')->setInputval3($adminUserId) : '';
    }

    /**
     * Validate manual checklimit and assign onHold payment status
     *
     * @param  Varien_Object $request
     * @return mixed
     */
    public function manualCheckValidate(Varien_Object $request)
    {
        // Get onHold available payments
        $setOnholdPayments = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('onHoldPayments');
        if (in_array($this->code, $setOnholdPayments)
            && (string) $this->manualCheckLimit <= (string) $request->getAmount()
        ) {
            $request->setOnHold(1); // Set payment status as onHold
        }
    }

    /**
     * Get order increment id
     *
     * @param  Varien_Object $info
     * @return int
     */
    public function getOrderId($info)
    {
        return ($this->_isPlaceOrder($info))
            ? $info->getOrder()->getIncrementId() : $this->_getIncrementId();
    }

    /**
     * Assign Novalnet payment data
     *
     * @param  Varien_Object $request
     * @return none
     */
    public function getPaymentInfo(Varien_Object $request)
    {
        $methodSession = $this->_helper->getMethodSession($this->code); // Get current payment method session
        $request->setPaymentType($this->getPaymentType($this->code)); // Set payment type param in payment request

        switch ($this->code) {
        case Novalnet_Payment_Model_Config::NN_CC:
            $methodSession->setCcFormType($this->getNovalnetConfig('cc_form_type'));
            if ($methodSession->getNnCcTid()) {
                // Add Credit Card payment process required params
                $request->setCcCvc2($methodSession->getNnCcCvc());
                $request->setPaymentRef($methodSession->getNnCcTid());
            } else {
                 // Add Credit Card PCI (Iframe) payment process params
                $this->getCcIframeParams($request);
            }

            // Add Credit Card 3D Secure payment process params
            if ($this->getNovalnetConfig('enable_cc3d')) {
                $request->setCc_3d(1);
            }

            break;
        case Novalnet_Payment_Model_Config::NN_SEPA:
            if ($methodSession->getNnSepaTid()) {
                $request->setPaymentRef($methodSession->getNnSepaTid());
            } else {
                $request->setBankAccountHolder($methodSession->getSepaHolder())
                    ->setSepaHash($methodSession->getSepaHash())
                    ->setSepaUniqueId($methodSession->getSepaUniqueId())
                    ->setIbanBicConfirmed($methodSession->getSepaMandateConfirm());
            }
            // Assign params for SEPA payment guarantee
            if ($methodSession->getPaymentGuaranteeFlag()) {
                $request->setBirthDate($methodSession->getCustomerDob())
                    ->setPaymentType(Novalnet_Payment_Model_Config::SEPA_PAYMENT_GUARANTEE_TYPE)
                    ->setKey(Novalnet_Payment_Model_Config::SEPA_PAYMENT_GUARANTEE_KEY);
            }
            $paymentDuration = trim($this->getNovalnetConfig('sepa_due_date'));
            $dueDate = (!$paymentDuration) ? date('Y-m-d', strtotime('+7 days'))
                    : date('Y-m-d', strtotime('+' . $paymentDuration . ' days'));
            $request->setSepaDueDate($dueDate);
            break;
        case Novalnet_Payment_Model_Config::NN_INVOICE:
            $request->setInvoiceType(Novalnet_Payment_Model_Config::INVOICE_PAYMENT_TYPE)
                ->setInvoiceRef('BNR-' . $request->getProduct() . '-' . $request->getOrderNo());
            // Assign invoice payment due date
            if ($dueDate = $this->getPaymentDueDate()) {
                $request->setDueDate($dueDate);
            }
            // Assign params for invoice payment guarantee
            if ($methodSession->getPaymentGuaranteeFlag()) {
                $request->setBirthDate($methodSession->getCustomerDob())
                    ->setPaymentType(Novalnet_Payment_Model_Config::INVOICE_PAYMENT_GUARANTEE_TYPE)
                    ->setKey(Novalnet_Payment_Model_Config::INVOICE_PAYMENT_GUARANTEE_KEY);
            }
            break;
        case Novalnet_Payment_Model_Config::NN_PREPAYMENT:
            $request->setInvoiceType(Novalnet_Payment_Model_Config::PREPAYMENT_PAYMENT_TYPE)
                ->setInvoiceRef('BNR-' . $request->getProduct() . '-' . $request->getOrderNo());
            break;
        case Novalnet_Payment_Model_Config::NN_PAYPAL:
        case Novalnet_Payment_Model_Config::NN_BANKTRANSFER:
        case Novalnet_Payment_Model_Config::NN_IDEAL:
        case Novalnet_Payment_Model_Config::NN_EPS:
        case Novalnet_Payment_Model_Config::NN_GIROPAY:
            $request->setUniqid(uniqid())
                ->setSession(session_id())
                ->setImplementation('PHP');
            $this->getMethodAndUrlInfo($request);
            $this->getEncodedParams($request);
            break;

        }
    }

    /**
     * Get due date for invoice payment
     *
     * @param  none
     * @return int
     */
    public function getPaymentDueDate()
    {
        $dueDate = '';
        $paymentDuration = trim($this->getNovalnetConfig('payment_duration'));
        if ($paymentDuration && $this->_helper->checkIsNumeric($paymentDuration)) {
            $dueDate = date('Y-m-d', strtotime('+' . (int) $paymentDuration . ' days'));
        } elseif ($paymentDuration == "0") {
            $dueDate = date('Y-m-d');
        }
        return $dueDate;
    }

    /**
     * Get Credit Card iframe payment params
     *
     * @param  Varien_Object $request
     * @return none
     */
    public function getCcIframeParams($request)
    {
        $request->setUniqid(uniqid())
            ->setSession(session_id())
            ->setImplementation('PHP_PCI')
            ->setVendorId($request->getVendor())
            ->setVendorAuthcode($request->getAuthCode())
            ->setTariffId($request->getTariff())
            ->setProductId($request->getProduct());
        $this->getMethodAndUrlInfo($request);
        $this->getEncodedParams($request);
        $request->unsVendor()
            ->unsAuthCode()
            ->unsProduct()
            ->unsTariff();
    }

    /**
     * Retrieve Novalnet payment type
     *
     * @param  string $code
     * @return string
     */
    public function getPaymentType($code)
    {
        $arrPaymentType = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('paymentTypes');
        return $arrPaymentType[$code];
    }

    /**
     * Get method and url infromations for redirect payments
     *
     * @param  Varien_Object $request
     * @return mixed
     */
    public function getMethodAndUrlInfo($request)
    {
        $request->setUserVariable_0(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB))
            ->setReturnMethod(Novalnet_Payment_Model_Config::NOVALNET_RETURN_METHOD)
            ->setErrorReturnMethod(Novalnet_Payment_Model_Config::NOVALNET_RETURN_METHOD)
            ->setReturnUrl($this->_helper->getUrl(Novalnet_Payment_Model_Config::GATEWAY_RETURN_URL))
            ->setErrorReturnUrl($this->_helper->getUrl(Novalnet_Payment_Model_Config::GATEWAY_ERROR_RETURN_URL));
    }

    /**
     * Assign fraud module params
     *
     * @param  Varien_Object $request
     * @param  Varien_Object $methodSession
     * @return none
     */
    public function setFraudModuleParams($request, $methodSession)
    {
        $paymentCode = ucfirst($this->code); // Payment method code
        $callbackTelNo = "getCallbackTel" . $paymentCode;  // End-customer telephone/mobile number
        $callbackEmail = "getCallbackEmail" . $paymentCode;  // End-customer email address

        if ($this->getNovalnetConfig('callback') == 1) { //PIN By Callback
            $request->setTel($methodSession->$callbackTelNo())
                ->setPinByCallback(true);
        } elseif ($this->getNovalnetConfig('callback') == 2) { //PIN By SMS
            $request->setTel($methodSession->$callbackTelNo())
                ->setPinBySms(true);
        }
    }

    /**
     * build process (capture/void/refund) request
     *
     * @param  Varien_Object $payment
     * @param  string        $type
     * @param  float|NULL    $amount
     * @return Varien_Object $request
     */
    public function buildProcessRequest(Varien_Object $payment, $type, $amount = null)
    {
        $request = $this->getprocessVendorInfo($payment);  // Get Novalnet merchant authentication informations

        if ($type == 'void' || $type == 'capture') { // Assign needed capture/void process request params
            $getTid = $this->_helper->makeValidNumber($payment->getLastTransId());
            $status = ($type == 'capture')
                ? Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
                : Novalnet_Payment_Model_Config::PAYMENT_VOID_STATUS;
            $request->setTid($getTid)
                ->setStatus($status)
                ->setEditStatus(true);
        } else {  // Assign needed refund process request params
            // Refund validation for Invoice payment method
            $refundTid = $this->_helper->makeValidNumber($payment->getRefundTransactionId());
            $paymentCode = $payment->getMethodInstance()->getCode(); // Get payment method code

            if ($paymentCode == Novalnet_Payment_Model_Config::NN_SEPA) {
                // Get payment last transaction id
                $refundTid = $this->_helper->makeValidNumber($payment->getLastTransId());
            }

            $refundAmount = $this->_helper->getFormatedAmount($amount); // Refund amount in cents
            $request->setTid($refundTid)
                ->setRefundRequest(true)
                ->setRefundParam($refundAmount);
            $this->refundAdditionalParam($request); // Add additonal params for refund process
        }

        return $request;
    }

    /**
     * Assign Novalnet authentication Data
     *
     * @param  Varien_Object $payment
     * @return Varien_Object $request
     */
    public function getprocessVendorInfo($payment)
    {
        $data = unserialize($payment->getAdditionalData()); // Get payment additional data
        $paymentCode = $payment->getMethodInstance()->getCode(); // Get payment method code
        $paymentId = $this->_helper->getPaymentId($paymentCode);

        $request = new Varien_Object();
        $request->setVendor(!empty($data['vendor']) ? trim($data['vendor']) : $this->vendorId)
            ->setAuthCode(!empty($data['auth_code']) ? trim($data['auth_code']) : $this->authcode)
            ->setProduct(!empty($data['product']) ? trim($data['product']) : $this->productId)
            ->setTariff(!empty($data['tariff']) ? trim($data['tariff']) : $this->tariffId)
            ->setKey(!empty($data['payment_id']) ? trim($data['payment_id']) : $paymentId);
        return $request;
    }

    /**
     * Add additonal params for refund process
     *
     * @param  Varien_Object $request
     * @return none
     */
    public function refundAdditionalParam($request)
    {
        $getParam = Mage::app()->getRequest();
        // Get bank account information for refund process
        $refundType = $getParam->getParam('refund_payment_type');
        $accountHolder = $getParam->getParam('nn_sepa_holder');
        $iban = $getParam->getParam('nn_sepa_iban');
        $bic = $getParam->getParam('nn_sepa_bic');
        // Get reference value for refund process
        $refundRef = $getParam->getParam('nn_refund_ref');

        if ($refundType == 'SEPA' && (!$iban || !$bic || !$accountHolder)) {
            $this->_helper->showException('Your account details are invalid');
        } elseif ($refundRef && preg_match('/[#%\^<>@$=*!]/', $refundRef)) {
            $this->_helper->showException('Your account details are invalid');
        }

        if ($refundRef) {
            $request->setRefundRef($refundRef); // Add reference param
        }

        if ($iban && $bic) {  // Add bank account params
            $request->setAccountHolder($accountHolder)
                ->setIban($iban)
                ->setBic($bic);
        }
    }

    /**
     * build recurring API process (cancel/suspend/active) request
     *
     * @param  Varien_Object                        $order
     * @param  Mage_Payment_Model_Recurring_Profile $profile
     * @return Varien_Object $request
     */
    public function buildRecurringApiRequest(Varien_Object $order, $profile)
    {
        $request = new Varien_Object();
        // Get Novalnet merchant authentication informations
        $request = $this->getprocessVendorInfo($order->getPayment());

        if ($profile->getNewState() == 'canceled') {
            $getRequest = Mage::app()->getRequest()->getQuery();
            $request->setNnLang(strtoupper($this->_helper->getDefaultLanguage()))
                ->setCancelSub(1)
                ->setCancelReason($getRequest['reason'])
                ->setTid($profile->getReferenceId());
        } elseif ($profile->getNewState() == 'suspended' || $profile->getNewState() == 'active') {
            $request = $this->recurringActiveSuspendRequest($order->getPayment(), $profile, $request);
        }

        return $request;
    }

    /**
     * build recurring API process (cancel/suspend/active) request
     *
     * @param  Varien_Object                        $payment
     * @param  Mage_Payment_Model_Recurring_Profile $profile
     * @return Varien_Object $request
     */
    public function recurringActiveSuspendRequest(Varien_Object $payment, $profile, $request)
    {
        $type = $profile->getNewState(); // Get recurring profile state
        $periodInfo = $this->getPeriodValues($profile);// Get subscription period frequency and unit
        $subsIdRequest = $payment->getAdditionalInformation('subs_id'); // Get subsId
        $suspend = ($type == 'suspended') ? 1 : 0;
        $pausePeriod = ($type == 'suspended') ? 1 : $periodInfo['periodFrequency'];
        $pausePeriodUnit = ($type == 'suspended') ? 'd' : $periodInfo['periodUnit'];

        $params = '<?xml version="1.0" encoding="UTF-8"?>';
        $params .= '<nnxml><info_request>';
        $params .= '<vendor_id>' . $request->getVendor() . '</vendor_id>';
        $params .= '<vendor_authcode>' . $request->getAuthCode() . '</vendor_authcode>';
        $params .= '<request_type>' . Novalnet_Payment_Model_Config::SUBS_PAUSE . '</request_type>';
        $params .= '<product_id>' . $request->getProduct() . '</product_id>';
        $params .= '<tid>' . $profile->getReferenceId() . '</tid>';
        $params .= '<subs_id>' . $subsIdRequest . '</subs_id>';
        $params .= '<pause_period>' . $pausePeriod . '</pause_period>';
        $params .= '<pause_time_unit>' . $pausePeriodUnit . '</pause_time_unit>';
        $params .= '<suspend>' . $suspend . '</suspend>';
        $params .= '</info_request></nnxml>';

        return $params;
    }

    /**
     * Get subscription period frequency and unit
     *
     * @param  Mage_Payment_Model_Recurring_Profile $profile
     * @return string
     */
    public function getPeriodValues($profile)
    {
        $periodFrequency = $profile->getperiodFrequency();
        $periodUnit = $this->_helper->__(ucfirst($profile->getperiodUnit()));
        $periodUnitFormat = array(
            $this->_helper->__('Day') => "d",
            $this->_helper->__('Month') => "m",
            $this->_helper->__('Year') => "y"
        );

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
     * Set recurring profile params for fraud prevention process
     *
     * @param  varien_object $request
     * @return none
     */
    public function getCallbackProfileParams($request)
    {
        $checkoutSession = $this->_helper->getCheckoutSession(); // Get checkout session
        $subsequentPeriod = $this->getNovalnetConfig('subsequent_period', true);
        $recurringTariffId = $this->getNovalnetConfig('subscrib_tariff_id', true);
        // Get order amount (Add initial fees if exist)
        $amount = $this->_helper->getFormatedAmount($checkoutSession->getNnRowAmount());
        // Get order amount
        $subsequentAmount = $this->_helper->getFormatedAmount($checkoutSession->getNnRegularAmount());
        $periodUnit = $checkoutSession->getNnPeriodUnit(); // Get recurring profile period unit
        $periodFrequency = $checkoutSession->getNnPeriodFrequency(); // Get recurring profile period frequency
        $periodUnitFormat = array("day" => "d", "month" => "m", "year" => "y");

        if ($periodUnit == "semi_month") {
            $tariffPeriod = "14d";
        } elseif ($periodUnit == "week") {
            $tariffPeriod = ($periodFrequency * 7) . "d";
        } else {
            $tariffPeriod = $periodFrequency . $periodUnitFormat[$periodUnit];
        }
        // Add recurring payment params
        $request->setTariff($recurringTariffId)
            ->setTariffPeriod($tariffPeriod)
            ->setTariffPeriod2($subsequentPeriod ? $subsequentPeriod : $tariffPeriod)
            ->setAmount($amount)
            ->setTariffPeriod2Amount($subsequentAmount);
    }

}
