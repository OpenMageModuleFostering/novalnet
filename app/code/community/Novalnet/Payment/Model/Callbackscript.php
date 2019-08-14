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
class Novalnet_Payment_Model_Callbackscript
{

    var $debug = false; //false|true; adapt: set to false for go-live
    var $test = false; //false|true; adapt: set to false for go-live
    var $log = false; //false|true; adapt
    var $createInvoice = true; //false|true; adapt for your need
    var $useZendEmail = true; //false|true; adapt for your need
    var $addSubsequentTidToDb = true; //whether to add the new tid to db; adapt if necessary
    //Security Setting; only this IP is allowed for call back script
    var $ipAllowed = array('195.143.189.210', '195.143.189.214'); //Novalnet IP, is a fixed value, DO NOT CHANGE!!!!!

    public function Callback()
    {
        $this->allowedPayment = array(
            'novalnetcc' => array('CREDITCARD', 'CREDITCARD_BOOKBACK', 'CREDITCARD_CHARGEBACK',
                'CREDIT_ENTRY_CREDITCARD', 'SUBSCRIPTION_STOP', 'DEBT_COLLECTION_CREDITCARD'),
            'novalnetinvoice' => array('INVOICE_START', 'INVOICE_CREDIT', 'SUBSCRIPTION_STOP'),
            'novalnetprepayment' => array('INVOICE_START', 'INVOICE_CREDIT', 'SUBSCRIPTION_STOP'),
            'novalnetideal' => array('IDEAL'),
            'novalnetpaypal' => array('PAYPAL'),
            'novalneteps' => array('EPS'),
            'novalnetsofortueberweisung' => array('ONLINE_TRANSFER', 'REFUND_BY_BANK_TRANSFER_EU'),
            'novalnetsepa' => array('DIRECT_DEBIT_SEPA', 'RETURN_DEBIT_SEPA', 'SUBSCRIPTION_STOP',
                'DEBT_COLLECTION_SEPA', 'CREDIT_ENTRY_SEPA'));
        $this->invoiceAllowed = array('INVOICE_CREDIT', 'INVOICE_START');

        /** @Array Type of payment available - Level : 0 */
        $this->paymentTypes = array('INVOICE_START', 'PAYPAL', 'ONLINE_TRANSFER',
            'CREDITCARD', 'IDEAL', 'DIRECT_DEBIT_SEPA', 'PAYSAFECARD', 'EPS', 'GUARANTEED_INVOICE_START');
        /** @Array Type of Charge backs available - Level : 1 */
        $this->chargeBackPayments = array('CREDITCARD_CHARGEBACK', 'RETURN_DEBIT_SEPA',
            'CREDITCARD_BOOKBACK', 'REFUND_BY_BANK_TRANSFER_EU');
        /** @Array Type of CreditEntry payment and Collections available - Level : 2 */
        $this->aryCollection = array('INVOICE_CREDIT', 'GUARANTEED_INVOICE_CREDIT',
            'CREDIT_ENTRY_CREDITCARD', 'CREDIT_ENTRY_SEPA', 'DEBT_COLLECTION_SEPA',
            'DEBT_COLLECTION_CREDITCARD');
        $this->arySubscription = array('SUBSCRIPTION_STOP');

        $this->request = $this->getParams();
        $this->helper = Mage::helper('novalnet_payment');
        $httpHost = Mage::helper('core/http')->getHttpHost();
        $this->lineBreak = empty($httpHost) ? PHP_EOL : '<br />';

        //Reporting Email Addresses Settings
        $this->shopInfo = 'Magento ' . $this->lineBreak; //mandatory;adapt for your need
        $this->mailHost = Mage::getStoreConfig('system/smtp/host'); //adapt or Mage::getStoreConfig('system/smtp/host')
        $this->mailPort = Mage::getStoreConfig('system/smtp/port'); //adapt or Mage::getStoreConfig('system/smtp/port')
        $this->emailFromAddr = ''; //sender email address., mandatory, adapt it
        $this->emailToAddr = ''; //recipient email address., mandatory, adapt it
        $this->emailSubject = 'Novalnet Callback Script Access Report'; //adapt if necessary;
        $this->emailBody = ""; //Email text's 1. line, can be let blank, adapt for your need
        $this->emailFromName = ""; // Sender name, adapt
        $this->emailToName = ""; // Recipient name, adapt
        $this->callBackExecuted = false;

        if (isset($this->request['debug_mode']) && $this->request['debug_mode'] == 1) {
            $this->debug = true;
            $this->test = true;
            $this->emailFromAddr = 'testadmin@novalnet.de';
            $this->emailFromName = 'Novalnet';
            $this->emailToAddr = 'test@novalnet.de';
            $this->emailToName = 'Novalnet';
        }


        if (isset($this->request['vendor_activation']) && $this->request['vendor_activation'] == 1) {
            $this->doNovalnetAffAccInfoLog();
            return false;
        }
        //Parameters Settings
        $this->hParamsRequired = array(
            'vendor_id' => '',
            'tid' => '',
            'payment_type' => '',
            'status' => '',
            'amount' => '',
            'tid_payment' => '',
            'tid' => ''
        );

        if (!in_array($this->request['payment_type'], array_merge($this->invoiceAllowed, $this->chargeBackPayments))) {
            unset($this->hParamsRequired['tid_payment']);
        }

        ksort($this->hParamsRequired);

        try {

            //Check Params
            if ($this->checkIP()) {
                $request = $this->request;
                $this->orderNo = isset($request['order_no']) && $request['order_no'] ? $request['order_no']
                            : '';
                if ($this->orderNo == '') {
                    unset($this->hParamsRequired['order_no']);
                    $this->orderNo = $this->getOrderIdByTransId();
                }

                if (empty($request['payment_type'])) {
                    echo "Required param (payment_type) missing!";
                } elseif (empty($this->orderNo)) {
                    echo "Order no is missing !" . $this->lineBreak;
                } elseif (!empty($request['payment_type']) && in_array(strtoupper($request['payment_type']), array_merge($this->paymentTypes, $this->chargeBackPayments, $this->aryCollection, $this->arySubscription))) {
                    //Complete the order in-case response failure from novalnet server
                    $order = $this->getOrderByIncrementId($this->orderNo);

                    if ($order->getIncrementId()) {
                        $payment = $order->getPayment();
                        $paymentObj = $payment->getMethodInstance();
                        $this->storeId = $order->getStoreId();
                        $this->redirectPayment = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('redirectPayments');
                        $paymentTid = (in_array($request['payment_type'], $this->invoiceAllowed))
                                || (in_array($request['payment_type'], $this->chargeBackPayments))
                                    ? $request['tid_payment'] : $request['tid'];
                        // Get Admin Transaction status via API
                        $this->getAdminTransaction = $paymentObj->doNovalnetStatusCall($paymentTid, $payment);
                        // Validate the payment type for the particular order
                        $this->paymentTypeValidation($order);
                        $checkTidExist = $payment->getLastTransId();

                        if (!empty($this->orderNo) && $order->getIncrementId() == $this->orderNo
                                && empty($checkTidExist)) {

                            //Unhold an order:-
                            if ($order->canUnhold()) {
                                $order->unhold()->save();
                            }

                            // save the payment additional information
                            $serverResMode = $request['test_mode'];
                            $shopMode = $paymentObj->_getConfigData('live_mode', '', $this->storeId);
                            $this->testMode = (((isset($serverResMode) && $serverResMode
                                    == 1) || (isset($shopMode) && $shopMode == 0))
                                                ? 1 : 0 );
                            $data = array('NnTestOrder' => $this->testMode);
                            $additionalData = unserialize($payment->getAdditionalData());
                            $data = $additionalData ? array_merge($additionalData, $data)
                                        : $data;
                            $payment->setStatus(Novalnet_Payment_Model_Payment_Method_Abstract::STATUS_SUCCESS)
                                    ->setStatusDescription($this->helper->__('Payment was successful.'))
                                    ->setAdditionalData(serialize($data))
                                    ->save();
                            // save the order transaction information
                            $dataObj = new Varien_Object($request);
                            if (in_array($paymentObj->getCode(), $this->redirectPayment)) {
                                $authorizeKey = $paymentObj->_getConfigData('password', true);
                                $responseAmount = is_numeric($request['amount'])
                                            ? $request['amount'] : $this->helper->getDecodedParam($request['amount'], $authorizeKey);
                                $amount = $this->helper->getFormatedAmount($responseAmount, 'RAW');
                            } else {
                                $amount = $this->helper->getFormatedAmount($request['amount'], 'RAW');
                            }
                            $this->helper->doTransactionStatusSave($dataObj, $this->getAdminTransaction, $payment, $amount);

                            // Payment process based on response status
                            if ($payment->getAdditionalInformation($paymentObj->getCode() . '_successAction')
                                    != 1) {
                                $payment->setAdditionalInformation($paymentObj->getCode() . '_successAction', 1);
                                if ($order->canInvoice() && $this->getAdminTransaction->getStatus()
                                        == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                                    $payment->setTransactionId($paymentTid) // Add capture text to make the new transaction
                                            ->setParentTransactionId(null)
                                            ->setIsTransactionClosed(true)
                                            ->setLastTransId($paymentTid)
                                            ->capture(null)
                                            ->save();
                                } else {
                                    $payment->setTransactionId($paymentTid)
                                            ->setLastTransId($paymentTid)
                                            ->setParentTransactionId(null)
                                            ->save();
                                }

                                $onHoldStatus = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('paymentOnholdStaus');
                                array_push($onHoldStatus, '100', '90');

                                if (in_array($this->getAdminTransaction->getStatus(), $onHoldStatus)) {
                                    $orderStatus = $this->getOrderStatus($order, $this->getAdminTransaction);
                                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $orderStatus, $this->helper->__('Customer successfully returned from Novalnet'), true
                                    )->save();
                                } else {
                                    $paymentObj->saveCancelledOrder($dataObj, $payment);
                                }
                            }
                            $this->callBackExecuted = true;
                            $customerId = $order->getCustomerId();
                            // save the server response details
                            $this->helper->doTransactionOrderLog($dataObj, $this->orderNo, $this->storeId, $customerId);

                            //sendNewOrderEmail
                            if (!$order->getEmailSent() && $order->getId() && in_array($this->getAdminTransaction->getStatus(), $onHoldStatus)) {
                                try {
                                    $order->sendNewOrderEmail()
                                            ->setEmailSent(true)
                                            ->save();
                                } catch (Exception $e) {
                                    Mage::throwException($this->helper->__('Cannot send new order email.'));
                                }
                            }
                            $order->save();
                        }
                        if ($this->checkParams()) {
                            //Get Order ID and Set New Order Status
                            $ordercheckstatus = $this->BasicValidation($this->orderNo, $order);
                            if ($ordercheckstatus) {
                                if ($this->getAdminTransaction->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                                    $this->setOrderStatus($this->orderNo, $order);
                                } else {
                                    echo isset($this->getAdminTransaction['transaction_status']['status_message'])
                                                ? ($this->getAdminTransaction['transaction_status']['status_message'])
                                                : 'Error in processing the transactions status';
                                    exit;
                                }
                            }
                        }
                        if ($this->log) {
                            $logFile = 'novalnet_callback_script_' . date('Y-m-d') . '.log';
                            Mage::log('Ein Haendlerskript-Aufruf fand statt mit StoreId ' . $this->storeId . " und Parametern:$this->lineBreak" . print_r($request, true), null, $logFile);
                        }
                    } else {
                        echo "Order no [" . $this->orderNo . "] is not valid! $this->lineBreak";
                    }
                } else {
                    echo "Payment type [" . $request['payment_type'] . "] is mismatched! $this->lineBreak";
                }
            }
        } catch (Exception $e) {
            $this->emailBody .= "Exception catched: $this->lineBreak\$e:" . $e->getMessage() . $this->lineBreak;
        }
        // Callback E-mail Notification
        $this->callbackMail();
    }

    // ############## Sub Routines #####################

    /**
     * Callback E-mail Notification
     *
     */
    private function callbackMail()
    {
        if ($this->emailBody && $this->emailFromAddr && ($this->emailToAddr && $this->validateEmail())) {
            if (!$this->sendMail()) {
                if ($this->debug) {
                    echo "Mailing failed!" . $this->lineBreak;
                    echo "This mail text should be sent: " . $this->lineBreak;
                }
            }
        }

        if ($this->debug) {
            echo $this->emailBody;
        }
    }

    /**
     * Send callback notification E-mail
     *
     */
    private function sendMail()
    {

        if ($this->useZendEmail) {
            if (!$this->sendEmailZend()) {
                return false;
            }
        } else {
            if (!$this->sendEmailMagento()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send callback notification E-mail via Magento
     *
     */
    private function sendEmailMagento()
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
        $emailTemplVariables = array();
        $emailTemplVariables['fromName'] = $this->emailFromName;
        $emailTemplVariables['fromEmail'] = $this->emailFromAddr;
        $emailTemplVariables['toName'] = $this->emailToName;
        $emailTemplVariables['toEmail'] = $this->emailToAddr;
        $emailTemplVariables['subject'] = $this->emailSubject;
        $emailTemplVariables['body'] = $this->emailBody;
        $processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplVariables);

        //Send Email
        if ($this->mailHost && $this->mailPort) {
            ini_set('SMTP', $this->mailHost);
            ini_set('smtp_port', $this->mailPort);
        }

        $mail = new Zend_Mail();
        $mail->setBodyHtml($processedTemplate);
        $mail->setFrom($this->emailFromAddr, $this->emailFromName);
        $mail->addTo($this->emailToAddr, $this->emailToName);
        $mail->setSubject($this->emailSubject);

        try {
            //Confirmation E-Mail Send
            $mail->send();
            if ($this->debug) {
                echo __FUNCTION__ . ': Sending Email succeeded!' . $this->lineBreak;
            }
        } catch (Exception $e) {
            Mage::getSingleton('core/session')
                    ->addError($this->helper->__('Unable to send email'));
            if ($this->debug) {
                echo 'Email sending failed: ' . $e->getMessage();
            }
            return false;
        }
        return true;
    }

    /**
     * Send callback notification E-mail via zend
     *
     */
    private function sendEmailZend()
    {

        if ($this->mailHost && $this->mailPort) {
            ini_set('SMTP', $this->mailHost);
            ini_set('smtp_port', $this->mailPort);
        }

        $mail = new Zend_Mail();
        $mail->setBodyHtml($this->emailBody);
        $mail->setFrom($this->emailFromAddr, $this->emailFromName);
        $mail->addTo($this->emailToAddr, $this->emailToName);
        $mail->setSubject($this->emailSubject);

        try {
            $mail->send();
            if ($this->debug) {
                echo __FUNCTION__ . ': Sending Email succeeded!' . $this->lineBreak;
            }
        } catch (Exception $e) {
            if ($this->debug) {
                echo 'Email sending failed: ' . $e->getMessage();
            }
            return false;
        }
        return true;
    }

    /**
     * validate the payment type.
     *
     */
    private function paymentTypeValidation($order)
    {
        $request = $this->request;
        $orderPaymentName = strtolower($this->getPaymentMethod($order));
        $paymentType = $this->allowedPayment[$orderPaymentName];

        if (!in_array($request['payment_type'], $paymentType)) {
            echo "Novalnet callback received. Payment type (" . $request['payment_type'] . ") is not matched with $orderPaymentName!$this->lineBreak$this->lineBreak";
            exit;
        }

        return true;
    }

    /**
     * Check the email id is valid
     *
     * @param $emailId
     * @return bool
     */
    private function validateEmail()
    {
        $validatorEmail = new Zend_Validate_EmailAddress();
        if (!$validatorEmail->isValid(trim($this->emailToAddr))) {
            return false;
        }
        return true;
    }

    /**
     * Check the callback mandatory parameters.
     *
     */
    private function checkParams()
    {
        $error = false;
        $request = $this->request;
        if (!$request) {
            echo 'Novalnet callback received. No params passed over!' . $this->lineBreak;
            exit;
        }

        if ($this->hParamsRequired) {
            foreach ($this->hParamsRequired as $k => $v) {
                if (!isset($request[$k]) || empty($request[$k])) {
                    $error = true;
                    echo 'Required param (' . $k . ') missing!' . $this->lineBreak;
                }
            }
            if ($error) {
                exit;
            }
        }

        if ((in_array($request['payment_type'], $this->invoiceAllowed)) || (in_array($request['payment_type'], $this->chargeBackPayments))) {
            if (strlen($request['tid_payment']) != 17) {
                echo 'Novalnet callback received. Invalid TID [' . $request['tid_payment'] . '] for Order:' . $this->orderNo . ' ' . "$this->lineBreak$this->lineBreak" . $this->lineBreak;
                exit;
            }
        }

        if (strlen($request['tid']) != 17) {
            if ((in_array($request['payment_type'], $this->invoiceAllowed)) || (in_array($request['payment_type'], $this->chargeBackPayments))) {
                echo 'Novalnet callback received. New TID is not valid.' . "$this->lineBreak$this->lineBreak" . $this->lineBreak;
            } else {
                echo 'Novalnet callback received. Invalid TID [' . $request['tid'] . '] for Order:' . $this->orderNo . ' ' . "$this->lineBreak$this->lineBreak" . $this->lineBreak;
            }
            exit;
        }

        if (!empty($request['status']) && 100 != $request['status']) {
            echo "Novalnet callback received. Callback Script executed already. Refer Order :" . $this->orderNo . $this->lineBreak;
            exit;
        }
        return true;
    }

    /**
     * validate the callback parameters.
     *
     */
    private function BasicValidation($orderNo, $order)
    {

        if ($order->getIncrementId() == $orderNo && !empty($orderNo)) {
            $request = $this->request;
            //check amount
            $amount = $request['amount'];
            if (!$amount || intval($amount) < 0) {
                echo "Novalnet callback received. The requested amount ($amount) must be greater than zero.$this->lineBreak$this->lineBreak";
                exit;
            }

            if ((in_array($request['payment_type'], $this->invoiceAllowed)) || (in_array($request['payment_type'], $this->chargeBackPayments))) {
                $orgTid = $request['tid_payment'];
            } else {
                $orgTid = $request['tid'];
            }

            $payment = $order->getPayment();
            $additionalData = unserialize($payment->getAdditionalData());
            $orderTid =  ((in_array($request['payment_type'], $this->chargeBackPayments)) && $additionalData['NnTid'])
                        ? $additionalData['NnTid'] : $order->getPayment()->getLastTransId();
            if (!preg_match('/^' . $orgTid . '/i', $orderTid)) {
                echo 'Novalnet callback received. Order no is not valid' . "$this->lineBreak$this->lineBreak" . $this->lineBreak;
                exit;
            }

            return true;
        } else {
            echo 'Transaction mapping failed. no order data found' . $this->lineBreak;
            exit;
        }
    }

    /**
     * Set the status and state to an order payment
     *
     */
    private function setOrderStatus($incrementId, $order)
    {

        if ($order) {
            $request = $this->request;
            $order->getPayment()->getMethodInstance()->setCanCapture(true);
            $orderStatus = $this->getOrderStatus($order);
            $orderState = Mage_Sales_Model_Order::STATE_PROCESSING; //processing: ok; complete: not ok -> would cause the error msg: 'Der Bestellzustand "complete" darf nicht manuell gesetzt werden'

            // add some feature regarding subscription and collection; adapt for your need.
            $this->subsCollectionInfo();

            if (in_array($request['payment_type'], $this->chargeBackPayments)) {
                $payment = $order->getPayment();
                $data = unserialize($payment->getAdditionalData());
                $currency = $this->getAdminTransaction->getCurrency();
                $script = 'Novalnet Callback received. Charge back was executed sucessfully for the TID ' . $request['tid_payment'] . ' amount ' . ($request['amount'])
                        / 100 . ' ' . $currency . " on " . date('Y-m-d H:i:s');
                $this->emailBody = $script;

                $data['NnComments'] = empty($data['NnComments']) ? '<br>' . $script
                            : $data['NnComments'] . '<br>' . $script;
                $this->saveAdditionalInfo($payment, $data);
                return true;
            }

            if ($this->createInvoice) {
                $saveinvoice = $this->saveInvoice($order);
            }

            if ($order->getInvoiceCollection()->getFirstItem()) {
                if ($saveinvoice) {
                    $order->setState($orderState, true, 'Novalnet callback set state ' . $orderState . ' for Order-ID = ' . $incrementId);
                    $order->addStatusToHistory($orderStatus, 'Novalnet callback added order status ' . $orderStatus); // this line must be located after $order->setState()
                    $this->emailBody .= 'Novalnet callback set state to ' . $orderState . $this->lineBreak;
                    $this->emailBody .= 'Novalnet callback set status to ' . $orderStatus . ' ... ' . $this->lineBreak;
                    $order->save();

                    //Add subsequent TID to DB column last_trans_id
                    if ($this->addSubsequentTidToDb) {
                        $payment = $order->getPayment();
                        $data = unserialize($payment->getAdditionalData());
                        $magentoVersion = $this->helper->getMagentoVersion();
                        $transMode = (version_compare($magentoVersion, '1.6', '<'))
                                    ? false : true;
                        $amount = $this->helper->getFormatedAmount($request['amount'], 'RAW');
                        $currency = $this->getAdminTransaction->getCurrency();
                        if (in_array($request['payment_type'], $this->invoiceAllowed)) {
                            $payment->setTransactionId($request['tid'])
                                    ->setIsTransactionClosed($transMode);
                            $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, null, false);
                            $transaction->setParentTxnId(null)
                                    ->save();
                            $script = "Novalnet Callback Script executed successfully for the TID: " . $request['tid_payment'] . " with amount " . $amount ." ". $currency . " on " . date('Y-m-d H:i:s') . ". Please refer PAID transaction in our Novalnet Merchant Administration with the TID: " . $request['tid'] . ". ";
                        } else {
                            if ($request['payment_type'] == 'PAYPAL') {
                                $loadTransaction = $this->helper->loadTransactionStatus(trim($request['tid']));
                                $loadTransaction->setTransactionStatus($this->getAdminTransaction->getStatus())
                                                ->save();
                            }
                            $script = "Novalnet Callback Script executed successfully for the TID: " . $request['tid'] . " with amount " . $amount ." ". $currency . " on " . date('d-m-Y H:i:s');
                        }

                        $data['NnComments'] = empty($data['NnComments']) ? '<br>' . $script
                                    : $data['NnComments'] . '<br>' . $script;
                        $this->saveAdditionalInfo($payment, $data);
                    }
                }
            } else {
                echo "Novalnet Callback: No invoice for order (" . $order->getId() . ") found";
                exit;
            }
        } else {
            echo "Novalnet Callback: No order for Increment-ID $incrementId found.";
            exit;
        }
        return true;
    }

    /**
     * Create invoice to order payment
     *
     */
    private function saveInvoice($order)
    {

        if (!$this->callBackExecuted) {
            $request = $this->request;
            $payment = $order->getPayment();
            $paymentObj = $payment->getMethodInstance();
            $data = unserialize($payment->getAdditionalData());
            $modNovalCallback = Mage::getModel('novalnet_payment/callback')->loadLogByOrderId($this->orderNo);
            $amount = $this->helper->getFormatedAmount($request['amount'], 'RAW');
            $sum = sprintf( ($request['amount'] + $modNovalCallback->getCallbackAmount()) , 0.2);
            $grandTotal = sprintf( ($order->getGrandTotal() * 100) , 0.2);

            if (in_array($request['payment_type'], $this->invoiceAllowed) && $sum
                    < $grandTotal) {

                $this->doNovalnetCallbackLog($modNovalCallback, $sum);

                $this->emailBody = "Novalnet Callback Script executed successfully for the TID: " . $request['tid_payment'] . " with amount " . $amount ." ". $this->getAdminTransaction->getCurrency() . " on " . date('Y-m-d H:i:s') . ". Please refer PAID transaction in our Novalnet Merchant Administration with the TID: " . $request['tid'] . "$this->lineBreak$this->lineBreak";

                $callbackComments = "Novalnet Callback Script executed successfully. Payment for order id :" . $this->orderNo . '. New TID: ' . $request['tid'] . ' on ' . date('Y-m-d H:i:s') . ' for the amount : ' . $amount . ' ' . $this->getAdminTransaction->getCurrency() . $this->lineBreak;
                $data['NnComments'] = empty($data['NnComments']) ? '<br>' . $callbackComments
                            : $data['NnComments'] . '<br>' . $callbackComments;
                $this->saveAdditionalInfo($payment, $data);
                return false;
            } else {

                $this->doNovalnetCallbackLog($modNovalCallback, $sum);

                if ($order->canInvoice()) {
                    $tid = in_array($request['payment_type'], $this->invoiceAllowed)
                                ? $request['tid_payment'] : $request['tid'];
                    $invoice = $order->prepareInvoice();
                    $invoice->setTransactionId($tid);
                    $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE)
                            ->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)
                            ->register();
                    Mage::getModel('core/resource_transaction')
                            ->addObject($invoice)
                            ->addObject($invoice->getOrder())
                            ->save();

                    if (in_array($request['payment_type'], $this->invoiceAllowed)) {
                        $emailText = "Novalnet Callback Script executed successfully for the TID: " . $request['tid_payment'] . " with amount " . $amount ." ".$this->getAdminTransaction->getCurrency() . " on " . date('Y-m-d H:i:s') . ". Please refer PAID transaction in our Novalnet Merchant Administration with the TID: " . $request['tid'] . ". ";
                        $this->emailBody = ($sum > $grandTotal) ? $emailText . "Customer paid amount is greater than the order total amount. $this->lineBreak$this->lineBreak"
                                    : $emailText . "$this->lineBreak$this->lineBreak";
                    } else {
                        $this->emailBody = "Novalnet Callback Script executed successfully for the TID: " . $request['tid'] . " with amount " . $amount ." ". $this->getAdminTransaction->getCurrency() . " on " . date('d-m-Y H:i:s') . ". $this->lineBreak$this->lineBreak";
                    }
                } else {
                    if ($paymentObj->getCode() == Novalnet_Payment_Model_Config::NN_INVOICE &&
                        $payment->getAdditionalInformation($paymentObj->getCode() . '_callbackSuccess') != 1) {
                        $currency = $this->getAdminTransaction->getCurrency();
                        $script = "Novalnet Callback Script executed successfully for the TID: " . $request['tid_payment'] . " with amount " . $amount ." ". $currency . " on " . date('Y-m-d H:i:s') . ". Please refer PAID transaction in our Novalnet Merchant Administration with the TID: " . $request['tid'] . ". ";
                        $data['NnComments'] = empty($data['NnComments']) ? '<br>' . $script
                                    : $data['NnComments'] . '<br>' . $script;
                        $payment->setAdditionalInformation($paymentObj->getCode() . '_callbackSuccess', 1);
                        $this->saveAdditionalInfo($payment, $data);
                        $this->saveOrderStatus($order);
                        $this->emailBody = $script;
                        $this->callbackMail();
                    }

                    $invoicePayments = array(Novalnet_Payment_Model_Config::NN_PREPAYMENT, Novalnet_Payment_Model_Config::NN_INVOICE);
                    if (in_array($paymentObj->getCode(), $invoicePayments)) {
                        echo "Novalnet callback received. Callback Script executed already. Refer Order :" . $this->orderNo . $this->lineBreak;
                    } else {
                        echo "Novalnet Callbackscript received. Payment type ( " . $request['payment_type'] . " ) is not applicable for this process!" . $this->lineBreak;
                    }
                    exit;
                }
            }
        }
        return true;
    }

    /**
     * save the order status for invoice payment method
     *
     */
    function saveOrderStatus($order)
    {
        $request = $this->request;
        $payment = $order->getPayment();
        $originalTid = trim($payment->getLastTransId());
        // save paid transaction id
        $transaction = Mage::getModel('sales/order_payment')->getCollection()
                ->addFieldToFilter('last_trans_id', $originalTid)
                ->addFieldToSelect('entity_id');
        foreach ($transaction as $transactionId) {
            $entitiyId = $transactionId->getEntityId();
        }
        Mage::getModel('sales/order_payment')->load($entitiyId)
                ->setLastTransId(trim($request['tid']))
                ->save();
        // change the order status
        $orderStatus = $this->getOrderStatus($order);
        $orderState = Mage_Sales_Model_Order::STATE_PROCESSING; //processing: ok; complete: not ok -> would cause the error msg: 'Der Bestellzustand "complete" darf nicht manuell gesetzt werden'
        $order->setState($orderState, true, 'Novalnet callback set state ' . $orderState . ' for Order-ID = ' . $this->orderNo);
        $order->addStatusToHistory($orderStatus, 'Novalnet callback added order status ' . $orderStatus); // this line must be located after $order->setState()
        $order->save();
        // change invoice status to paid
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);
        $invoice->save();
    }

    /**
     * save the payment additional data for the order
     *
     */
    private function saveAdditionalInfo($payment, $data)
    {
        if ($payment && $data) {
            $order = $payment->getOrder();
            $payment->setAdditionalData(serialize($data));
            $order->setPayment($payment)
                    ->save();
        }
    }

    /**
     * Get the order payment status
     *
     * @return Order_status
     */
    private function getOrderStatus($order)
    {
        $payment = $order->getPayment();
        $paymentObj = $payment->getMethodInstance();
        $getresponseData = unserialize($payment->getAdditionalData());
        array_push($this->redirectPayment, Novalnet_Payment_Model_Config::NN_PREPAYMENT, Novalnet_Payment_Model_Config::NN_INVOICE);

        $status = $paymentObj->_getConfigData('order_status', '', $this->storeId);

        if (($paymentObj->getCode() && (in_array($paymentObj->getCode(), $this->redirectPayment)))
                || ($paymentObj->getCode() == Novalnet_Payment_Model_Config::NN_CC
                && $getresponseData['ActiveCc3d'])) {
            $status = $paymentObj->_getConfigData('order_status_after_payment', '', $this->storeId);
        }
        if ($paymentObj->getCode() && $paymentObj->getCode() == Novalnet_Payment_Model_Config::NN_PAYPAL
                && ($this->getAdminTransaction->getStatus() == Novalnet_Payment_Model_Config::PAYPAL_PENDING_CODE)) {
            $status = $paymentObj->_getConfigData('order_status', '', $this->storeId)
                    ? $paymentObj->_getConfigData('order_status', '', $this->storeId)
                    : Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        }
        if (!$status) {
            $status = Mage_Sales_Model_Order::STATE_PROCESSING;
        }
        return $status;
    }

    /**
     * Log partial callback data
     *
     */
    private function doNovalnetCallbackLog($modNovalCallback, $sum)
    {
        $request = $this->request;
        $orgTid = $request['payment_type'] == 'INVOICE_CREDIT' ? trim($request['tid_payment'])
                    : trim($request['tid']);
        $requestUri = Mage::helper('core/http')->getRequestUri();
        $modNovalCallback->setOrderId($this->orderNo)
                ->setCallbackAmount($sum)
                ->setReferenceTid($orgTid)
                ->setCallbackDatetime(date('Y-m-d H:i:s'))
                ->setCallbackLog($requestUri)
                ->save();
    }

    /**
     * Log Affiliate account details
     *
     */
    private function doNovalnetAffAccInfoLog()
    {
        $request = $this->request;
        $affiliateAccInfo = $this->helper->getModelAffiliate();
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
        $this->emailBody = 'Novalnet callback script executed successfully with Novalnet account activation information.';
        $this->callbackMail();
    }

    /**
     * Get order object for specific order id
     *
     * @return payment object
     */
    private function getOrderByIncrementId($incrementId)
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        return $order;
    }

    /**
     * Get the payment method code from order
     *
     * @return string
     */
    private function getPaymentMethod($order)
    {
        return $order->getPayment()->getData('method');
    }

    /**
     * Get parameters GET/POST
     *
     */
    private function getParams()
    {
        if (Mage::app()->getRequest()->getParams()) {
            $data = Mage::app()->getRequest()->getParams();
        } else {
            $data = Mage::app()->getRequest()->getQuery();
        }
        return $data;
    }

    /**
     * Check whether the ip address is authorised
     *
     */
    private function checkIP()
    {
        $callerIp = $this->helper->getRealIpAddr();

        if (!in_array($callerIp, $this->ipAllowed) && !$this->test) {
            echo 'Unauthorised access from the IP [' . $callerIp . ']' . $this->lineBreak . $this->lineBreak;
            exit;
        }
        return true;
    }

    /**
     * add some feature regarding subscription and collection; adapt for your need.
     *
     */
    private function subsCollectionInfo()
    {
        $request = $this->request;

        if (in_array($request['payment_type'], $this->paymentTypes)) { ### Incoming of a payment
            if (isset($request['subs_billing']) && $request['subs_billing'] == 1) { ##IF PAYMENT MADE ON SUBSCRIPTION RENEWAL
                #### Step1: THE SUBSCRIPTION IS RENEWED, PAYMENT IS MADE, SO JUST CREATE A NEW ORDER HERE WITHOUT A PAYMENT PROCESS AND SET THE ORDER STATUS AS PAID ####
                #### Step2: THIS IS OPTIONAL: UPDATE THE BOOKING REFERENCE AT NOVALNET WITH YOUR ORDER_NO BY CALLING NOVALNET GATEWAY, IF U WANT THE USER TO USE ORDER_NO AS PAYMENT REFERENCE ###
                #### Step3: ADJUST THE NEW ORDER CONFIRMATION EMAIL TO INFORM THE USER THAT THIS ORDER IS MADE ON SUBSCRIPTION RENEWAL ###
            }
            if ($request['payment_type'] == 'INVOICE_START') { ##INVOICE START
                if (isset($request['subs_billing']) && $request['subs_billing'] == 1) {
                    #### Step4: ENTER THE NECESSARY REFERENCE & BANK ACCOUNT DETAILS IN THE NEW ORDER CONFIRMATION EMAIL ####
                }
            }
            ### DO THE STEPS TO UPDATE THE STATUS OF THE PAYMENT ###
        } elseif (in_array($request['payment_type'], $this->aryCollection)) { ### Incoming of collection of a payment OR Bank transfer OR invoice OR Advance payment through Customer
            ### DO THE STEPS TO UPDATE THE STATUS OF THE ORDER OR THE USER ###
            if ($request['payment_type'] == 'INVOICE_CREDIT') { #if settlement of Advance payment through Customer
                #### UPDATE THE STATUS OF THE USER ORDER HERE AND NOTE THAT THE ORDER HAS BEEN PAID ####
            }
        } elseif ($request['payment_type'] == 'SUBSCRIPTION_STOP') { ### Cancellation of a Subscription
            ### UPDATE THE STATUS OF THE USER SUBSCRIPTION ###
        }
    }

    /**
     * get order id based on last transaction id.
     *
     */
    private function getOrderIdByTransId()
    {
        $request = $this->request;

        if ((in_array($request['payment_type'], $this->invoiceAllowed)) || (in_array($request['payment_type'], $this->chargeBackPayments))) {
            $orgTid = $request['tid_payment'];
        } else {
            $orgTid = $request['tid'];
        }

        if ($orgTid == '') {
            return false;
        }

        $tablePrefix = Mage::getConfig()->getTablePrefix();
        if (in_array($request['payment_type'], $this->chargeBackPayments)) {
            $orderPayment = $tablePrefix.'sales_payment_transaction';
            $onCondition = "main_table.entity_id = $orderPayment.order_id";
            $orderCollection =  Mage::getModel('sales/order')->getCollection()
                                                             ->addFieldToFilter('txn_id', array('like' => "%$orgTid%"))
                                                             ->addFieldToSelect('increment_id');
        } else {
            $orderPayment = $tablePrefix.'sales_flat_order_payment';
            $onCondition = "main_table.entity_id = $orderPayment.parent_id";
            $orderCollection =  Mage::getModel('sales/order')->getCollection()
                                                             ->addFieldToFilter('last_trans_id', array('like' => "%$orgTid%"))
                                                             ->addFieldToSelect('increment_id');
        }

        $orderCollection->getSelect()->join($orderPayment,$onCondition);
        $count = $orderCollection->count();
        if ($count > 0) {
            foreach ($orderCollection as $order) {
                $orderid = $order->getIncrementId();
            }
        }
        $orderId = (isset($orderid) && $orderid != NULL) ? $orderid : '';

        return $orderId;
    }

}
