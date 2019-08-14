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
class Novalnet_Payment_Model_Callbackscript
{

    var $useZendEmail = true; //false|true; adapt for your need

    public function Callback()
    {
        //Security Setting; only this IP is allowed for call back script
        $this->ipAllowed = array('195.143.189.210', '195.143.189.214'); //Novalnet IP, is a fixed value, DO NOT CHANGE!!!!!
        $this->debug = Mage::getStoreConfig('novalnet_global/novalnetsetting/enabledebugmode');
        $this->test = Mage::getStoreConfig('novalnet_global/novalnetsetting/enabletestmode');

        $this->callback = false;
        $this->recurring = false;
        $this->request = $this->getParam();
        $this->currentTime = Mage::getModel('core/date')->date('Y-m-d H:i:s');
        $this->emailSendOption = Mage::getStoreConfig('novalnet_global/novalnetsetting/emailsendoption');

        $this->allowedPayment = array(
            'novalnetcc' => array('CREDITCARD', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK',
                'CREDIT_ENTRY_CREDITCARD', 'SUBSCRIPTION_STOP', 'DEBT_COLLECTION_CREDITCARD'),
            'novalnetinvoice' => array('INVOICE_START', 'INVOICE_CREDIT', 'SUBSCRIPTION_STOP'),
            'novalnetprepayment' => array('INVOICE_START', 'INVOICE_CREDIT', 'SUBSCRIPTION_STOP'),
            'novalnetideal' => array('IDEAL'),
            'novalnetpaypal' => array('PAYPAL'),
            'novalneteps' => array('EPS'),
            'novalnetbanktransfer' => array('ONLINE_TRANSFER', 'REFUND_BY_BANK_TRANSFER_EU'),
            'novalnetsepa' => array('DIRECT_DEBIT_SEPA', 'RETURN_DEBIT_SEPA', 'SUBSCRIPTION_STOP',
                'DEBT_COLLECTION_SEPA', 'CREDIT_ENTRY_SEPA'));
        $this->invoiceAllowed = array('INVOICE_CREDIT', 'INVOICE_START');
        $this->recurringAllowed = array('INVOICE_CREDIT', 'INVOICE_START', 'CREDITCARD',
            'DIRECT_DEBIT_SEPA', 'SUBSCRIPTION_STOP');

        /** @Array Type of payment available - Level : 0 */
        $this->paymentTypes = array('INVOICE_START', 'PAYPAL', 'ONLINE_TRANSFER',
            'CREDITCARD', 'IDEAL', 'DIRECT_DEBIT_SEPA', 'PAYSAFECARD', 'EPS', 'GUARANTEED_INVOICE_START');
        /** @Array Type of Chargebacks available - Level : 1 */
        $this->chargebacks = array('CREDITCARD_CHARGEBACK', 'RETURN_DEBIT_SEPA',
            'CREDITCARD_BOOKBACK', 'REFUND_BY_BANK_TRANSFER_EU');
        /** @Array Type of CreditEntry payment and Collections available - Level : 2 */
        $this->aryCollection = array('INVOICE_CREDIT', 'GUARANTEED_INVOICE_CREDIT',
            'CREDIT_ENTRY_CREDITCARD', 'CREDIT_ENTRY_SEPA', 'DEBT_COLLECTION_SEPA',
            'DEBT_COLLECTION_CREDITCARD');
        $this->arySubscription = array('SUBSCRIPTION_STOP');

        $httpHost = Mage::helper('core/http')->getHttpHost();
        $this->lineBreak = empty($httpHost) ? PHP_EOL : '<br />';

        //Reporting Email Addresses Settings
        $this->shopInfo = 'Magento ' . $this->lineBreak; //mandatory;adapt for your need
        $this->mailHost = Mage::getStoreConfig('system/smtp/host'); //adapt or Mage::getStoreConfig('system/smtp/host')
        $this->mailPort = Mage::getStoreConfig('system/smtp/port'); //adapt or Mage::getStoreConfig('system/smtp/port')

        if (isset($this->request['vendor_activation']) && $this->request['vendor_activation'] == 1) {
            $this->doNovalnetAffAccInfoLog();
            return false;
        }

        $this->level = $this->getPaymentTypeLevel();

        if (in_array($this->request['payment_type'], $this->recurringAllowed)
                && (!empty($this->request['signup_tid']) || !empty($this->request['subs_billing']))) {
            $this->recurring = true;
        } else {
            $this->callback = true;
        }

        //Parameter Settings
        $this->paramsRequired = array(
            'vendor_id' => '',
            'tid' => '',
            'payment_type' => '',
            'status' => '',
            'amount' => '',
            'tid_payment' => '',
            'signup_tid' => ''
        );

        if ($this->callback) {
            unset($this->paramsRequired['signup_tid']);
            $invoiceChargeback = array_merge($this->invoiceAllowed, $this->chargebacks);
            if ((!in_array($this->request['payment_type'], $invoiceChargeback))) {
                unset($this->paramsRequired['tid_payment']);
            }
        } else {
            unset($this->paramsRequired['tid_payment']);
            unset($this->paramsRequired['order_no']);
        }

        ksort($this->paramsRequired);

        // ################### Main Prog. ##########################
        try {

            //Check Params
            if ($this->checkIP()) {

                $response = $this->request;
                $helper = $this->_getNovalnetHelper();
                if ($this->recurring) {
                    $recurringProfileId = $this->getProfileInformation($this->request);
                    $orderNo = $this->getRecurringOrderNo($recurringProfileId);
                    $this->orderNo = $orderNo;
                } else {
                    $this->orderNo = isset($response['order_no']) && $response['order_no'] ? $response['order_no'] : '';
                    if (!$this->orderNo) {
                        $this->orderNo = $this->getOrderIdByTransId();
                    }
                }

                if (empty($response['payment_type'])) {
                    $this->showDebug("Required param (payment_type) missing!");
                } elseif (empty($this->orderNo)) {
                    $this->showDebug("Required (Transaction ID) not Found!");
                } elseif (!empty($response['payment_type'])
                        && in_array(strtoupper($response['payment_type']), array_merge($this->paymentTypes, $this->chargebacks, $this->aryCollection, $this->arySubscription))) {
                    //Complete the order incase response failure from novalnet server
                    $order = $this->getOrderByIncrementId($this->orderNo);
                    $storeId = $order->getStoreId();
                    $this->getEmailConfig($storeId);
                    $this->setLanguageStore($storeId);
                    if ($order->getIncrementId()) {
                        $payment = $order->getPayment();
                        $paymentObj = $payment->getMethodInstance();
                        $this->paymentCode = $paymentObj->getCode();
                        $additionalData = $getResponseData = unserialize($payment->getAdditionalData());
                        $paymentObj->_vendorId = ($getResponseData['vendor']) ? $getResponseData['vendor'] : $paymentObj->getNovalnetConfig('merchant_id', true, $storeId);
                        $paymentObj->_authCode = ($getResponseData['auth_code']) ? $getResponseData['auth_code'] : $paymentObj->getNovalnetConfig('auth_code', true, $storeId);
                        $paymentObj->_productId = ($getResponseData['product']) ? $getResponseData['product'] : $paymentObj->getNovalnetConfig('product_id', true, $storeId);
                        // Get Admin Transaction status via API
                        if ($this->recurring) {
                            $paymentTid = $response['signup_tid'];
                        } else {
                            $paymentTid = (in_array($response['payment_type'], $this->chargebacks)) ? $response['tid_payment'] : (in_array($response['payment_type'], $this->invoiceAllowed)) ? $response['tid_payment'] : $response['tid'];
                        }
                        $this->getAdminTransaction = $paymentObj->doNovalnetStatusCall($paymentTid, $payment);
                        $checkTidExist = $payment->getLastTransId();
                        $this->paymentTypeValidation($order);
                        if (!empty($this->orderNo) && $order->getIncrementId() == $this->orderNo && empty($checkTidExist)) {

                            //Unhold an order:-
                            if ($order->canUnhold()) {
                                $order->unhold()->save();
                            }

                            $redirectPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayments');
                            $serverResponseMode = $response['test_mode'];
                            $shopMode = $paymentObj->getNovalnetConfig('live_mode', '', $storeId);
                            $testMode = ((isset($serverResponseMode) && $serverResponseMode == 1) || (isset($shopMode) && $shopMode == 0)) ? 1 : 0;
                            $data = array('NnTestOrder' => $testMode);
                            $data = $additionalData ? array_merge($additionalData, $data) : $data;
                            $txnId = $response['tid'];
                            $payment->setStatus(Novalnet_Payment_Model_Payment_Method_Abstract::STATUS_SUCCESS)
                                    ->setStatusDescription($helper->__('Payment was successful.'))
                                    ->setAdditionalData(serialize($data))
                                    ->setIsTransactionClosed(true)
                                    ->save();
                            $dataObj = new Varien_Object($response);
                            $this->doTransactionStatusSave($dataObj); // Save the Transaction status
                            // Payment process based on response status
                            if ($payment->getAdditionalInformation($paymentObj->getCode() . '_successAction') != 1) {
                                if ($order->canInvoice() && $this->getAdminTransaction->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                                    $payment->setTransactionId($txnId) // Add capture text to make the new transaction
                                            ->setParentTransactionId(null)
                                            ->setIsTransactionClosed(true)
                                            ->setLastTransId($txnId)
                                            ->capture(null)
                                            ->save();
                                } else {
                                    $payment->setTransactionId($txnId)
                                            ->setLastTransId($txnId)
                                            ->setParentTransactionId(null)
                                            ->save();
                                }
                                $onHoldStatus = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('paymentOnholdStaus');
                                array_push($onHoldStatus, '100', '90');
                                if (in_array($this->getAdminTransaction->getStatus(), $onHoldStatus)) {
                                    $orderStatus = $this->getOrderStatus($order);
                                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $orderStatus, $helper->__('Customer successfully returned from Novalnet'), true
                                    )->save();
                                } else {
                                    $paymentObj->saveCancelledOrder($dataObj, $payment);
                                }
                            }

                            $this->callBackExecuted = true;
                            $this->doTransactionOrderLog($response);
                            //sendNewOrderEmail
                            if (!$order->getEmailSent() && $order->getId()
                                && in_array($this->getAdminTransaction->getStatus(), $onHoldStatus)) {
                                try {
                                    $order->sendNewOrderEmail()
                                            ->setEmailSent(true)
                                            ->save();
                                } catch (Exception $e) {
                                    $helper->showException('Cannot send new order email.');
                                }
                            }
                            $order->save();
                            $this->emailBody = "Novalnet Callback received for the TID: " . $txnId;
                        }

                        //Get Order ID and Set New Order Status
                        if ($this->checkParams($response)) {
                            $orderCheckStatus = $this->BasicValidation();
                            if ($orderCheckStatus) {
                                if ($response['payment_type'] != 'INVOICE_CREDIT' || ($response['payment_type'] == 'INVOICE_CREDIT' && $this->getAdminTransaction->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED)) {
                                    $this->setOrderStatus(); //and send error mails if any
                                } else {
                                    $this->showDebug(isset($this->getAdminTransaction['transaction_status']['status_message']) ? ($this->getAdminTransaction['transaction_status']['status_message']) : 'Error in processing the transactions status');
                                }
                            }
                        }
                    } else {
                        $this->showDebug("Order no [" . $this->orderNo . "] is not valid!");
                    }
                } else {
                    $this->showDebug("Payment type [" . $response['payment_type'] . "] is mismatched!");
                }
            }

        } catch (Exception $e) {
            $this->emailBody .= "Exception catched: $this->lineBreak\$e:" . $e->getMessage() . $this->lineBreak;
        }

        $this->sendCallbackMail();
    }

    /**
     * Send callback notification E-mail
     *
     * @return boolean
     */
    private function sendCallbackMail()
    {
        if (isset($this->emailBody) && $this->emailBody && $this->emailFromAddr && $this->emailToAddr) {
            if (!$this->sendMail($this->emailBody)) {
                $this->showDebug("Mailing failed!".$this->lineBreak, false);
                $this->showDebug("This mail text should be sent: ", false);
            }
        }

        if (isset($this->emailBody)) {
            $this->showDebug($this->emailBody);
        }
    }

    /**
     * Send callback notification E-mail
     *
     * @param string $emailBody
     * @return boolean
     */
    public function sendMail($emailBody)
    {
        //Send Email
        if ($this->mailHost && $this->mailPort) {
            ini_set('SMTP', $this->mailHost);
            ini_set('smtp_port', $this->mailPort);
        }

        if ($this->useZendEmail) {
            if ($this->emailSendOption && !$this->sendEmailZend($emailBody)) {
                return false;
            }
        } else {
            if ($this->emailSendOption && !$this->sendEmailMagento($emailBody)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send callback notification E-mail via Magento
     *
     * @param string $emailBody
     * @return boolean
     */
    public function sendEmailMagento($emailBody)
    {
        /*
         * Loads the html file named 'novalnet_callback_email.html' from
         * E.G: app/locale/en_US/template/email/novalnet/novalnet_callback_email.html
         * OR:  app/locale/YourLanguage/template/email/novalnet/novalnet_callback_email.html
         * Adapt the corresponding template if necessary
         */
        $emailTemplate = Mage::getModel('core/email_template')
                ->loadDefault('novalnet_callback_email_template');

        //Define some variables to assign to template
        $emailTemplateVariables = array();
        $emailTemplateVariables['fromName'] = $this->emailFromName;
        $emailTemplateVariables['fromEmail'] = $this->emailFromAddr;
        $emailTemplateVariables['toName'] = $this->emailToName;
        $emailTemplateVariables['toEmail'] = $this->emailToAddr;
        $emailTemplateVariables['subject'] = $this->emailSubject;
        $emailTemplateVariables['body'] = $emailBody;
        $processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplVariables);

        $mail = new Zend_Mail();
        $mail->setBodyHtml($processedTemplate);
        $mail->setFrom($this->emailFromAddr, $this->emailFromName);
        $mail->addTo($this->emailToAddr, $this->emailToName);
        $mail->setSubject($this->emailSubject);

        try {
            $this->showDebug(__FUNCTION__ . ': Sending Email succeeded!'.$this->lineBreak, false);
            $emailTemplate->send($this->emailToAddr, $this->emailToName, $emailTemplateVariables);
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError($this->_getNovalnetHelper()->__('Unable to send email'));
            $this->showDebug('Email sending failed: ', false);
            return false;
        }
        return true;
    }

    /**
     * Send callback notification E-mail via zend
     *
     * @param string $emailBody
     * @return boolean
     */
    public function sendEmailZend($emailBody)
    {
        $this->validatorEmail = new Zend_Validate_EmailAddress();

        $mail = new Zend_Mail();
        $mail->setBodyHtml($emailBody);
        $mail->setFrom($this->emailFromAddr, $this->emailFromName);
        $this->emailSeparate($this->emailToAddr, $mail, 'To');
        $this->emailSeparate($this->emailBCcAddr, $mail, 'Bcc');
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
     * E-mail TO and Bcc address comma separate
     *
     * @param string $emailaddr
     * @param string $mail
     * @param string $addr
     * @return string
     */
    public function emailSeparate($emailaddr, $mail, $addr)
    {
        $email = explode(',', $emailaddr);
        $validatorEmail = $this->validatorEmail;

        foreach ($email as $emailAddrVal) {
            if ($validatorEmail->isValid(trim($emailAddrVal))) {
                ($addr == 'To') ? $mail->addTo($emailAddrVal) : $mail->addBcc($emailAddrVal);
            }
        }
        return $mail;
    }

    /**
     * Check the callback mandatory parameters.
     *
     * @param array $request
     * @return boolean
     */
    public function checkParams($request)
    {
        if (!$request) {
            $this->showDebug('Novalnet callback received. No params passed over!');
        }

        if (in_array($request['payment_type'], $this->arySubscription)) {
            unset($this->paramsRequired['amount']);
        }

        foreach ($this->paramsRequired as $k => $v) {
            if (isset($k) && $k == 'amount'
                && (!is_numeric($request[$k]) || $request[$k] < 0)) {
                $this->showDebug('Required param (' . $k . ') missing!');
            } elseif (isset($k) && $k != 'amount'
                && (!isset($request[$k]) || empty($request[$k]))) {
                $this->showDebug('Required param (' . $k . ') missing!');
            }
        }

        if ($this->recurring && !preg_match('/^\d{17}$/', $request['signup_tid'])) {
            $this->showDebug('Novalnet callback received. Invalid TID [' . $request['signup_tid'] . '] for Order.');
        } else if ((in_array($request['payment_type'], $this->invoiceAllowed)) || (in_array($request['payment_type'], $this->chargebacks))) {
            if (!$this->recurring && !preg_match('/^\d{17}$/', $request['tid_payment'])) {
                $this->showDebug('Novalnet callback received. Invalid TID [' . $request['tid_payment'] . '] for Order:' . $this->orderNo);
            }
        }

        if (!preg_match('/^\d{17}$/', $request['tid'])) {
            if ((in_array($request['payment_type'], $this->invoiceAllowed)) || (in_array($request['payment_type'], $this->chargebacks))) {
                $this->showDebug('Novalnet callback received. New TID is not valid.');
            } else {
                $this->showDebug('Novalnet callback received. Invalid TID [' . $request['tid'] . '] for Order:' . $this->orderNo);
            }
        }

        if ($request['payment_type'] == 'INVOICE_CREDIT' && !empty($request['status']) && 100 != $request['status']) {
            $this->showDebug('Novalnet callback received. Callback Script executed already. Refer Order :' . $this->orderNo);
        }

        return true;
    }

    /**
     * validate the callback parameters.
     *
     * @return boolean
     */
    public function BasicValidation()
    {
        $request = $this->request;
        $orderNo = $this->orderNo;
        $order = $this->getOrderByIncrementId($orderNo);

        if ($this->recurring && $request['subs_billing'] && $request['signup_tid']) {
            $recurringProfileId = $this->getProfileInformation($request);
            $activeState = $recurringProfileId->getState();
            $orderNo = $this->getRecurringOrderNo($recurringProfileId);
        }

        if ($order->getIncrementId() == $orderNo && !empty($orderNo)) {
            $orderPaymentName = strtolower($this->paymentCode);

            if ($request['subs_billing'] == 1) {
                $orgTid = $request['signup_tid'];
            } else if ((in_array($request['payment_type'], $this->chargebacks)) || $request['payment_type'] == 'INVOICE_CREDIT') {
                $orgTid = $request['tid_payment'];
            } else {
                $orgTid = $request['tid'];
            }

            $paymentType = $this->allowedPayment[$orderPaymentName];
            if (!in_array($request['payment_type'], $paymentType)) {
                $this->showDebug("Novalnet callback received. Payment type (" . $request['payment_type'] . ") is not matched with $orderPaymentName!");
            }

            $payment = $order->getPayment();
            $additionalData = unserialize($payment->getAdditionalData());
            $orderTid = ((in_array($request['payment_type'], $this->chargebacks)) && $additionalData['NnTid']) ? $additionalData['NnTid'] : $order->getPayment()->getLastTransId();

            if (!preg_match('/^' . $orgTid . '/i', $orderTid) && !$this->recurring) {
                $this->showDebug('Novalnet callback received. Order no is not valid');
            }

            if ($this->recurring && $request['subs_billing'] && $activeState == 'canceled') {
                $this->showDebug('Subscription already Cancelled. Refer Order : ' . $orderNo);
            }

            return true;
        } else {
            $this->showDebug('Novalnet callback received. Order no is not valid');
        }
    }

    /**
     * Set the staus and state to an order payment
     *
     * @return boolean
     */
    public function setOrderStatus()
    {
        $order = $this->getOrderByIncrementId($this->orderNo);
        $orderItems = $order->getAllItems();
        $helper = $this->_getNovalnetHelper();
        $nominalItem = $helper->checkNominalItem($orderItems);

        if ($order) {
            $status = $this->getOrderStatus($order);
            $state = Mage_Sales_Model_Order::STATE_PROCESSING;
            $request = $this->request;
            $order->getPayment()->getMethodInstance()->setCanCapture(true);
            $payment = $order->getPayment();
            $data = unserialize($payment->getAdditionalData());
            $currency = $this->getAdminTransaction->getCurrency();
            if ($this->level == 1) { //level 1 payments - Type of Chargebacks
                // Update callback comments for Chargebacks
                if (in_array($this->request['payment_type'], $this->chargebacks)) {
                    $bookBack = array('CREDITCARD_BOOKBACK', 'REFUND_BY_BANK_TRANSFER_EU');
                    $tId = !$this->recurring ? $request['tid_payment'] : $request['signup_tid'];
                    if (in_array($request['payment_type'], $bookBack)) {
                        $script = 'Novalnet callback received. Refund/Bookback executed successfully for the TID: ' . $tId . ' amount: ' . ($request['amount'])
                            / 100 . ' ' . $currency . " on " . date('Y-m-d H:i:s') . '. The subsequent TID: ' . $request['tid'];
                    } else {
                        $script = 'Novalnet Callback script received. Chargeback executed sucessfully for the TID ' . $tId . ' amount ' . ($request['amount']) / 100 . ' ' . $currency . " on " . $this->currentTime . '. The subsequent TID: ' . $request['tid'];
                    }
                    $this->emailBody = $script;
                    $this->saveAdditionalInfo($payment, $data, $script, $order);
                    return true;
                }
            }

            if ($request['payment_type'] == 'SUBSCRIPTION_STOP') { ### Cancellation of a Subscription
                ### UPDATE THE STATUS OF THE USER SUBSCRIPTION ###
                $script = 'Novalnet Callback script received. Subscription has been stopped for the TID:' . $request['signup_tid'] . " on " . $this->currentTime;
                $script .= '<br>Reason for Cancellation: ' . $request['termination_reason'];
                $this->emailBody = $script;
                $recurringProfileId = $this->getProfileInformation($request);
                $orderNumber = $helper->getModelRecurring()->getRecurringOrderNo($recurringProfileId);
                $parentOrder = $this->getOrderByIncrementId($orderNumber[0]);
                $parentPayment = $parentOrder->getPayment();
                $parentData = unserialize($parentPayment->getAdditionalData());
                $this->saveAdditionalInfo($parentPayment, $parentData, $script, $parentOrder);
                $recurringProfileId->setState('canceled');
                $recurringProfileId->save();
                return true;
            }

            $invoice = $order->getInvoiceCollection()->getFirstItem();
            $paid = $invoice->getState();
            if ($this->level == 0 || $this->level == 2) {
                $subsPaymentType = array('CREDITCARD', 'DIRECT_DEBIT_SEPA', 'INVOICE_START');
                if ($this->recurring && in_array($this->request['payment_type'], $subsPaymentType) && !empty($this->request['signup_tid']) && $this->request['subs_billing'] == 1) {
                    $recurringProfileId = $this->getProfileInformation($request);
                    $periodMaxCycles = $recurringProfileId->getPeriodMaxCycles();
                    $profileId = $recurringProfileId->getId();
                    $helper = $this->_getNovalnetHelper();
                    if (!$this->getAdminTransaction->getNextSubsCycle()) {
                        $this->showDebug("Novalnet callback received. Subscription Suspended. Refer Order :" . $this->orderNo);
                    }
                    $script = 'Novalnet Callback script received. Recurring was executed sucessfully for the TID ' . $request['signup_tid'] . ' with amount ' . ($request['amount']) / 100 . ' ' . $currency . ' ' . " on " . $this->currentTime . '.';

                    $callbackCycle = $this->getRecurringCallbackSave($request, $periodMaxCycles, $helper, $profileId);
                    $this->getEndTime($request, $recurringProfileId, $callbackCycle);
                    $paidAmount = ($request['amount'] / 100);
                    $loadTransaction = $helper->loadTransactionStatus($request['signup_tid'])
                            ->setAmount($paidAmount)
                            ->save();
                    $this->createOrder($order, $script, $data, $payment, $profileId);
                    return true;
                }

                $saveInvoice = '';
                if (($request['payment_type'] != 'PAYPAL')
                    || (($request['payment_type'] == 'PAYPAL') && ($this->getAdminTransaction->getStatus() == 100))) {
                    $saveInvoice = $this->saveInvoice($order, $nominalItem, $paid);
                }

                if ($invoice) {
                    if ($saveInvoice) {
                        $order->setState($state, true, 'Novalnet callback set state ' . $state . ' for Order-ID = ' . $this->orderNo);
                        $order->addStatusToHistory($status, 'Novalnet callback added order status ' . $status);
                        $this->emailBody .= 'Novalnet callback set state to ' . $state . $this->lineBreak;
                        $this->emailBody .= 'Novalnet callback set status to ' . $status . ' ... ' . $this->lineBreak;
                        $order->save();

                        $transMode = (version_compare($helper->getMagentoVersion(), '1.6', '<')) ? false : true;
                        if (in_array($request['payment_type'], $this->invoiceAllowed)) {
                            if ($nominalItem) {
                                $tidPayment = isset($request['signup_tid']) && $request['signup_tid'] && $request['subs_billing'] ? trim($request['signup_tid']) : trim($request['tid_payment']);
                            } else {
                                $tidPayment = trim($request['tid']);
                            }
                            $payment->setTransactionId($tidPayment)
                                    ->setIsTransactionClosed($transMode);
                            $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, null, false);
                            $transaction->setParentTxnId(null)
                                    ->save();
                            $amount = $this->_getNovalnetHelper()->getFormatedAmount($request['amount'], 'RAW');
                            $currency = $this->getAdminTransaction->getCurrency();
                            $script = 'Novalnet Callback Script executed successfully for the TID: ' . $request['tid_payment'] . ' with amount ' . $amount . ' ' . $currency . ' on ' . $this->currentTime . '. Please refer PAID transaction in our Novalnet Merchant Administration with the TID: ' . $request['tid'];
                        } else {
                            $script = 'Novalnet Callback Script executed successfully on ' . $this->currentTime;
                        }
                        $this->saveAdditionalInfo($payment, $data, $script, $order);


                        $changeAmount = $helper->getAmountCollection($order->getId(), 1, NULL);
                        if ($changeAmount) {
                            $loadTransStatus = $helper->loadTransactionStatus($request['tid_payment']);
                            $loadTransStatus->setAmount($changeAmount)
                                    ->save();
                        }
                    }
                } else {
                    $this->showDebug("Novalnet Callback: No invoice for order (" . $order->getId() . ") found");
                }
            }
        } else {
            $this->showDebug("Novalnet Callback: No order for Increment-ID $this->orderNo found.");
        }
        return true;
    }

    /**
     * Create invoice to order payment
     *
     * @param varien_object $order
     * @param varien_object $nominalItem
     * @param int $paid
     * @return boolean
     */
    public function saveInvoice(Mage_Sales_Model_Order $order, $nominalItem, $paid)
    {
        if (!$this->callBackExecuted) {
            $request = $this->request;
            $orderNo = $this->orderNo;
            $helper = $this->_getNovalnetHelper();
            $payment = $order->getPayment();
            $paymentObj = $payment->getMethodInstance();
            $data = unserialize($payment->getAdditionalData());

            $novalnetCallbackModel = Mage::getModel('novalnet_payment/callback')->loadLogByOrderId($orderNo);
            $sum = sprintf(($request['amount'] + $novalnetCallbackModel->getCallbackAmount()), 0.2);
            $amountvalue = $this->getRecurringTotal($request, $order, $nominalItem);
            $grandTotal = sprintf(($amountvalue * 100), 0.2);
            if (!$this->recurring) {
                $tidPayment = $request['tid_payment'];
            } else {
                $tidPayment = $request['signup_tid'];
            }

            if (in_array($request['payment_type'], $this->invoiceAllowed) && $sum < $grandTotal) {
                $this->doNovalnetCallbackLog($novalnetCallbackModel, $request, $sum);
                $amount = $helper->getFormatedAmount($request['amount'], 'RAW');
                $this->emailBody = "Novalnet Callback Script executed successfully for the TID: " . $tidPayment . " with amount " . $amount . " " . $this->getAdminTransaction->getCurrency() . " on " . $this->currentTime . ". Please refer PAID transaction in our Novalnet Merchant Administration with the TID: " . $request['tid'] . "$this->lineBreak$this->lineBreak";
                $script = "Novalnet Callback Script executed successfully for the TID: " . $tidPayment . " with amount " . $amount . " " . $this->getAdminTransaction->getCurrency() . " on " . $this->currentTime . ". Please refer PAID transaction in our Novalnet Merchant Administration with the TID: " . $request['tid'] . $this->lineBreak;
                $this->saveAdditionalInfo($payment, $data, $script, $order);
                return false;
            } else {
                $this->doNovalnetCallbackLog($novalnetCallbackModel, $request, $sum);
                $amount = $helper->getFormatedAmount($request['amount'], 'RAW');
                if ($order->canInvoice()) {
                    $tid = $this->requestTid($request);
                    $invoice = $order->prepareInvoice();
                    $invoice->setTransactionId($tid);
                    $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE)
                            ->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)
                            ->register();
                    Mage::getModel('core/resource_transaction')
                            ->addObject($invoice)
                            ->addObject($invoice->getOrder())
                            ->save();
                    if ($this->recurring || $nominalItem) {
                        $profileInfo = $this->getProfileInformation($request);
                        if ($profileInfo->getState() != 'canceled') {
                            $profileInfo->setState('active');
                            $profileInfo->save();
                        }
                    }

                    $amount = $helper->getFormatedAmount($request['amount'], 'RAW');
                    if (in_array($request['payment_type'], $this->invoiceAllowed)) {
                        $emailText = "Novalnet Callback Script executed successfully for the TID: " . $tidPayment . " with amount " . $amount . " ". $this->getAdminTransaction->getCurrency() . " on " . $this->currentTime . ". Please refer PAID transaction in our Novalnet Merchant Administration with the TID: " . $request['tid'] . "$this->lineBreak$this->lineBreak";

                        $this->emailBody = ($sum > $grandTotal) ? $emailText . "Your paid amount is greater than the order total amount. $this->lineBreak" : $emailText;
                    } else {
                        $this->emailBody = "Novalnet Callback Script executed successfully for the TID: " . $request['tid'] . " with amount " . $amount . " ".  $this->getAdminTransaction->getCurrency() . " on " . $this->currentTime . ". $this->lineBreak$this->lineBreak";
                    }
                } else {
                    if ($paymentObj->getCode() == Novalnet_Payment_Model_Config::NN_INVOICE && $paid == 1) {
                        $currency = $this->getAdminTransaction->getCurrency();
                        $scriptText = 'Novalnet Callback Script executed successfully for the TID: ' . $request['tid_payment'] . ' with amount ' . $amount . ' ' . $currency . ' on ' . $this->currentTime . '. Please refer PAID transaction in our Novalnet Merchant Administration with the TID: ' . $request['tid'];
                        $script = ($sum > $grandTotal) ? $scriptText . '.Your paid amount is greater than the order total amount.' : $scriptText;
                        $this->emailBody = $script;
                        $this->saveAdditionalInfo($payment, $data, $scriptText, $order);
                        $this->saveOrderStatus($order, $nominalItem);
                        $this->sendCallbackMail();
                        die;
                    }

                    $invoicePayments = array(Novalnet_Payment_Model_Config::NN_PREPAYMENT, Novalnet_Payment_Model_Config::NN_INVOICE);
                    if (in_array($paymentObj->getCode(), $invoicePayments)) {
                        $this->showDebug("Novalnet callback received. Callback Script executed already. Refer Order :" . $this->orderNo);
                    } elseif ($paymentObj->getCode() == Novalnet_Payment_Model_Config::NN_PAYPAL) {
                        $this->showDebug("Novalnet callback received. Order already paid.");
                    } else {
                        $this->showDebug("Novalnet Callbackscript received. Payment type ( " . $request['payment_type'] . " ) is not applicable for this process!");
                    }
                }
            }
        }
        return true;
    }

    /**
     * Save order status invoice payment
     *
     * @param varien_object $order
     * @param varien_object $nominalItem
     */
    private function saveOrderStatus($order, $nominalItem)
    {
        $payment = $order->getPayment();
        $originalTid = trim($payment->getLastTransId());
        if (!$this->recurring && !$nominalItem) {
            $transaction = Mage::getModel('sales/order_payment')->getCollection()
                    ->addFieldToFilter('last_trans_id', $originalTid)
                    ->addFieldToSelect('entity_id');
            foreach ($transaction as $transactionId) {
                $entitiyId = $transactionId->getEntityId();
            }
            Mage::getModel('sales/order_payment')->load($entitiyId)
                    ->setLastTransId(trim($this->request['tid']))
                    ->save();
        }

        $orderStatus = $this->getOrderStatus($order);
        $orderState = Mage_Sales_Model_Order::STATE_PROCESSING;
        $order->setState($orderState, true, 'Novalnet callback set state ' . $orderState . ' for Order-ID = ' . $this->orderNo);
        $order->addStatusToHistory($orderStatus, 'Novalnet callback added order status ' . $orderStatus);
        $order->save();

        $invoice = $order->getInvoiceCollection()->getFirstItem();
        $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);
        $invoice->save();

        if ($this->recurring || $nominalItem) {
            $profileInfo = $this->getProfileInformation($this->request);
            if ($profileInfo->getState() != 'canceled') {
                $profileInfo->setState('active');
                $profileInfo->save();
            }
        }
    }

    /**
     * Get the payment method code from order
     *
     * @param varien_object $order
     * @return string
     */
    private function getPaymentMethod($order)
    {
        return $order->getPayment()->getData('method');
    }

    /**
     * Check whether the ip address is authorised
     *
     * @return boolean
     */
    public function checkIP()
    {
        $callerIp = $this->_getNovalnetHelper()->getRealIpAddr();

        if (!in_array($callerIp, $this->ipAllowed) && !$this->test) {
            $this->showDebug('Novalnet callback received. Unauthorised access from the IP [' . $callerIp . ']');
        }

        return true;
    }

    /**
     * Log Novalnet transaction status data
     *
     * @param array $response
     */
    public function doTransactionStatusSave($response)
    {
        $transactionStatus = $this->getAdminTransaction;
        $order = $this->getOrderByIncrementId($this->orderNo);
        $amount = $response['amount'];
        $status = trim($transactionStatus->getStatus());
        $ncNo = (isset($response['nc_no'])) ? $response['nc_no'] : NULL;
        $helper = $this->_getNovalnetHelper();
        $payment = $order->getPayment();
        $paymentObj = $payment->getMethodInstance();
        $storeId = $order->getStoreId();
        $transactionStatus = Mage::getModel('novalnet_payment/transactionstatus');
        $transactionStatus->setTransactionNo($response['tid'])
                ->setOrderId($this->orderNo)
                ->setTransactionStatus($status)
                ->setNcNo($ncNo)
                ->setCustomerId($response['customer_no'])
                ->setPaymentName($this->paymentCode)
                ->setAmount($helper->getFormatedAmount($amount, 'RAW'))
                ->setRemoteIp($helper->getRealIpAddr())
                ->setStoreId($storeId)
                ->setShopUrl($helper->getCurrentSiteUrl())
                ->setCreatedDate($helper->getCurrentDateTime())
                ->save();
    }

    /**
     * Log Novalnet transaction data
     *
     * @param array $response
     */
    public function doTransactionOrderLog($response)
    {
        $order = $this->getOrderByIncrementId($this->orderNo);
        $helper = $this->_getNovalnetHelper();
        $storeId = $order->getStoreId();
        $transactionOverview = $helper->getModelTransactionOverview()->loadByAttribute('order_id', $this->orderNo);
        $transactionOverview->setTransactionId($response['tid'])
                ->setResponseData(base64_encode(serialize($response)))
                ->setCustomerId($response['customer_no'])
                ->setStatus($response['status'])
                ->setStoreId($storeId)
                ->setShopUrl($helper->getCurrentSiteUrl())
                ->save();
    }

    /**
     * Log partial callback data
     *
     * @param Novalnet_Payment_Model_Callback $novalnetCallbackModel
     * @param array $response
     * @param int $sum
     */
    public function doNovalnetCallbackLog($novalnetCallbackModel, $response, $sum)
    {
        $orgTid = $this->requestTid($response);
        $orderNo = $this->orderNo;
        $reqUrl = Mage::helper('core/http')->getRequestUri();
        $novalnetCallbackModel->setOrderId($orderNo)
                ->setCallbackAmount($sum)
                ->setReferenceTid($orgTid)
                ->setCallbackDatetime($this->currentTime)
                ->setCallbackLog($reqUrl)
                ->save();
    }

    /**
     * Log Affiliate account details
     *
     */
    private function doNovalnetAffAccInfoLog()
    {
        $request = $this->request;
        $helper = $this->_getNovalnetHelper();
        $affiliateAccInfo = $helper->getModelAffiliate();
        $affiliateAccInfo->setVendorId($request['vendor_id'])
                ->setVendorAuthcode($request['vendor_authcode'])
                ->setProductId($request['product_id'])
                ->setProductUrl($request['product_url'])
                ->setActivationDate($request['activation_date'])
                ->setAffId($request['aff_id'])
                ->setAffAuthcode($request['aff_authcode'])
                ->setAffAccesskey($request['aff_accesskey'])
                ->save();
        //Send notification mail to Merchant
        $this->getEmailConfig();
        $this->emailBody = 'Novalnet callback script executed successfully with Novalnet account activation information.';
        $this->sendCallbackMail();
    }

    /**
     * Get Novalnet Helper
     *
     * @return Novalnet_Payment_Helper_Data
     */
    private function _getNovalnetHelper()
    {
        return Mage::helper('novalnet_payment');
    }

    /**
     * Get order object for specific order id
     *
     * @param int $incrementId
     * @return varien_object
     */
    public function getOrderByIncrementId($incrementId)
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        return $order;
    }

    /**
     * Get Request param
     *
     * @return array
     */
    public function getParam()
    {
        return Mage::app()->getRequest()->getPost() ? Mage::app()->getRequest()->getPost() : Mage::app()->getRequest()->getQuery();
    }

    /**
     * Get the order payment status
     *
     * @param varien_object $order
     * @return string
     */
    private function getOrderStatus($order)
    {
        $payment = $order->getPayment();
        $paymentObj = $payment->getMethodInstance();
        $storeId = $order->getStoreId();
        $getResponseData = unserialize($payment->getAdditionalData());
        $redirectPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayments');
        array_push($redirectPayment, Novalnet_Payment_Model_Config::NN_PREPAYMENT, Novalnet_Payment_Model_Config::NN_INVOICE);

        $status = $paymentObj->getNovalnetConfig('order_status', '', $storeId);
        if (($paymentObj->getCode() && (in_array($paymentObj->getCode(), $redirectPayment))) || ($paymentObj->getCode() == Novalnet_Payment_Model_Config::NN_CC && isset($getResponseData['ActiveCc3d']) && $getResponseData['ActiveCc3d'])) {
            $status = $paymentObj->getNovalnetConfig('order_status_after_payment', '', $storeId);
        }
        if ($paymentObj->getCode() && $paymentObj->getCode() == Novalnet_Payment_Model_Config::NN_PAYPAL && ($this->getAdminTransaction->getStatus() == Novalnet_Payment_Model_Config::PAYPAL_PENDING_CODE)) {
            $status = $paymentObj->getNovalnetConfig('order_status', '', $storeId) ? $paymentObj->getNovalnetConfig('order_status', '', $storeId) : Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        }
        if (!$status) {
            $status = Mage_Sales_Model_Order::STATE_PROCESSING;
        }
        return $status;
    }

    /**
     * Get the Recurring Profile Order No
     *
     * @param int $recurringProfileId
     * @return int
     */
    private function getRecurringOrderNo($recurringProfileId)
    {
        $orderNo = '';
        $recurringProfileCollection = Mage::getResourceModel('sales/order_grid_collection')
                ->addRecurringProfilesFilter($recurringProfileId->getId());
        foreach ($recurringProfileCollection as $recurringProfileCollectionValue) {
            $orderNo = $recurringProfileCollectionValue->getIncrementId();
        }
        return $orderNo;
    }

    /**
     * Get the Recurring Profile Information
     *
     * @param varien_object $request
     * @return int
     */
    private function getProfileInformation($request)
    {
        $tid = (isset($request['signup_tid']) && $request['signup_tid']) ? $request['signup_tid'] : $request['tid_payment'];

        $recurringProfileId = Mage::getModel('sales/recurring_profile')->load($tid, 'reference_id');
        return $recurringProfileId;
    }

    /**
     * Get the Recurring Callback save
     *
     * @param varien_object $request
     * @param int $periodMaxCycles
     * @param Novalnet_Payment_Helper_Data $helper
     * @param int $profileId
     * @return int
     */
    private function getRecurringCallbackSave($request, $periodMaxCycles, $helper, $profileId)
    {
        $recurringCollection = $helper->getModelRecurring()->getCollection();
        $recurringCollection->addFieldToFilter('profile_id', $profileId);
        $recurringCollection->addFieldToSelect('callbackcycle');
        $countRecurring = count($recurringCollection);
        if ($countRecurring == 0) {
            $callbackCycle = 1;
            $recurringSave = $helper->getModelRecurring();
            $recurringSave->setProfileId($profileId);
            $recurringSave->setSignupTid($request['signup_tid']);
            $recurringSave->setBillingcycle($periodMaxCycles);
            $recurringSave->setCallbackcycle($callbackCycle);
            $recurringSave->setCycleDatetime($this->currentTime);
            $recurringSave->save();
        } else {
            foreach ($recurringCollection as $recurringValue) {
                $callbackCycle = $recurringValue->getCallbackcycle();
            }
            $callbackCycle = $callbackCycle + 1;
            $recurringSave = $helper->getModelRecurring()->load($profileId, 'profile_id');
            $recurringSave->setCallbackcycle($callbackCycle);
            $recurringSave->setCycleDatetime($this->currentTime);
            $recurringSave->save();
        }
        return $callbackCycle;
    }

    /**
     * Get the Recurring End Time
     *
     * @param varien_object $request
     * @param int $recurringProfileId
     * @param int $callbackCycle
     */
    private function getEndTime($request, $recurringProfileId, $callbackCycle)
    {
        $periodUnit = $recurringProfileId->getPeriodUnit();
        $periodFrequency = $recurringProfileId->getPeriodFrequency();
        $periodMaxCycles = $recurringProfileId->getPeriodMaxCycles();
        $this->endTime = 0;

        if ($callbackCycle == $periodMaxCycles) {
            $requestdata = new Varien_Object();
            $order = $this->getOrderByIncrementId($this->orderNo);
            $payment = $order->getPayment();
            $paymentObj = $payment->getMethodInstance();
            $helper = $this->_getNovalnetHelper();
            $orderItems = $order->getAllItems();
            $nominalItem = $helper->checkNominalItem($orderItems);
            $storeId = $helper->getMagentoStoreId();
            $paymentObj->assignOrderBasicParams($requestdata, $payment, $storeId, $nominalItem);
            $requestdata->setNnLang(strtoupper($helper->getDefaultLanguage()))
                    ->setCancelSub(1)
                    ->setCancelReason('other')
                    ->setTid($request['signup_tid']);
            $buildNovalnetParam = http_build_query($requestdata->getData());
            $recurringCancelUrl = $helper->getPayportUrl('paygate');
            $dataHelper = Mage::helper('novalnet_payment/AssignData');
            $response = $dataHelper->setRawCallRequest($buildNovalnetParam, $recurringCancelUrl, $payment);
            $orderNo = $this->getOrderIdByTransId();
            // load parent order
            $parentOrder = $this->getOrderByIncrementId($orderNo);
            $parentPayment = $parentOrder->getPayment();
            $data = unserialize($parentPayment->getAdditionalData());
            $data['subsCancelReason'] = 'other';
            $parentPayment->setAdditionalData(serialize($data))->save();
            $paymentObj->logNovalnetTransactionData($requestdata, $response, $request['signup_tid'], NULL, $storeId, $orderNo);
            if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                $recurringProfileId->setState('canceled');
                $recurringProfileId->save();
                $this->endTime = 1;
            } else {
                $this->endTime = 0;
            }
        }
    }

    /**
     * validate the payment type.
     *
     * @param varien_object $order
     * @return boolean
     */
    private function paymentTypeValidation($order)
    {
        $orderPaymentName = strtolower($this->getPaymentMethod($order));
        $paymentType = $this->allowedPayment[$orderPaymentName];
        if (!in_array($this->request['payment_type'], $paymentType)) {
            $this->showDebug("Novalnet callback received. Payment type (" . $this->request['payment_type'] . ") is not matched with $orderPaymentName!");
        }

        return true;
    }

    /**
     * Get Recurring total.
     *
     * @param varien_object $request
     * @param varien_object $order
     * @param mixed $nominalItem
     * @return float
     */
    private function getRecurringTotal($request, $order, $nominalItem)
    {
        if ($this->recurring || $nominalItem) {
            $profileInfo = $this->getProfileInformation($request);
            $billingAmount = $profileInfo->getBillingAmount();
            $initialAmount = $profileInfo->getInitAmount();
        }

        $changeAmount = $this->_getNovalnetHelper()->getAmountCollection($order->getId(), 1, NULL);
        if ($changeAmount) {
            $amountvalue = $changeAmount;
        } elseif (($this->recurring || $nominalItem) && ($initialAmount && $billingAmount)) {
            $amountvalue = round(($initialAmount + $billingAmount + $profileInfo->getShippingAmount()
                    + $profileInfo->getTaxAmount()), 2);
        } else {
            $amountvalue = $order->getGrandTotal();
        }

        return $amountvalue;
    }

    /**
     * Save the additional data.
     *
     * @param varien_object $payment
     * @param array $data
     * @param string $script
     * @param varien_object $order
     */
    private function saveAdditionalInfo($payment, $data, $script, $order)
    {
        $data['NnComments'] = empty($data['NnComments']) ? '<br>' . $script : $data['NnComments'] . '<br>' . $script;
        if ($payment && $data) {
            $payment->setAdditionalData(serialize($data));
            $order->setPayment($payment)
                    ->save();
        }
    }

    /**
     * Get email config
     *
     * @param int $storeId
     */
    private function getEmailConfig($storeId = NULL)
    {
        $this->emailFromAddr = Mage::getStoreConfig('trans_email/ident_general/email', $storeId);
        $this->emailToAddr = Mage::getStoreConfig('novalnet_global/novalnetsetting/emailtoaddr', $storeId);
        $this->emailBCcAddr = Mage::getStoreConfig('novalnet_global/novalnetsetting/emailBcc', $storeId);
        $this->emailSubject = 'Novalnet Callback Script Access Report';
        $this->emailBody = "";
        $this->emailFromName = Mage::getStoreConfig('trans_email/ident_general/name', $storeId);
        $this->emailToName = "";
        $this->callBackExecuted = false;
    }

    /*
     * Get given payment_type level for process
     *
     * @return Integer | boolean
     */

    private function getPaymentTypeLevel()
    {
        if (!empty($this->request)) {
            if (in_array($this->request['payment_type'], $this->paymentTypes)) {
                return 0;
            } else if (in_array($this->request['payment_type'], $this->chargebacks)) {
                return 1;
            } else if (in_array($this->request['payment_type'], $this->aryCollection)) {
                return 2;
            }
        }
        return false;
    }

    /**
     * get order id based on last transaction id.
     *
     * @return int
     */
    private function getOrderIdByTransId()
    {
        $request = $this->request;
        $orgTid = $this->requestTid($request);

        $tablePrefix = Mage::getConfig()->getTablePrefix();
        if (in_array($request['payment_type'], $this->chargebacks)) {
            $orderPayment = $tablePrefix . 'sales_payment_transaction';
            $onCondition = "main_table.entity_id = $orderPayment.order_id";
            $orderCollection = Mage::getModel('sales/order')->getCollection()
                    ->addFieldToFilter('txn_id', array('like' => "%$orgTid%"))
                    ->addFieldToSelect('increment_id');
        } else {
            $orderPayment = $tablePrefix . 'sales_flat_order_payment';
            $onCondition = "main_table.entity_id = $orderPayment.parent_id";
            $orderCollection = Mage::getModel('sales/order')->getCollection()
                    ->addFieldToFilter('last_trans_id', array('like' => "%$orgTid%"))
                    ->addFieldToSelect('increment_id');
        }

        $orderCollection->getSelect()->join($orderPayment, $onCondition);
        $count = $orderCollection->count();
        if ($count > 0) {
            foreach ($orderCollection as $order) {
                $orderid = $order->getIncrementId();
            }
        }

        $orderId = !empty($orderid) ? $orderid : '';
        return $orderId;
    }

    /**
     * Get transaction id based on payment type
     *
     * @param array $request
     * @return int $tidPayment
     */
    private function requestTid($request)
    {
        if (isset($request['signup_tid']) && $request['signup_tid']) {
            $tidPayment = trim($request['signup_tid']);
        } elseif ((in_array($this->request['payment_type'], $this->chargebacks)) || ($this->request['payment_type'] == 'INVOICE_CREDIT')) {
            $tidPayment = trim($request['tid_payment']);
        } else {
            $tidPayment = trim($request['tid']);
        }

        return $tidPayment;
    }

    /**
     * New order create process
     *
     * @param varien_object $order
     * @param string $script
     * @param array $data
     * @param varien_object $paymentold
     * @param int $profileId
     */
    private function createOrder($order, $script, $data, $paymentold, $profileId)
    {
        $helper = $this->_getNovalnetHelper();
        $paymentCode = $order->getPayment()->getMethodInstance()->getCode();
        $additionalInfo = $order->getPayment()->getAdditionalInformation();
        $storeId = $order->getStoreId();
        $this->setLanguageStore($storeId);
        $orderNew = Mage::getModel('sales/order')
                ->setState('new');

        $orderPayment = Mage::getModel('sales/order_payment')
                ->setStoreId($storeId)
                ->setMethod($paymentCode)
                ->setPo_number('-');
        $orderNew->setPayment($orderPayment);
        $orderNew = $this->setOrderDetails($order, $orderNew);
        $billingAddress = Mage::getModel('sales/order_address');
        $getBillingAddress = Mage::getModel('sales/order_address')->load($order->getBillingAddress()->getId());
        $orderNew = $this->setBillingShippingAddress($getBillingAddress, $billingAddress, $orderNew, $order);
        $isVirtual = $order->getIsVirtual();

        if ($isVirtual == 0) {
            $shippingAddress = Mage::getModel('sales/order_address');
            $getShippingAddress = Mage::getModel('sales/order_address')->load($order->getShippingAddress()->getId());
            $orderNew = $this->setBillingShippingAddress($getShippingAddress, $shippingAddress, $orderNew, $order);
        }

        $orderNew = $this->setOrderItemsDetails($order, $orderNew);
        $payment = $orderNew->getPayment();
        $paymentObj = $payment->getMethodInstance();
        $setOrderAfterStatus = $paymentObj->getConfigData('order_status', $storeId);
        $setOrderAfterStatus = $setOrderAfterStatus ? $setOrderAfterStatus : Mage_Sales_Model_Order::STATE_PROCESSING;
        $orderNew->addStatusToHistory($setOrderAfterStatus, $helper->__('Novalnet Recurring Callback script Executed Successfully'), false);
        $tid = trim($this->request['tid']);
        $orderNew->save();
        $newOrderId = $orderNew->getId();
        $parentOrderNo = $this->getOrderIdByTransId() ? $this->getOrderIdByTransId() : $orderNew->getIncrementId();
        $script .= $comments = ' Recurring Payment for order id : ' . $parentOrderNo . '<br>';

        $script .= $nextDate = !$this->endTime ? '<br>Next Payment Date is: ' . $this->getAdminTransaction->getNextSubsCycle() . '<br>' : '';
        $this->emailBody = $script;

        $newData = array('NnTestOrder' => $this->request['test_mode'],
            'NnTid' => $tid,
            'orderNo' => $orderNew->getIncrementId(),
            'vendor' => $data['vendor'],
            'auth_code' => $data['auth_code'],
            'product' => $data['product'],
            'tariff' => $data['tariff'],
            'key' => $data['key'],
            'NnComments' => ($comments . $nextDate)
        );
        if ($paymentCode == 'novalnetPrepayment' || $paymentCode == 'novalnetInvoice') {
            $dataObj = new Varien_Object($this->request);
            $dataHelper = Mage::helper('novalnet_payment/AssignData');
            $newData['NnNoteDesc'] = $dataHelper->getNoteDescription();
            $newData['NnRecurringOrder'] = 1;
            $newData['NnDueDate'] = isset($this->request['due_date']) ? ($helper->__('Due Date') . ' : <b>' . Mage::helper('core')->formatDate($this->request['due_date']) . "</b><br />") : '';
            $newData['NnNote'] = $dataHelper->getNote($dataObj);
            $newData['NnNoteAmount'] = $dataHelper->getBankDetailsAmount(($this->request['amount'] / 100));
        }

        $payment->setTransactionId($tid)
                ->setAdditionalData(serialize($newData))
                ->setAdditionalInformation($additionalInfo)
                ->setLastTransId($tid)
                ->setParentTransactionId(null)
                ->save();
        $orderNew->sendNewOrderEmail()
                ->setEmailSent(true)
                ->setPayment($payment)
                ->save();
        $this->insertOrderId($newOrderId, $profileId);
        $getTransactionStatus = $paymentObj->doNovalnetStatusCall($tid, $payment);
        $this->_logStatusData($getTransactionStatus, $tid, $orderNew, $paymentCode);
        if ($orderNew->canInvoice() && $getTransactionStatus->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED && $paymentCode != Novalnet_Payment_Model_Config::NN_PREPAYMENT) {
            $invoice = $orderNew->prepareInvoice();
            $invoice->setTransactionId($tid);
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE)
                    ->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)
                    ->register();

            Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();

            $magentoVersion = $this->_getNovalnetHelper()->getMagentoVersion();
            $transMode = (version_compare($magentoVersion, '1.6', '<')) ? false : true;

            $payment->setTransactionId($tid)
                    ->setIsTransactionClosed($transMode);
        }
        $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, null, false);
        $transaction->setParentTxnId(null)
                ->save();
        $this->updateInventory($order);
    }

    /**
     * Log Novalnet transaction status data
     *
     * @param varien_object $response
     * @param int $txnId
     * @param varien_object $order
     * @param string $paymentCode
     * @return null
     */
    private function _logStatusData($response, $txnId, $order, $paymentCode)
    {
        $helper = $this->_getNovalnetHelper();
        $customerId = $order->getCustomerId() ? $order->getCustomerId() : '';
        $storeId = $order->getStoreId() ? $order->getStoreId() : '';
        $amount = $this->request['amount'] ? ($this->request['amount'] / 100) : '';
        $modNovalTransactionStatus = $helper->getModelTransactionStatus();
        $modNovalTransactionStatus->setTransactionNo($txnId)
                ->setOrderId($order->getIncrementId())  // Order number
                ->setTransactionStatus($response->getStatus()) // Transaction status code
                ->setCustomerId($customerId) // Customer number
                ->setPaymentName($paymentCode)   // Payment name
                ->setAmount($amount)  // Amount
                ->setRemoteIp($helper->getRealIpAddr()) // Remote ip
                ->setStoreId($storeId)  // Store id
                ->setShopUrl($helper->getCurrentSiteUrl())
                ->setCreatedDate($helper->getCurrentDateTime()) // Created date
                ->save();
    }

    /**
     * Set billing and sipping address informations
     *
     * @param varien_object $getBillingAddress
     * @param varien_object $billingAddress
     * @param varien_object $orderNew
     * @param varien_object $order
     * @return mixed
     */
    private function setBillingShippingAddress($getBillingAddress, $billingAddress, $orderNew, $order)
    {
        $addressType = $getBillingAddress->getAddressType();
        $billingStreet = $getBillingAddress->getStreet();
        $street = !empty($billingStreet[1]) ? array($billingStreet[0], $billingStreet[1]) : array($billingStreet[0]);
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
     * Set order amount and customer informations
     *
     * @param varien_object $order
     * @param varien_object $orderNew
     * @return mixed
     */
    private function setOrderDetails($order, $orderNew)
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
     * Set product informations (product, discount, tax, etc.,)
     *
     * @param varien_object $order
     * @param varien_object $orderNew
     * @return mixed
     */
    private function setOrderItemsDetails($order, $orderNew)
    {
        foreach ($order->getAllItems() as $orderValue) {
            $orderItem = Mage::getModel('sales/order_item')
                    ->setStoreId($orderValue->getStoreId())
                    ->setQuoteItemId(0)
                    ->setQuoteParentItemId(NULL)
                    ->setQtyBackordered(NULL)
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
     * Set the language by storeid
     *
     * @param int $storeId
     */
    private function setLanguageStore($storeId)
    {
        $app = Mage::app();
        $app->setCurrentStore($storeId);
        $locale = Mage::getStoreConfig('general/locale/code', $storeId);
        $app->getLocale()->setLocaleCode($locale);
        Mage::getSingleton('core/translate')->setLocale($locale)->init('frontend', true);
    }

    /**
     * Insert the order id in recurring order table
     *
     * @param int $newOrderId
     * @param int $profileId
     */
    private function insertOrderId($newOrderId, $profileId)
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
     * update the product inventory (stock)
     *
     * @param varien_object $order
     */
    private function updateInventory($order)
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
     * Show debug message
     *
     * @param string $text
     * @param bool $die
     */
    public function showDebug($text, $die = true) {
        if ($this->debug == true && !empty($text)) {
            echo $text;
        }
        if ($die) {
            die;
        }
    }

}
