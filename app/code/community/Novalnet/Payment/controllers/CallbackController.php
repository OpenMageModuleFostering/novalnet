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
 * @category  Novalnet
 * @package   Novalnet_Payment
 * @copyright Novalnet AG
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_CallbackController extends Mage_Core_Controller_Front_Action
{

    /**
     * Get Novalnet vendor script response
     *
     * @param  none
     * @return none
     */
    public function indexAction()
    {
        $this->_assignGlobalParams(); // Assign Global params for callback process

        if ($this->checkIP()) { // Check whether the IP address is authorized
            // Make reference log (Novalnet callback response) based on configuration settings
            if ($this->logTraces) {
                $fileName = 'novalnet_callback_script_' . $this->currentTime . '.log';
                Mage::log($this->response->getData(), null, $fileName, true);
            }

            // Make affiliate process
            if ($this->response->getVendorActivation()) {
                $this->affiliateProcess();
                return false;
            }

            $this->level = $this->getCallbackProcessLevel(); // Assign callback process level
            $this->orderNo = $this->getOrderIncrementId(); // Assign order number
            $this->order = Mage::getModel('sales/order')->loadByIncrementId($this->orderNo); // Get order object
            $this->storeId = $this->order->getStoreId(); // Get order store id
            $this->payment = $this->order->getPayment(); // Get payment object
            $this->code = $this->payment->getMethodInstance()->getCode(); // Get payment method code
            $this->paymentTxnId = $this->payment->getLastTransId(); // Get payment last transaction id
            $this->currency = $this->order->getOrderCurrencyCode(); // Get order currency
            $this->responseModel = $this->helper->getModel('Service_Api_Response');

            // Complete the order in-case response failure from Novalnet server
            $this->_handleCommunicationFailure();

            // Perform callback process for recurring and payment credit related process
            if ($this->_checkParams()) {
                $this->_callbackProcess();
                $this->sendCallbackMail(); // Send callback notification E-mail
            } else {
                $this->showDebug($this->responseModel->getStatusText($this->response));
            }
        }
    }

    /**
     * Assign Global params for callback process
     *
     * @param  none
     * @return none
     */
    protected function _assignGlobalParams()
    {
        // Get callback setting params (from shop admin)
        $this->debug = Mage::getStoreConfig('novalnet_global/merchant_script/debug_mode');
        $this->test = Mage::getStoreConfig('novalnet_global/merchant_script/test_mode');
        $this->logTraces = Mage::getStoreConfig('novalnet_global/merchant_script/vendor_script_log');
        // Get Novalnet callback response values
        $params = Mage::app()->getRequest()->getPost()
            ? Mage::app()->getRequest()->getPost()
            : Mage::app()->getRequest()->getQuery();
        $params = array_filter($params);
        if (empty($params)) {
            $this->showDebug('Novalnet callback received. No params passed over!');
        }

        $this->response = new Varien_Object();
        $this->response->setData($params); // Assign response params to object data
        // Novalnet IP, is a fixed value, DO NOT CHANGE !!!!!
        $this->ipAllowed = array('195.143.189.210', '195.143.189.214');
        $this->currentTime = Mage::getModel('core/date')->date('Y-m-d H:i:s'); // Get current time for callback process
        $httpHost = Mage::helper('core/http')->getHttpHost();
        $this->lineBreak = empty($httpHost) ? PHP_EOL : '<br />';
        $this->helper = Mage::helper('novalnet_payment'); // Novalnet payment helper
        $this->callback = $this->recurring = false;

        // Assign callback process payment types
        $this->callbackPaymentTypes();
    }

    /**
     * Assign callback process payment types
     *
     * @param  none
     * @return none
     */
    public function callbackPaymentTypes()
    {
        $this->allowedPayment = array(
            'novalnetcc' => array('CREDITCARD', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK',
                'CREDIT_ENTRY_CREDITCARD', 'SUBSCRIPTION_STOP', 'DEBT_COLLECTION_CREDITCARD'),
            'novalnetinvoice' => array('INVOICE_START', 'INVOICE_CREDIT', 'SUBSCRIPTION_STOP',
                'GUARANTEED_INVOICE_START', 'GUARANTEED_INVOICE_CREDIT'),
            'novalnetprepayment' => array('INVOICE_START', 'INVOICE_CREDIT', 'SUBSCRIPTION_STOP',
                'GUARANTEED_INVOICE_START', 'GUARANTEED_INVOICE_CREDIT'),
            'novalnetideal' => array('IDEAL'),
            'novalnetpaypal' => array('PAYPAL', 'PAYPAL_BOOKBACK', 'SUBSCRIPTION_STOP'),
            'novalneteps' => array('EPS'),
            'novalnetbanktransfer' => array('ONLINE_TRANSFER', 'REFUND_BY_BANK_TRANSFER_EU'),
            'novalnetsepa' => array('DIRECT_DEBIT_SEPA', 'RETURN_DEBIT_SEPA', 'SUBSCRIPTION_STOP',
                'DEBT_COLLECTION_SEPA', 'CREDIT_ENTRY_SEPA', 'GUARANTEED_DIRECT_DEBIT_SEPA'));
        $this->invoiceAllowed = array('INVOICE_CREDIT', 'INVOICE_START');
        $this->recurringAllowed = array('INVOICE_CREDIT', 'INVOICE_START', 'CREDITCARD',
            'DIRECT_DEBIT_SEPA', 'SUBSCRIPTION_STOP', 'GUARANTEED_DIRECT_DEBIT_SEPA',
            'GUARANTEED_INVOICE_START', 'GUARANTEED_INVOICE_CREDIT', 'PAYPAL');
        // Array Type of payment available - Level : 0
        $this->paymentTypes = array('CREDITCARD', 'INVOICE_START', 'DIRECT_DEBIT_SEPA',
            'GUARANTEED_INVOICE_START', 'PAYPAL', 'ONLINE_TRANSFER', 'IDEAL',
            'EPS', 'PAYSAFECARD', 'GIROPAY', 'GUARANTEED_DIRECT_DEBIT_SEPA');
        // Array Type of Chargebacks available - Level : 1
        $this->chargebacks = array('CREDITCARD_CHARGEBACK', 'RETURN_DEBIT_SEPA',
            'CREDITCARD_BOOKBACK', 'REFUND_BY_BANK_TRANSFER_EU', 'PAYPAL_BOOKBACK');
        // Array Type of CreditEntry payment and Collections available - Level : 2
        $this->aryCollection = array('INVOICE_CREDIT', 'GUARANTEED_INVOICE_CREDIT',
            'CREDIT_ENTRY_CREDITCARD', 'CREDIT_ENTRY_SEPA', 'DEBT_COLLECTION_SEPA',
            'DEBT_COLLECTION_CREDITCARD');
        $this->arySubscription = array('SUBSCRIPTION_STOP');
    }

    /**
     * Get email config
     *
     * @param  none
     * @return none
     */
    public function getEmailConfig()
    {
        $this->emailSendOption = Mage::getStoreConfig('novalnet_global/merchant_script/mail_send_option');
        $this->useEmailOption = Mage::getStoreConfig('novalnet_global/merchant_script/use_mail_option');
        $this->emailFromAddr = Mage::getStoreConfig('trans_email/ident_general/email');
        $this->emailFromName = Mage::getStoreConfig('trans_email/ident_general/name');
        $this->emailToAddr = Mage::getStoreConfig('novalnet_global/merchant_script/mail_to_addr');
        $this->emailToName = 'store admin'; // Adapt for your need
        $this->emailBCcAddr = Mage::getStoreConfig('novalnet_global/merchant_script/mail_bcc_addr');
        $this->emailSubject = 'Novalnet Callback Script Access Report';
        //Reporting Email Addresses Settings
        $this->shopInfo = 'Magento ' . $this->lineBreak; //mandatory;adapt for your need
        $this->mailHost = Mage::getStoreConfig('system/smtp/host');
        $this->mailPort = Mage::getStoreConfig('system/smtp/port');
    }

    /**
     * Log Affiliate account details
     *
     * @param  none
     * @return none
     */
    public function affiliateProcess()
    {
        $paramsRequired = $this->getRequiredParams(); // Get required params for callback process
        // Check the necessary params for callback script process
        foreach ($paramsRequired as $param) {
            if (!$this->response->getData($param)) {
                $this->showDebug('Required param (' . $param . ') missing!');
            }
        }

        $affiliateModel = $this->helper->getModel('Mysql4_AffiliateInfo');
        $affiliateModel->setVendorId($this->response->getVendorId())
            ->setVendorAuthcode($this->response->getVendorAuthcode())
            ->setProductId($this->response->getProductId())
            ->setProductUrl($this->response->getProductUrl())
            ->setActivationDate($this->response->getActivationDate())
            ->setAffId($this->response->getAffId())
            ->setAffAuthcode($this->response->getAffAuthcode())
            ->setAffAccesskey($this->response->getAffAccesskey())
            ->save();
        // Send notification mail to Merchant
        $message = 'Novalnet callback script executed successfully with Novalnet account activation information.';
        $this->emailBody = $message;
        $this->sendCallbackMail(); // Send callback notification E-mail
    }

    /**
     * Complete the order in-case response failure from Novalnet server
     *
     * @param  none
     * @return none
     */
    protected function _handleCommunicationFailure()
    {
        $successActionFlag = $this->code . '_successAction';

        if (empty($this->paymentTxnId)
            && $this->payment->getAdditionalInformation($successActionFlag) != 1
        ) {
            $this->payment->setAdditionalInformation($successActionFlag, 1)->save();
            // Unhold an order
            if ($this->order->canUnhold()) {
                $this->order->unhold()->save();
            }

            // Save transaction additional information
            $transactionId = $this->response->getTid();
            // Get payment mode from Novalnet global configuration
            $responsePaymentMode = $this->response->getTestMode();
            $shopMode = $this->_getConfig('live_mode'); // Get payment mode from callback response
            $testMode = ($responsePaymentMode == 1 || $shopMode == 0) ? 1 : 0; // Get payment process mode
            $paymentId = $this->helper->getPaymentId($this->code); // Get payment key
            $confirmText = 'Novalnet Callback Script executed successfully on ' . $this->currentTime;
            $data = array('NnTestOrder' => $testMode, 'NnTid' => $transactionId,
                'vendor' => $this->_getConfig('merchant_id'),
                'auth_code' => $this->_getConfig('auth_code'),
                'product' => $this->_getConfig('product_id'),
                'tariff' => $this->_getConfig('tariff_id'),
                'payment_id'=> $paymentId,
                'NnComments' => $confirmText
                );
            $data['paidUntil'] = $this->response->hasNextSubsCycle() ? $this->response->getNextSubsCycle()
                    : ($this->response->hasPaidUntil() ? $this->response->getPaidUntil() : '');
            // Get payment additional information
            $additionalData = unserialize($this->payment->getAdditionalData());
            // Merge payment additional information if exist
            $data = $additionalData ? array_merge($additionalData, $data) : $data;

            // Save the payment transaction information
            $this->payment->setTransactionId($transactionId)
                ->setLastTransId($transactionId)
                ->setParentTransactionId(null)
                ->setAdditionalData(serialize($data));

            // Capture the payment
            if ($this->order->canInvoice()
                && $this->response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
                && $this->response->getTidStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
            ) {
                // Save payment information with invoice for Novalnet successful transaction
                $this->payment->setIsTransactionClosed(true)
                    ->capture(null);
            }
            $this->payment->save();

            // Get payment request params
            $request = new Varien_Object();
            $traces = $this->helper->getModel('Mysql4_TransactionTraces')
                ->loadByAttribute('order_id', $this->orderNo);
            $request->setData(unserialize($traces->getRequestData()));
            $this->responseModel->logTransactionStatus($this->response, $this->order); // Log Novalnet payment transaction informations
            $this->savePayportResponse(); // Log Novalnet payment transaction traces informations

            // Set order status based on Novalnet transaction status
            if ($this->response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
                && in_array($this->response->getTidStatus(), array(100, 90, 91, 98, 99))
            ) {
                $this->setRecurringProfileState(); // Assign recurring profile state if profile exist
                $orderStatus = $this->getOrderStatus(); // Get order status
                $this->order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $orderStatus, $this->helper->__('Customer successfully returned from Novalnet'), true)->save();
                $this->paymentTxnId = $transactionId; // Get payment last transaction id
                // Send order email for successful Novalnet transaction
                Mage::dispatchEvent('novalnet_sales_order_email', array('order' => $this->order));
            } else {
                $this->setRecurringProfileState('canceled'); // Assign recurring profile state if profile exist
                $this->responseModel->saveCanceledOrder($this->response, $this->order, $testMode); // Cancel the order based on Novalnet transaction status
            }

            $this->order->save(); // Save the current order
            $this->showDebug($confirmText);
        }
    }

    /**
     * Check the callback mandatory parameters.
     *
     * @param  none
     * @return boolean
     */
    protected function _checkParams()
    {
        $paramsRequired = $this->getRequiredParams(); // Get required params for callback process
        $amount = $this->response->getAmount() ? (int) $this->response->getAmount() : '';

        // Check the necessary params for callback script process
        foreach ($paramsRequired as $param) {
            if ($param == 'amount' && (!$amount)) {
                $this->showDebug('Required param (amount) missing!');
            } elseif ($param != 'amount' && !$this->response->getData($param)) {
                $this->showDebug('Required param (' . $param . ') missing!');
            }
        }

        // Check whether Novalnet Tid is valid
        $transactionId = $this->getParentTid(); // Get the original/parent transaction id

        if (!preg_match('/^\d{17}$/', $transactionId)) {
            $this->showDebug('Novalnet callback received. Invalid TID [' . $transactionId . '] for Order :' . $this->orderNo);
        }

        $referenceTid = ($transactionId != $this->response->getTid()) ? $this->response->getTid() : '';

        if ($referenceTid && !preg_match('/^\d{17}$/', $referenceTid)) {
            $this->showDebug('Novalnet callback received. Invalid TID [' . $referenceTid . '] for Order :' . $this->orderNo);
        }

        // Check whether payment type is valid
        $paymentType = $this->allowedPayment[strtolower($this->code)];
        if (!in_array($this->response->getPaymentType(), $paymentType)) {
            $this->showDebug("Novalnet callback received. Payment type (" . $this->response->getPaymentType() . ") is not matched with $this->code!");
        }

        if ($this->recurring && $this->response->getSubsBilling() && $this->response->getSignupTid()) {
            $profile = $this->getProfileInformation(); // Get the Recurring Profile Information
            if ($profile->getState() == 'canceled') {
                // Get parent order object for given recurring profile
                $profileOrders = $this->helper->getModel('Mysql4_Recurring')->getRecurringOrderNo($profile);
                $parentOrder = Mage::getModel('sales/order')->loadByIncrementId($profileOrders[0]);
                $this->showDebug('Subscription already Cancelled. Refer Order : ' . $parentOrder->getIncrementId());
            }
        } else {
            $additionalData = unserialize($this->payment->getAdditionalData());
            $orderTid = (in_array($this->response->getPaymentType(), $this->chargebacks)
                            && $additionalData['NnTid']) ? $additionalData['NnTid'] : $this->paymentTxnId;
            if (!preg_match('/^' . $transactionId . '/i', $orderTid)) {
                $this->showDebug('Novalnet callback received. Order no is not valid');
            }
        }

        if (($this->response->getPaymentType() == 'INVOICE_CREDIT'
            || in_array($this->response->getPaymentType(), $this->chargebacks))
            && ($this->response->getStatus() != Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
            || $this->response->getTidStatus() != Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED)
        ) {
            $this->showDebug('Novalnet callback received. Status is not valid. Refer Order :' . $this->orderNo);
        }

        return true;
    }

    /**
     * Get required params for callback process
     *
     * @param  none
     * @return array $paramsRequired
     */
    public function getRequiredParams()
    {
        $paramsRequired = array('amount', 'payment_type', 'status', 'tid_status', 'tid', 'vendor_id');

        if ($this->response->getVendorActivation()) {
            $paramsRequired = array('vendor_id', 'vendor_authcode', 'product_id',
                'product_url', 'activation_date', 'aff_id', 'aff_authcode', 'aff_accesskey'
            );
        } elseif ($this->callback) {
            $invoicePayments = array_merge($this->invoiceAllowed, $this->chargebacks);
            if ((in_array($this->response->getPaymentType(), $invoicePayments))) {
                array_push($paramsRequired, 'tid_payment');
            }
        } elseif ($this->recurring) {
            array_push($paramsRequired, 'signup_tid');
            if ($this->response->getPaymentType() == 'SUBSCRIPTION_STOP') {
                unset($paramsRequired[0]);
            }
        }

        return $paramsRequired;
    }

    /**
     * Perform callback process
     *
     * @param  none
     * @return boolean
     */
    protected function _callbackProcess()
    {
        if ($this->order) {
            if ($this->level == 1
                && $this->response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
                && $this->response->getTidStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
            ) { // Level 1 payments - Type of Chargebacks
                $this->_refundProcess();
                return true;
            }

            if ($this->response->getPaymentType() == 'SUBSCRIPTION_STOP') { // Cancellation of a subscription
                $this->_subscriptionCancel();
                return true;
            }

            if ($this->level == 0 || $this->level == 2) {
                if ($this->recurring) {  // Handle subscription process
                    $this->_recurringProcess();
                    return true;
                }

                $saveInvoice = '';
                if ($this->response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
                    && $this->response->getTidStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
                ) {
                    $saveInvoice = $this->_saveInvoice(); // Handle payment credit process
                }

                $invoice = $this->order->getInvoiceCollection()->getFirstItem(); // Get order invoice items
                if ($invoice && $saveInvoice) { // Handle payment credit process
                    $this->_updateOrderStatus(); // Update order status for payment credit process
                }
            }
        } else {
            $this->showDebug("Novalnet Callback: No order for Increment-ID $this->orderNo found.");
        }
    }

    /**
     * Handle payment chargeback and bookback process
     *
     * @param  none
     * @return none
     */
    protected function _refundProcess()
    {
        // Update callback comments for Chargebacks
        if (in_array($this->response->getPaymentType(), $this->chargebacks)) {
            $bookBack = array('CREDITCARD_BOOKBACK', 'PAYPAL_BOOKBACK', 'REFUND_BY_BANK_TRANSFER_EU');
            $transactionId = !$this->recurring ? $this->response->getTidPayment() : $this->response->getSignupTid();
            $message = (in_array($this->response->getPaymentType(), $bookBack)) ? 'Refund/Bookback' : 'Chargeback';
            $this->emailBody = $script = 'Novalnet callback received. ' . $message . ' executed successfully for the TID: ' . $transactionId . ' amount: ' . ($this->response->getAmount())/ 100 . ' ' . $this->currency . " on " . $this->currentTime . '. The subsequent TID: ' . $this->response->getTid();
            $data = unserialize($this->payment->getAdditionalData());
            $data['NnComments'] = empty($data['NnComments'])
                ? '<br>' . $script : $data['NnComments'] . '<br>' . $script;
            $this->payment->setAdditionalData(serialize($data))->save();
        }
    }

    /**
     * Handle subscription cancel process
     *
     * @param  none
     * @return none
     */
    protected function _subscriptionCancel()
    {
        // Update the status of the user subscription
        $statusText = $this->response->getTerminationReason()
            ? $this->response->getTerminationReason() : $this->responseModel->getStatusText($this->response);
        $script = 'Novalnet Callback script received. Subscription has been stopped for the TID:' . $this->response->getSignupTid() . " on " . $this->currentTime;
        $script .= '<br>Subscription has been canceled due to : ' . $statusText;
        $this->emailBody = $script;
        $profile = $this->getProfileInformation(); // Get the Recurring Profile Information
        $profile->setState('canceled')->save(); // Set profile status as canceled

        // Get parent order object for given recurring profile
        $profileOrders = $this->helper->getModel('Mysql4_Recurring')->getRecurringOrderNo($profile);
        $parentOrder = Mage::getModel('sales/order')->loadByIncrementId($profileOrders[0]);
        $parentPayment = $parentOrder->getPayment();

        // Save additional transaction information
        $data = unserialize($parentPayment->getAdditionalData());
        $data['NnComments'] = empty($data['NnComments']) ? '<br>' . $script : $data['NnComments'] . '<br>' . $script;
        $parentPayment->setAdditionalData(serialize($data))->save();
    }

    /**
     * Handle recurring payment process
     *
     * @param  none
     * @return boolean
     */
    protected function _recurringProcess()
    {
        $recurringTypes = array('CREDITCARD', 'DIRECT_DEBIT_SEPA', 'GUARANTEED_DIRECT_DEBIT_SEPA', 'INVOICE_START',
            'GUARANTEED_INVOICE_START', 'PAYPAL');
        $profile = $this->getProfileInformation(); // Get the Recurring Profile Information

        if ($this->response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
            && in_array($this->response->getPaymentType(), $recurringTypes)
        ) {
            $script = 'Novalnet Callback Script executed successfully for the subscription TID ' . $this->response->getSignupTid() . ' with amount ' . ($this->response->getAmount()) / 100 . ' ' . $this->currency . ' ' . " on " . $this->currentTime . '.';
            // Save subscription callback values
            $callbackCycle = $this->saveRecurringProcess($profile->getPeriodMaxCycles(), $profile->getId());
            // Verify subscription period (cycles)
            $this->verifyRecurringCycle($profile, $callbackCycle);
            // Create recurring payment order
            $this->_createOrder($script, $profile->getId());
        } else {
            if ($profile->getState() != 'canceled') {  // Cancellation of a subscription
                $this->_subscriptionCancel();
            }
        }
        return true;
    }

    /**
     * Save subscription callback informations
     *
     * @param  int $periodMaxCycles
     * @param  int $profileId
     * @return int $callbackCycle
     */
    public function saveRecurringProcess($periodMaxCycles, $profileId)
    {
        $recurringModel = $this->helper->getModel('Mysql4_Recurring');
        $recurringCollection = $recurringModel->getCollection();
        $recurringCollection->addFieldToFilter('profile_id', $profileId);
        $recurringCollection->addFieldToSelect('callbackcycle');
        $countRecurring = count($recurringCollection);
        if ($countRecurring == 0) {
            $callbackCycle = 1;
            $recurringModel->setProfileId($profileId)
                ->setSignupTid($this->response->getSignupTid())
                ->setBillingcycle($periodMaxCycles)
                ->setCallbackcycle($callbackCycle)
                ->setCycleDatetime($this->currentTime)
                ->save();
        } else {
            foreach ($recurringCollection as $profile) {
                $callbackCycle = $profile->getCallbackcycle();
            }
            $callbackCycle = ++$callbackCycle;
            $recurring = $recurringModel->load($profileId, 'profile_id');
            $recurring->setCallbackcycle($callbackCycle)
                ->setCycleDatetime($this->currentTime)
                ->save();
        }
        return $callbackCycle;
    }

    /**
     * Verify subscription period (cycles)
     *
     * @param  int $profile
     * @param  int $callbackCycle
     * @return none
     */
    public function verifyRecurringCycle($profile, $callbackCycle)
    {
        $periodMaxCycles = $profile->getPeriodMaxCycles();
        $this->endTime = 0;

        if ($callbackCycle == $periodMaxCycles) {
            // Get parent order object for given recurring profile
            $profileOrders = $this->helper->getModel('Mysql4_Recurring')->getRecurringOrderNo($profile);
            $this->parentOrder = Mage::getModel('sales/order')->loadByIncrementId($profileOrders[0]);
            $this->parentPayment = $this->parentOrder->getPayment();
            $this->parentPaymentObj = $this->parentPayment->getMethodInstance();

            // Send subscription cancel request to Novalnet
            $response = $this->initiateSubscriptionCancel();

            // Save additional transaction information
            $data = unserialize($this->parentPayment->getAdditionalData());
            $data['subsCancelReason'] = 'other';
            $this->parentPayment->setAdditionalData(serialize($data))->save();

            if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                $profile->setState('canceled')->save();
                $this->endTime = 1;
            } else {
                $this->endTime = 0;
            }
        }
    }

    /**
     * Send subscription cancel request to Novalnet
     *
     * @param  none
     * @return Varien_Object $response
     */
    public function initiateSubscriptionCancel()
    {
        $traces = $this->helper->getModel('Mysql4_TransactionTraces')
            ->loadByAttribute('order_id', $this->parentOrder->getIncrementId());
        $paymentRequest = unserialize($traces->getRequestData());
        $additionalData = unserialize($this->parentPayment->getAdditionalData());
        // Build subscription cancel request
        $request = new Varien_Object();
        $request->setVendor($additionalData['vendor'])
            ->setAuthCode($additionalData['auth_code'])
            ->setProduct($additionalData['product'])
            ->setTariff($additionalData['tariff'])
            ->setKey($paymentRequest['key'])
            ->setNnLang($paymentRequest['lang'])
            ->setCancelSub(1)
            ->setCancelReason('other')
            ->setTid($this->response->getSignupTid());
        // Send recurring cancel request to Novalnet gateway
        $response = $this->parentPaymentObj->postRequest($request);
        // Log Novalnet payment transaction informations
        $this->responseModel->logTransactionTraces($request, $response, $this->parentOrder, $request->getTid());
        return $response;
    }

    /**
     * New order create process
     *
     * @param  string $script
     * @param  int    $profileId
     * @return none
     */
    protected function _createOrder($script, $profileId)
    {
        $this->setLanguageStore(); // Set the language by store id
        $orderNew = Mage::getModel('sales/order')
                ->setState('new');

        $orderPayment = Mage::getModel('sales/order_payment')
                ->setStoreId($this->storeId)
                ->setMethod($this->code)
                ->setPo_number('-');
        $orderNew->setPayment($orderPayment);
        $orderNew = $this->setOrderDetails($this->order, $orderNew);
        $billingAddress = Mage::getModel('sales/order_address');
        $getBillingAddress = Mage::getModel('sales/order_address')->load($this->order->getBillingAddress()->getId());
        $orderNew = $this->setBillingShippingAddress($getBillingAddress, $billingAddress, $orderNew, $this->order);

        if ($this->order->getIsVirtual() == 0) {
            $shippingAddress = Mage::getModel('sales/order_address');
            $getShipping = Mage::getModel('sales/order_address')->load($this->order->getShippingAddress()->getId());
            $orderNew = $this->setBillingShippingAddress($getShipping, $shippingAddress, $orderNew, $this->order);
        }

        $orderNew = $this->setOrderItemsDetails($this->order, $orderNew);
        $paymentNew = $orderNew->getPayment();
        $orderStatus = $this->getOrderStatus(); // Get order status
        $message = $this->helper->__('Novalnet Recurring Callback script Executed Successfully');
        $orderNew->addStatusToHistory($orderStatus, $message, false)->save();

        $transactionId = trim($this->response->getTid());
        $data = $this->getPaymentAddtionaldata($orderNew, $script);
        $additionalInfo = $this->payment->getAdditionalInformation();
        // Save payment transaction informations
        $paymentNew->setTransactionId($transactionId)
            ->setAdditionalData(serialize($data))
            ->setAdditionalInformation($additionalInfo)
            ->setLastTransId($transactionId)
            ->setParentTransactionId(null)
            ->save();
        // Send new order email
        $orderNew->sendNewOrderEmail()
            ->setEmailSent(true)
            ->setPayment($paymentNew)
            ->save();

        $this->insertOrderId($orderNew->getId(), $profileId); // Insert the order id in recurring order table
        $this->updateInventory($this->order); // Update the product inventory (stock)
        // Log Novalnet payment transaction informations
        $this->responseModel->logTransactionStatus($this->response, $orderNew);

        if ($orderNew->canInvoice() && $this->code != Novalnet_Payment_Model_Config::NN_PREPAYMENT
            && $this->response->getTidStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
        ) {
            $this->createInvoice($orderNew, $transactionId);
        }
    }

    /**
     * Get payment method additional informations
     *
     * @param Varien_Object $orderNew
     * @param mixed         $script
     * @param array         $data
     */
    public function getPaymentAddtionaldata($orderNew, $script)
    {
        $parentOrderNo = $this->getOrderIdByTransId() ? $this->getOrderIdByTransId() : $orderNew->getIncrementId();
        $subsCycleDate = $this->response->getNextSubsCycle()
            ? $this->response->getNextSubsCycle() : $this->response->getPaidUntil();
        $script .= $comments = ' Reference order id : ' . $parentOrderNo . '<br>';
        $script .= $nextDate = !$this->endTime ? '<br>Next charging date : ' . $subsCycleDate . '<br>' : '';
        $this->emailBody = $script;

        $parentAdditionalData = unserialize($this->payment->getAdditionalData());
        $data = array('NnTestOrder' => $this->response->getTestMode(),
                    'NnTid' => trim($this->response->getTid()),
                    'NnComments' => ($comments . $nextDate),
                    'vendor' => $parentAdditionalData['vendor'],
                    'auth_code' => $parentAdditionalData['auth_code'],
                    'product' => $parentAdditionalData['product'],
                    'tariff' => $parentAdditionalData['tariff'],
                    'payment_id' => $parentAdditionalData['payment_id'],
                );

        if (in_array($this->code, array('novalnetInvoice', 'novalnetPrepayment'))) {
            $amount = Mage::helper('core')->currency($this->response->getAmount()/100, true, false);
            $data['NnNote'] = $this->responseModel->getInvoicePaymentNote($this->response);
            $data['NnDueDate'] = $this->response->getDueDate();
            $data['NnNoteAmount'] = 'NN_Amount: ' . $amount;
        }

        return $data;
    }

    /**
     * Create order invoice
     *
     * @param Varien_Object $orderNew
     * @param int           $transactionId
     * @param none
     */
    public function createInvoice($order, $transactionId)
    {
        $invoice = $order->prepareInvoice();
        $invoice->setTransactionId($transactionId);
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE)
            ->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)
            ->register();

        Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();

        $transMode = (version_compare($this->helper->getMagentoVersion(), '1.6', '<')) ? false : true;
        $payment = $order->getPayment();
        $payment->setTransactionId($transactionId)
            ->setIsTransactionClosed($transMode);
        $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, null, false);
        $transaction->setParentTxnId(null)
            ->save();
    }

    /**
     * Set the language based on store id
     *
     * @param  int $storeId
     * @return none
     */
    public function setLanguageStore()
    {
        $app = Mage::app();
        $app->setCurrentStore($this->storeId);
        $locale = Mage::getStoreConfig('general/locale/code', $this->storeId);
        $app->getLocale()->setLocaleCode($locale);
        Mage::getSingleton('core/translate')->setLocale($locale)->init('frontend', true);
    }

    /**
     * Set order item and customer informations
     *
     * @param  Varien_Object $order
     * @param  Varien_Object $orderNew
     * @return Varien_Object $orderNew
     */
    public function setOrderDetails($order, $orderNew)
    {
        $orderNew->setStoreId($order->getStoreId())
            ->setCustomerGroupId($order->getCustomerGroupId())
            ->setQuoteId(0)
            ->setIsVirtual($order->getIsVirtual())
            ->setGlobalCurrencyCode($order->getGlobalCurrencyCode())
            ->setBaseCurrencyCode($order->getBaseCurrencyCode())
            ->setStoreCurrencyCode($order->getStoreCurrencyCode())
            ->setOrderCurrencyCode($order->getOrderCurrencyCode())
            ->setStoreName($order->getStoreName())
            ->setCustomerEmail($order->getCustomerEmail())
            ->setCustomerFirstname($order->getCustomerFirstname())
            ->setCustomerLastname($order->getCustomerLastname())
            ->setCustomerId($order->getCustomerId())
            ->setCustomerIsGuest($order->getCustomerIsGuest())
            ->setState('processing')
            ->setStatus($order->getStatus())
            ->setSubtotal($order->getSubtotal())
            ->setBaseSubtotal($order->getBaseSubtotal())
            ->setSubtotalInclTax($order->getSubtotalInclTax())
            ->setBaseSubtotalInclTax($order->getBaseSubtotalInclTax())
            ->setShippingAmount($order->getShippingAmount())
            ->setBaseShippingAmount($order->getBaseShippingAmount())
            ->setGrandTotal($order->getGrandTotal())
            ->setBaseGrandTotal($order->getBaseGrandTotal())
            ->setTaxAmount($order->getTaxAmount())
            ->setTotalQtyOrdered($order->getTotalQtyOrdered())
            ->setBaseTaxAmount($order->getBaseTaxAmount())
            ->setBaseToGlobalRate($order->getBaseToGlobalRate())
            ->setBaseToOrderRate($order->getBaseToOrderRate())
            ->setStoreToBaseRate($order->getStoreToBaseRate())
            ->setStoreToOrderRate($order->getStoreToOrderRate())
            ->setWeight($order->getWeight())
            ->setCustomerNoteNotify($order->getCustomerNoteNotify());
        return $orderNew;
    }

    /**
     * Set billing and shipping address informations
     *
     * @param  Varien_Object $getBillingAddress
     * @param  Varien_Object $billingAddress
     * @param  Varien_Object $orderNew
     * @param  Varien_Object $order
     * @return mixed
     */
    public function setBillingShippingAddress($getBillingAddress, $billingAddress, $orderNew, $order)
    {
        $addressType = $getBillingAddress->getAddressType();
        $billingStreet = $getBillingAddress->getStreet();
        $street = !empty($billingStreet[1])
            ? array($billingStreet[0], $billingStreet[1]) : array($billingStreet[0]);
        $billingAddress->setStoreId($order->getStoreId())
            ->setAddressType($addressType)
            ->setPrefix($getBillingAddress->getPrefix())
            ->setFirstname($getBillingAddress->getFirstname())
            ->setLastname($getBillingAddress->getLastname())
            ->setMiddlename($getBillingAddress->getMiddlename())
            ->setSuffix($getBillingAddress->getSuffix())
            ->setCompany($getBillingAddress->getCompany())
            ->setStreet($street)
            ->setCity($getBillingAddress->getCity())
            ->setCountryId($getBillingAddress->getCountryId())
            ->setRegionId($getBillingAddress->getRegionId())
            ->setTelephone($getBillingAddress->getTelephone())
            ->setFax($getBillingAddress->getFax())
            ->setVatId($getBillingAddress->getVatId())
            ->setPostcode($getBillingAddress->getPostcode());

        if ($addressType == Mage_Sales_Model_Quote_Address::TYPE_BILLING) {
            $orderNew->setBillingAddress($billingAddress);
        } else {
            $shippingMethod = $order->getShippingMethod();
            $shippingDescription = $order->getShippingDescription();
            $orderNew->setShippingAddress($billingAddress)
                ->setShippingMethod($shippingMethod)
                ->setShippingDescription($shippingDescription);
        }

        return $orderNew;
    }

    /**
     * Set product informations (product, discount, tax, etc.,)
     *
     * @param  Varien_Object $order
     * @param  Varien_Object $orderNew
     * @return mixed
     */
    public function setOrderItemsDetails($order, $orderNew)
    {
        foreach ($order->getAllItems() as $orderValue) {
            $orderItem = Mage::getModel('sales/order_item')
                    ->setStoreId($orderValue->getStoreId())
                    ->setQuoteItemId(0)
                    ->setQuoteParentItemId(null)
                    ->setQtyBackordered(null)
                    ->setQtyOrdered($orderValue->getQtyOrdered())
                    ->setName($orderValue->getName())
                    ->setIsVirtual($orderValue->getIsVirtual())
                    ->setProductId($orderValue->getProductId())
                    ->setProductType($orderValue->getProductType())
                    ->setSku($orderValue->getSku())
                    ->setWeight($orderValue->getWeight())
                    ->setPrice($orderValue->getPrice())
                    ->setBasePrice($orderValue->getBasePrice())
                    ->setOriginalPrice($orderValue->getOriginalPrice())
                    ->setTaxAmount($orderValue->getTaxAmount())
                    ->setTaxPercent($orderValue->getTaxPercent())
                    ->setIsNominal($orderValue->getIsNominal())
                    ->setRowTotal($orderValue->getRowTotal())
                    ->setBaseRowTotal($orderValue->getBaseRowTotal())
                    ->setBaseWeeeTaxAppliedAmount($orderValue->getBaseWeeeTaxAppliedAmount())
                    ->setWeeeTaxAppliedAmount($orderValue->getWeeeTaxAppliedAmount())
                    ->setWeeeTaxAppliedRowAmount($orderValue->getWeeeTaxAppliedRowAmount())
                    ->setWeeeTaxApplied($orderValue->getWeeeTaxApplied())
                    ->setWeeeTaxDisposition($orderValue->getWeeeTaxDisposition())
                    ->setWeeeTaxRowDisposition($orderValue->getWeeeTaxRowDisposition())
                    ->setBaseWeeeTaxDisposition($orderValue->getBaseWeeeTaxDisposition())
                    ->setBaseWeeeTaxRowDisposition($orderValue->getBaseWeeeTaxRowDisposition());
            $orderNew->addItem($orderItem);
        }

        return $orderNew;
    }

    /**
     * Insert the order id in recurring order table
     *
     * @param  int $newOrderId
     * @param  int $profileId
     * @return none
     */
    public function insertOrderId($newOrderId, $profileId)
    {
        if ($newOrderId && $profileId) {
            $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
            $connection->beginTransaction();
            $tablePrefix = Mage::getConfig()->getTablePrefix();
            $orderTable = $tablePrefix . 'sales_recurring_profile_order';
            $fields = array();
            $fields['profile_id'] = $profileId;
            $fields['order_id'] = $newOrderId;
            $connection->insert($orderTable, $fields);
            $connection->commit();
        }
    }

    /**
     * Update the product inventory (stock)
     *
     * @param  Varien_Object $order
     * @return none
     */
    public function updateInventory($order)
    {
        foreach ($order->getAllItems() as $orderValue) {
            $itemsQtyOrdered = floor($orderValue->getQtyOrdered());
            $productId = $orderValue->getProductId();
            break;
        }

        if ($productId) {
            $stockObj = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
            $productQtyBefore = (int) $stockObj->getQty();
        }

        if (isset($productQtyBefore) && $productQtyBefore > 0) {
            $productQtyAfter = (int) ($productQtyBefore - $itemsQtyOrdered);
            $stockObj->setQty($productQtyAfter);
            $stockObj->save();
        }
    }

    /**
     * Create invoice to order payment
     *
     * @param  none
     * @return boolean
     */
    protected function _saveInvoice()
    {
        $data = unserialize($this->payment->getAdditionalData());
        $callbackModel = $this->helper->getModel('Mysql4_Callback')->loadLogByOrderId($this->orderNo);
        $tidPayment = (!$this->recurring) ? $this->response->getTidPayment() : $this->response->getSignupTid();
        $totalAmount = sprintf(($this->response->getAmount() + $callbackModel->getCallbackAmount()), 0.2);
        $grandTotal = sprintf(($this->order->getGrandTotal() * 100), 0.2);
        $amount = $this->helper->getFormatedAmount($this->response->getAmount(), 'RAW');

        if (in_array($this->response->getPaymentType(), $this->invoiceAllowed) && $totalAmount < $grandTotal) {
            $this->logCallbackInfo($callbackModel, $totalAmount, $this->orderNo); // Log callback data
            $this->emailBody = "Novalnet Callback Script executed successfully for the TID: " . $tidPayment . " with amount " . $amount . " " . $this->currency . " on " . $this->currentTime . ". Please refer PAID transaction in our Novalnet Merchant Administration with the TID: " . $this->response->getTid() . "$this->lineBreak$this->lineBreak";
            $script = "Novalnet Callback Script executed successfully for the TID: " . $tidPayment . " with amount " . $amount . " " . $this->currency . " on " . $this->currentTime . ". Please refer PAID transaction in our Novalnet Merchant Administration with the TID: " . $this->response->getTid() . $this->lineBreak;
            $data['NnComments'] = empty($data['NnComments'])
                ? '<br>' . $script
                : $data['NnComments'] . '<br>' . $script;
            $this->payment->setAdditionalData(serialize($data))->save();
            return false;
        } else {
            $this->logCallbackInfo($callbackModel, $totalAmount, $this->orderNo); // Log callback data
            if ($this->order->canInvoice()) {
                $transactionId = $this->getParentTid(); // Get the original/parent transaction id
                // Create order invoice
                $this->createInvoice($this->order, $transactionId);

                if (in_array($this->response->getPaymentType(), $this->invoiceAllowed)) {
                    $emailText = "Novalnet Callback Script executed successfully for the TID: " . $tidPayment . " with amount " . $amount . " ". $this->currency . " on " . $this->currentTime . ". Please refer PAID transaction in our Novalnet Merchant Administration with the TID: " . $this->response->getTid() . "$this->lineBreak$this->lineBreak";
                    $this->emailBody = ($totalAmount > $grandTotal)
                        ? $emailText . "Your paid amount is greater than the order total amount. $this->lineBreak"
                        : $emailText;
                } else {
                    $this->emailBody = "Novalnet Callback Script executed successfully for the TID: " . $this->response->getTid() . " with amount " . $amount . " ".  $this->currency . " on " . $this->currentTime . ". $this->lineBreak$this->lineBreak";
                }
            } else {
                // Get order invoice collection
                $invoice = $this->order->getInvoiceCollection()->getFirstItem();

                if ($this->code == Novalnet_Payment_Model_Config::NN_INVOICE && $invoice->getState() == 1) {
                    $scriptText = 'Novalnet Callback Script executed successfully for the TID: ' . $tidPayment . ' with amount ' . $amount . ' ' . $this->currency . ' on ' . $this->currentTime . '. Please refer PAID transaction in our Novalnet Merchant Administration with the TID: ' . $this->response->getTid();
                    $this->emailBody = $script = ($totalAmount > $grandTotal)
                        ? $scriptText . '.Your paid amount is greater than the order total amount.' : $scriptText;
                    $data['NnComments'] = empty($data['NnComments']) ? '<br>' . $script : $data['NnComments'] . '<br>' . $script;
                    $this->payment->setAdditionalData(serialize($data))->save();
                    $this->saveOrderStatus();
                    $this->sendCallbackMail(); // Send callback notification E-mail
                    $this->showDebug($script);
                }

                $invoicePayments = array(Novalnet_Payment_Model_Config::NN_PREPAYMENT, Novalnet_Payment_Model_Config::NN_INVOICE);
                if (in_array($this->code, $invoicePayments)) {
                    $this->showDebug("Novalnet callback received. Callback Script executed already. Refer Order :" . $this->orderNo);
                } elseif ($this->code == Novalnet_Payment_Model_Config::NN_PAYPAL) {
                    $this->showDebug("Novalnet callback received. Order already paid.");
                } else {
                    $this->showDebug("Novalnet Callbackscript received. Payment type ( " . $this->response->getPaymentType() . " ) is not applicable for this process!");
                }
            }
        }

        return true;
    }

    /**
     * Save order status for invoice payment
     *
     * @param  none
     * @return none
     */
    public function saveOrderStatus()
    {
        $orderStatus = $this->getOrderStatus(); // Get order status
        $orderState = Mage_Sales_Model_Order::STATE_PROCESSING;
        $message = 'Novalnet callback set state ' . $orderState . ' for Order-ID = ' . $this->orderNo;
        $this->order->setState($orderState, true, $message);
        $this->order->addStatusToHistory($orderStatus, 'Novalnet callback added order status ' . $orderStatus);
        $this->order->save();

        $invoice = $this->order->getInvoiceCollection()->getFirstItem();
        $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);
        $invoice->save();
    }

    /**
     * Save order status invoice payment
     *
     * @param  none
     * @return none
     */
    protected function _updateOrderStatus()
    {
        $orderStatus = $this->getOrderStatus(); // Get order status
        $state = Mage_Sales_Model_Order::STATE_PROCESSING;
        $message = 'Novalnet callback set state ' . $state . ' for Order-ID = ' . $this->orderNo;
        $this->order->setState($state, true, $message);
        $this->order->addStatusToHistory($orderStatus, 'Novalnet callback added order status ' . $orderStatus);
        $this->emailBody .= 'Novalnet callback set state to ' . $state . $this->lineBreak;
        $this->emailBody .= 'Novalnet callback set status to ' . $orderStatus . ' ... ' . $this->lineBreak;
        $this->order->save();

        if (in_array($this->response->getPaymentType(), $this->invoiceAllowed)) {
            $amount = $this->helper->getFormatedAmount($this->response->getAmount(), 'RAW');
            $script = 'Novalnet Callback Script executed successfully for the TID: ' . $this->response->getTidPayment() . ' with amount ' . $amount . ' ' . $this->currency . ' on ' . $this->currentTime . '. Please refer PAID transaction in our Novalnet Merchant Administration with the TID: ' . $this->response->getTid();
        } else {
            $script = 'Novalnet Callback Script executed successfully on ' . $this->currentTime;
        }

        $data = unserialize($this->payment->getAdditionalData()); // Get payment additional information
        $data['NnComments'] = empty($data['NnComments']) ? '<br>' . $script : $data['NnComments'] . '<br>' . $script;
        $this->payment->setAdditionalData(serialize($data));
        $this->order->setPayment($this->payment)->save();
    }

    /**
     * Log callback transaction information
     *
     * @param  Novalnet_Payment_Model_Mysql4_Callback $callbackModel
     * @param  float                                  $amount
     * @param  int                                    $orderNo
     * @return none
     */
    public function logCallbackInfo($callbackModel, $amount, $orderNo)
    {
        $transactionId = $this->getParentTid(); // Get the original/parent transaction id
        $reqUrl = Mage::helper('core/http')->getRequestUri();
        $callbackModel->setOrderId($orderNo)
            ->setCallbackAmount($amount)
            ->setReferenceTid($this->response->getTid())
            ->setCallbackTid($transactionId)
            ->setCallbackDatetime($this->currentTime)
            ->setCallbackLog($reqUrl)
            ->save();
    }

    /**
     * Get payment order status
     *
     * @param  none
     * @return string
     */
    public function getOrderStatus()
    {
        $paymentObj = $this->payment->getMethodInstance(); // Payment method instance
        $status = $paymentObj->getConfigData('order_status', $this->storeId);
        $redirectPayments = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayments');
        array_push($redirectPayments, Novalnet_Payment_Model_Config::NN_PREPAYMENT, Novalnet_Payment_Model_Config::NN_INVOICE);

        // Redirect payment method order status
        if (($this->response->getTidStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
            && $this->response->getPaymentType() != 'INVOICE_START'
            && in_array($this->code, $redirectPayments))
            || $this->code == Novalnet_Payment_Model_Config::NN_CC
        ) {
            $status = $paymentObj->getConfigData('order_status_after_payment', $this->storeId);
        }

        // PayPal payment pending order status
        if ($this->code == Novalnet_Payment_Model_Config::NN_PAYPAL
            && ($this->response->getTidStatus() == Novalnet_Payment_Model_Config::PAYPAL_PENDING_CODE)
        ) {
            $status = $paymentObj->getConfigData('order_status', $this->storeId)
                ? $paymentObj->getConfigData('order_status', $this->storeId)
                : Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        }

        return !empty($status) ? $status : Mage_Sales_Model_Order::STATE_PROCESSING;;
    }

    /**
     * Log Novalnet callback response data
     *
     * @param  none
     * @return none
     */
    public function savePayportResponse()
    {
        $transactionTraces = $this->helper->getModel('Mysql4_TransactionTraces')
            ->loadByAttribute('order_id', $this->orderNo); // Get Novalnet transaction traces model
        $transactionTraces->setTransactionId($this->response->getTid())
            ->setResponseData(base64_encode(serialize($this->response->getData())))
            ->setCustomerId($this->order->getCustomerId())
            ->setStatus($this->response->getTidStatus())
            ->setStoreId($this->storeId)
            ->setShopUrl($this->response->getSystemUrl() ? $this->response->getSystemUrl() : '')
            ->save();
    }

    /**
     * Get Novalnet global configuration values
     *
     * @param  string $field
     * @return mixed
     */
    protected function _getConfig($field)
    {
        $path = 'novalnet_global/novalnet/' . $field; // Global config value path

        if ($field == 'live_mode') { // Novalnet payment mode
            $paymentMethod = Mage::getStoreConfig($path, $this->storeId);
            return preg_match('/' . $this->code . '/i', $paymentMethod) ? true : false;
        } elseif ($field !== null) {  // Get Novalnet payment/global configuration
            return Mage::getStoreConfig($path, $this->storeId);
        }

        return null;
    }

    /*
     * Assign callback process level
     *
     * @param none
     * @return int
     */
    public function getCallbackProcessLevel()
    {
        if ($this->response->getPaymentType()) {
            // Assign callback process flag
            if (in_array($this->response->getPaymentType(), $this->recurringAllowed)
                && ($this->response->getSignupTid() || $this->response->getSubsBilling())
            ) {
                $this->recurring = true;
            } else {
                $this->callback = true;
            }
            // Assign callback process level
            if (in_array($this->response->getPaymentType(), $this->paymentTypes)) {
                return 0;
            } else if (in_array($this->response->getPaymentType(), $this->chargebacks)) {
                return 1;
            } else if (in_array($this->response->getPaymentType(), $this->aryCollection)) {
                return 2;
            }
        } else {
            $this->showDebug("Required param (payment_type) missing!");
        }
    }

    /**
     * Get order increment id
     *
     * @param  none
     * @return int $orderNo
     */
    public function getOrderIncrementId()
    {
        if ($this->recurring) { // Get recurring profile increment id
            $orderNo = $this->getRecurringOrderId();
        } else {  // Get order increment id
            $orderNo = $this->response->getOrderNo() ? $this->response->getOrderNo()
                : ($this->response->getOrderId() ? $this->response->getOrderId() : '');
            $orderNo = $orderNo ? $orderNo : $this->getOrderIdByTransId();
        }

        return !empty($orderNo) ? $orderNo : $this->showDebug("Required (Transaction ID) not Found!");
    }

    /**
     * Get increment id based on payment last transaction id
     *
     * @param  none
     * @return int
     */
    public function getOrderIdByTransId()
    {
        $parentTid = $this->getParentTid(); // Get the original/parent transaction id

        if (empty($parentTid)) { // Check whether the original/parent transaction id exists
            return false;
        }

        $tablePrefix = Mage::getConfig()->getTablePrefix();
        if (in_array($this->response->getPaymentType(), $this->chargebacks)) {
            $orderPayment = $tablePrefix . 'sales_payment_transaction';
            $onCondition = "main_table.entity_id = $orderPayment.order_id";
            $orderCollection = Mage::getModel('sales/order')->getCollection()
                    ->addFieldToFilter('txn_id', array('like' => "%$parentTid%"))
                    ->addFieldToSelect('increment_id');
        } else {
            $orderPayment = $tablePrefix . 'sales_flat_order_payment';
            $onCondition = "main_table.entity_id = $orderPayment.parent_id";
            $orderCollection = Mage::getModel('sales/order')->getCollection()
                    ->addFieldToFilter('last_trans_id', array('like' => "%$parentTid%"))
                    ->addFieldToSelect('increment_id');
        }
        // Get order collection
        $orderCollection->getSelect()->join($orderPayment, $onCondition);
        $count = $orderCollection->count();
        $orderId = '';

        if ($count > 0) {
            foreach ($orderCollection as $order) {
                $orderId = $order->getIncrementId();
            }
        }

        return $orderId;
    }

    /**
     * Get subscription payment increment id
     *
     * @param  none
     * @return int $orderNo
     */
    public function getRecurringOrderId()
    {
        $orderNo = '';
        $profile = $this->getProfileInformation(); // Get the Recurring Profile Information
        $profileCollection = Mage::getResourceModel('sales/order_grid_collection')
                ->addRecurringProfilesFilter($profile->getId());
        foreach ($profileCollection as $profileValue) {
            $orderNo = $profileValue->getIncrementId();
        }
        return $orderNo;
    }

    /**
     * Get the Recurring Profile Information
     *
     * @param  none
     * @return Varien_Object $profile
     */
    public function getProfileInformation()
    {
        // Get the original/parent transaction id
        $tid = ($this->response->getSignupTid()) ? $this->response->getSignupTid() : $this->response->getTidPayment();
        $profile = Mage::getModel('sales/recurring_profile')->load($tid, 'reference_id');
        return $profile;
    }

    /**
     * Get transaction id based on payment type
     *
     * @param  none
     * @return int $tid
     */
    public function getParentTid()
    {
        // Get the original/parent transaction id
        if ($this->response->getSignupTid()) {
            $tid = trim($this->response->getSignupTid());
        } elseif (($this->response->getPaymentType() == 'INVOICE_CREDIT')
            || (in_array($this->response->getPaymentType(), $this->chargebacks))
        ) {
            $tid = $this->response->getTidPayment();
        } else {
            $tid = $this->response->getTid();
        }

        return $tid;
    }

    /**
     * Check whether the ip address is authorised
     *
     * @param  none
     * @return boolean
     */
    public function checkIP()
    {
        $callerIp = $this->helper->getRealIpAddr();

        if (!in_array($callerIp, $this->ipAllowed) && !$this->test) {
            $message = 'Novalnet callback received. Unauthorised access from the IP [' . $callerIp . ']';
            $this->showDebug($message, true, true);
        }

        return true;
    }

    /**
     * Show callback process transaction comments
     *
     * @param  string  $text
     * @param  boolean $die
     * @param  boolean $forceDisplay
     * @return none
     */
    public function showDebug($text, $die = true, $forceDisplay = false)
    {
        if (!empty($text) && ($forceDisplay || $this->debug == true)) {
            echo $text;
        }
        if ($die) {
            die;
        }
    }

    /**
     * Send callback notification E-mail
     *
     * @param  none
     * @return boolean
     */
    public function sendCallbackMail()
    {
        $this->getEmailConfig(); // Get email configuration settings

        if ($this->emailBody && $this->emailFromAddr && $this->emailToAddr) {
            if (!$this->sendMail()) {
                $this->showDebug("Mailing failed!".$this->lineBreak, false);
                $this->showDebug("This mail text should be sent: ", false);
            }
        }

        if ($this->emailBody) {
            $this->showDebug($this->emailBody);
        }
    }

    /**
     * Send callback notification E-mail
     *
     * @param  none
     * @return boolean
     */
    public function sendMail()
    {
        if ($this->mailHost && $this->mailPort) {
            ini_set('SMTP', $this->mailHost);
            ini_set('smtp_port', $this->mailPort);
        }

        if ($this->emailSendOption && $this->useEmailOption == 1) {
            if (!$this->sendEmailZend()) {
                return false;
            }
        } elseif ($this->emailSendOption && $this->useEmailOption == 2) {
            if (!$this->sendEmailMagento()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send callback notification E-mail (with callback template)
     *
     * @param  none
     * @return boolean
     */
    public function sendEmailMagento()
    {
        /*
         * Loads the html file named 'novalnet_callback_email.html' from
         * E.G: app/locale/en_US/template/email/novalnet/novalnet_callback_email.html
         * OR:  app/locale/YourLanguage/template/email/novalnet/novalnet_callback_email.html
         * Adapt the corresponding template if necessary
         */
        $emailTemplate = Mage::getModel('core/email_template')
                ->loadDefault('novalnet_callback_email_template');

        // Define some variables to assign to template
        $templateParams = array();
        $templateParams['fromName'] = $this->emailFromName;
        $templateParams['fromEmail'] = $this->emailFromAddr;
        $templateParams['toName'] = $this->emailToName;
        $templateParams['toEmail'] = $this->emailToAddr;
        $templateParams['subject'] = $this->emailSubject;
        $templateParams['body'] = $this->emailBody;
        $template = $emailTemplate->getProcessedTemplate($templateParams);

        $mail = new Zend_Mail();
        $mail->setBodyHtml($template);
        $mail->setFrom($this->emailFromAddr, $this->emailFromName);
        $this->assignEmailAddress($this->emailToAddr, $mail, 'To');
        $this->assignEmailAddress($this->emailBCcAddr, $mail, 'Bcc');
        $mail->setSubject($this->emailSubject);

        try {
            $mail->send();
            $this->showDebug(__FUNCTION__ . ': Sending Email succeeded!'.$this->lineBreak, false);
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($this->helper()->__('Unable to send email'));
            $this->showDebug('Email sending failed: ', false);
            return false;
        }
        return true;
    }

    /**
     * Send callback notification E-mail via zend
     *
     * @param  none
     * @return boolean
     */
    public function sendEmailZend()
    {
        $mail = new Zend_Mail();
        $mail->setBodyHtml($this->emailBody);
        $mail->setFrom($this->emailFromAddr, $this->emailFromName);
        $this->assignEmailAddress($this->emailToAddr, $mail, 'To');
        $this->assignEmailAddress($this->emailBCcAddr, $mail, 'Bcc');
        $mail->setSubject($this->emailSubject);

        try {
            $mail->send();
            $this->showDebug(__FUNCTION__ . ': Sending Email succeeded!'.$this->lineBreak, false);
        } catch (Exception $e) {
            $this->showDebug('Email sending failed: ', false);
            return false;
        }
        return true;
    }

    /**
     * Assign E-mail (TO and Bcc) address
     *
     * @param  string $emailaddr
     * @param  mixed  $mail
     * @param  string $addr
     * @return string
     */
    public function assignEmailAddress($emailaddr, $mail, $addr)
    {
        $email = explode(',', $emailaddr);
        $emailValidator = new Zend_Validate_EmailAddress();

        foreach ($email as $emailAddrVal) {
            if ($emailValidator->isValid(trim($emailAddrVal))) {
                ($addr == 'To') ? $mail->addTo($emailAddrVal) : $mail->addBcc($emailAddrVal);
            }
        }
        return $mail;
    }

    /**
     * Set recurring profile state
     *
     * @param  string|null $state
     * @return none
     */
    public function setRecurringProfileState($state = 'Active')
    {
        $profileId = $this->response->hasProfileId() ? $this->response->getProfileId()
            : ($this->response->hasInput4() && $this->response->getInput4() == 'profile_id'
            ? $this->response->getInputval4() : '');
        if ($profileId) {
            $profile = Mage::getModel('sales/recurring_profile')->load($profileId, 'profile_id');

            if ($state == 'Active') {
                $status = $this->response->getTidStatus();
                $message = $this->helper->getModel('Recurring_Payment')->createIpnComment($status, $this->order);
                $this->payment->setPreparedMessage($message)
                    ->setAdditionalInformation('subs_id', $this->response->getSubsId())->save();
                $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE); //Set profile status as active
            } else {
                $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_CANCELED); //Set profile status as canceled
            }

            $profile->setReferenceId($this->response->getTid())->save();
        }
    }

}
