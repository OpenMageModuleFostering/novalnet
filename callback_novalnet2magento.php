<?php
/**
 * Novalnet Callback Script for Magento
 *
 * NOTICE
 *
 * This script is used for real time capturing of parameters passed 
 * from Novalnet AG after Payment processing of customers.
 *
 * This script is only free to the use for Merchants of Novalnet AG
 *
 * If you have found this script useful a small recommendation as well
 * as a comment on merchant form would be greatly appreciated.
 *
 * Please contact sales@novalnet.de for enquiry or info
 *
 * ABSTRACT:
 * This script is called from Novalnet, as soon as a payment is finished for
 * payment methods, e.g. Prepayment, Invoice.
 *
 * This script is adapted for those cases where the money for Prepayment / 
 * Invoice has been transferred to Novalnet.
 *
 * An e-mail will be sent if an error occurs.
 *
 * If you also want to handle other payment methods you have to change the logic 
 * accordingly.
 *
 *
 * @category   Novalnet
 * @package    Novalnet
 * @version    1.0
 * @copyright  Copyright (c) 2012 Novalnet AG. (http://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @notice     1. This script must be placed in basic Magento folder
 *                to avoid rewrite rules (mod_rewrite)
 *             2. You have to adapt the value of all the variables
 *                commented with 'adapt ...'
 *             3. Set $test/$debug to false for live system
*/

require_once 'app/Mage.php';
$storeId = Mage_Core_Model_App::ADMIN_STORE_ID;
Mage::app()->setCurrentStore($storeId);
Mage::app('admin');
umask(0);
date_default_timezone_set('Europe/Berlin');

 //Variable Settings
$logFile        = 'novalnet_callback_script_'.date('Y-m-d').'.log';
$log            = false;//false|true; adapt
$debug          = false; //false|true; adapt: set to false for go-live
$test           = false; //false|true; adapt: set to false for go-live
$createInvoice  = true; //false|true; adapt for your need
$useZendEmail   = true;//false|true; adapt for your need
$lineBreak      = empty($_SERVER['HTTP_HOST'])? PHP_EOL: '<br />';
$addSubsequentTidToDb = true;//whether to add the new tid to db; adapt if necessary

$aPaymentTypes = array('INVOICE_CREDIT');//adapt here if needed; Options are:
      /*
        COLLECTION_REVERSAL_AT (7)
        COLLECTION_REVERSAL_DE (7)
        CREDITCARD (1)
        CREDITCARD_BOOKBACK (4)
        CREDITCARD_CHARGEBACK (3)
        CREDITCARD_REPRESENTMENT (5)
        CREDIT_ENTRY_AT (5)
        CREDIT_ENTRY_CREDITCARD (5)
        CREDIT_ENTRY_DE (5)
        DEBT_COLLECTION_AT (6)
        DEBT_COLLECTION_CREDITCARD (6)
        DEBT_COLLECTION_DE (6)
        DIRECT_DEBIT_AT (1)
        DIRECT_DEBIT_DE (1)
        DIRECT_DEBIT_ES (1)
        DIRECT_DEBIT_SEPA (1)
        INVOICE (8)
        INVOICE_CREDIT (2)
        INVOICE_START (2)
        NC_CONVERT (8)
        NC_CREDIT (8)
        NC_DEBIT (8)
        NC_ENCASH (8)
        NC_PAYOUT (8)
        NOVALCARD (8)
        NOVALTEL_DE (1)
        NOVALTEL_DE_CB_REVERSAL (7)
        NOVALTEL_DE_CHARGEBACK (3)
        NOVALTEL_DE_COLLECTION (6)
        ONLINE_TRANSFER (1)
        PAYPAL (1)
        PAYSAFECARD (1)
        REFUND_BY_BANK_TRANSFER_EU (4)
        RETURN_DEBIT_AT (3)
        RETURN_DEBIT_DE (3)
        REVERSAL (8)
        WAP_CREDITCARD (1)
        WAP_DIRECT_DEBIT_AT (1)
        WAP_DIRECT_DEBIT_DE (1)

      NOTES:
       (1) Diese Zahlungsarten bedeuten eine unmittelbare Gutschrift für den Händler (bei Kreditkarte nicht durch uns, sondern durch den Acquirer; bei Paypal durch Paypal selbst).
       (1) These payment types implicate a credit for the merchant. (For credit card payments, this is not done by us, but through the acquirer; PayPal payments are processed directly by PayPal).

       (2) Bei Rechnung/Vorkasse wird zunächst nur der Rechnungsstart (INVOICE_START) eingetragen, die Gutschrift (INVOICE_CREDIT) erfolgt, sobald der Endkunde an uns überwiesen hat
       (2) For Invoice/prepayment, there will only be an Invoice start recorded (INVOICE_START), and the final Credit (INVOICE_CREDIT) will follow as soon as the payment goes through.

       (3) Diese Zahlungsarten bedeuten jeweils eine Rückbelastung, weil der Endkunde der Zahlung widerspricht oder eine Belastung letztlich nicht möglich ist.
       (3)These payment types imply a book back or a charge back, as the end customer has denied making the transaction, or the booking could not take place for a particular reason.

       (4) Diese Zahlungsarten kommen in Frage, wenn der Händler von sich aus dem Endkunden das Geld zurück erstattet.
       (4) These payment types play a role only when the merchant credits the transactional amount back to the end customer.

       (5) Nach einer Rückbelastung begleichen manche Kunden von sich aus die offene Forderung.
       (5) After a chargeback some customers voluntarily pay the open amount.

       (6)Diese Zahlungsarten bedeuten, dass das Geld über Inkasso hereinkommt.
       (6) These payment types imply that the amount has been retrieved by the debt collection department/company.

       (7) Gelegentlich muss aus technischen Gründen eine Rückbelastung oder eine Inkasso-Gutschrift storniert werden.
       (7) Occasionally, due to technical reasons, a chargeback or a debt collection credit needs to be cancelled or aborted.

       (8) Diese Zahlungsarten werden zurzeit noch gar nicht verwendet.
       (8) These payment types are not being used at all.
*/

// Order State/Status Settings
          /*5. Standard Types of Status/States:
               1. pending
               2. processing
               3. holded
               4. complete
               5. canceled
          */
$orderState  = Mage_Sales_Model_Order::STATE_PROCESSING; //Note: Mage_Sales_Model_Order::STATE_COMPLETE => NOK, Refer to function setOrderStatus()
$orderStatus = Mage_Sales_Model_Order::STATE_PROCESSING;//adapt for your need
$orderComment = $lineBreak.date('d.m.Y H:i:s').': Novalnet callback script changed order state to '.$orderState.' and order status to '. $orderStatus;

//Security Setting; only this IP is allowed for call back script
$ipAllowed = '195.143.189.210'; //Novalnet IP, is a fixed value, DO NOT CHANGE!!!!!
//$ipAllowed = '182.72.184.185'; //Novalnet IP, is a fixed value, DO NOT CHANGE!!!!!

//Reporting Email Addresses Settings
$shopInfo      = 'Magento '.$lineBreak; //manditory;adapt for your need
$mailHost      = Mage::getStoreConfig('system/smtp/host');//adapt or Mage::getStoreConfig('system/smtp/host')
$mailPort      = Mage::getStoreConfig('system/smtp/port');//adapt or Mage::getStoreConfig('system/smtp/port')
$emailFromAddr = '';//sender email addr., manditory, adapt it
$emailToAddr   = '';//recipient email addr., manditory, adapt it
$emailSubject  = 'Novalnet Callback Script Access Report'; //adapt if necessary; 
$emailBody     = 'Novalnet Callback Script Access Report.';//Email text's 1. line, can be let blank, adapt for your need
$emailFromName = ""; // Sender name, adapt
$emailToName   = ""; // Recipient name, adapt

//Parameters Settings
$hParamsRequired = array(
  'vendor_id'    => '',
  'tid'          => '',
  'payment_type' => '',
  'status'       => '',
  'amount'       => '',
  'order_no'     => '');

$hParamsTest = array(
  'vendor_id'    => '4',
  'status'       => '100',
  'amount'       => '15500',//must be avail. in shop database; 850 = 8.50
  'payment_type' => 'INVOICE_CREDIT',
  'tid'          => '12345678901234567',//subsequent tid, from Novalnet backend; can be a fake for test
  'order_no'	 => '200000008',	// Order number 
  );

if (in_array('INVOICE_CREDIT', $aPaymentTypes) and isset($_REQUEST['payment_type']) and $_REQUEST['payment_type'] == 'INVOICE_CREDIT'){
  $hParamsRequired['tid_payment'] = '';
  $hParamsTest['tid_payment'] = '12497500001209615'; //orig. tid; must be avail. in shop database; adapt for test;
}
ksort($hParamsRequired);
ksort($hParamsTest);

//Test Data Settings
if ($test){
  $_REQUEST      = $hParamsTest;
  $emailFromName = "Novalnet test"; // Sender name, adapt
  $emailToName   = "Novalnet test"; // Recipient name, adapt
  $emailFromAddr = 'test@novalnet.de';//manditory for test; adapt
  $emailToAddr   = 'test@novalnet.de';//manditory for test; adapt
  $emailSubject  = $emailSubject.' - TEST';//adapt
}

// ################### Main Prog. ##########################
try {
  //Check Params
  if (checkIP($_REQUEST)){
    if (checkParams($_REQUEST)){
      //Get Order ID and Set New Order Status
      if ($ordercheckstatus = BasicValidation($_REQUEST)){
        $orderNo = $_REQUEST['order_no']? $_REQUEST['order_no']: $_REQUEST['order_id'];
        setOrderStatus($orderNo);//and send error mails if any
      }
    }
  }

  if ($log) {
    Mage::log('Ein Haendlerskript-Aufruf fand statt mit StoreId '.$storeId." und Parametern:$lineBreak".print_r($_POST, true), null, $logFile);
    //exit;
  }

  if (!$emailBody){
    $emailBody .= 'Novalnet Callback Script called for StoreId '.$storeId." and Parameters: ".print_r($_POST, true).$lineBreak;
    $emailBody .= 'Novalnet callback succ. '.$lineBreak;
    $emailBody .= 'Params: '.print_r($_REQUEST, true).$lineBreak;
  }
}catch(Exception $e){
  //Mage::logException($e);
  $emailBody .= "Exception catched: $lineBreak\$e:".$e->getMessage().$lineBreak;
}

if ($emailBody){
  if (!sendMail($emailBody)){
    if ($debug){
      echo "Mailing failed!".$lineBreak;
      echo "This mail text should be sent: ".$lineBreak;
      echo $emailBody;
    }
  }
}

// ############## Sub Routines #####################
function sendMail($emailBody){
  global $lineBreak, $debug, $test, $emailFromAddr, $emailToAddr, $emailFromName, $emailToName, $emailSubject, $storeId, $shopInfo, $useZendEmail;
  if ($useZendEmail){
    if (!sendEmailZend($emailBody)){
      return false;
    }
  }else{
    if (!sendEmailMagento($emailBody)){
      return false;
    }
  }

  if ($debug){
    echo 'This text has been sent:'.$lineBreak.$emailBody;
  }
  return true;
}
function sendEmailMagento($emailBody){
  global $lineBreak, $debug, $test, $emailFromAddr, $emailToAddr, $emailFromName, $emailToName, $emailSubject, $storeId, $shopInfo, $mailHost, $mailPort;
  $emailBodyT = str_replace('<br />', PHP_EOL, $emailBody);

  /*
   * Loads the html file named 'novalnet_callback_email.html' from
   * E.G: app/locale/en_US/template/email/novalnet/novalnet_callback_email.html
   * OR:  app/locale/YourLanguage/template/email/novalnet/novalnet_callback_email.html
   * Adapt the corresponding template if necessary
   */
  $emailTemplate = Mage::getModel('core/email_template')
                    ->loadDefault('novalnet_callback_email_template');
  //echo 'hh: <pre>'; print_r($emailTemplate); echo '<hr />'; //exit;

  //Define some variables to assign to template
  $emailTemplateVariables = array();
  $emailTemplateVariables['fromName']  = $emailFromName;
  $emailTemplateVariables['fromEmail'] = $emailFromAddr;
  $emailTemplateVariables['toName']    = $emailToName;
  $emailTemplateVariables['toEmail']   = $emailToAddr;
  $emailTemplateVariables['subject']   = $emailSubject;
  $emailTemplateVariables['body']      = $emailBodyT;
  $processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplateVariables, true);
  //echo 'hh: <pre>'; print_r($processedTemplate); echo '<hr />'; exit;

  //Send Email
  ini_set('SMTP', $mailHost);
  ini_set('smtp_port', $mailPort);

  try{
    if ($debug){
      echo __FUNCTION__.': Sending Email suceeded!'.$lineBreak;
    }
    $emailTemplate->send($emailTo, $emailToName, $emailTemplateVariables);
  }
  catch(Exception $e) {
    //Mage::logException($e);
    Mage::getSingleton('core/session')
        ->addError(Mage::helper('novalnet')
        ->__('Unable to send email'));
    if ($debug) {echo 'Email sending failed: '.$e->getMessage();}
    #Mage::throwException('Email sending failed, reason:'.$lineBreak).$e->getMessage();
    return false;
  }
  return true;
}
function sendEmailZend($emailBody){
  global $lineBreak, $debug, $test, $emailFromAddr, $emailToAddr, $emailFromName, $emailToName, $emailSubject, $storeId, $shopInfo, $mailHost, $mailPort;
  $emailBodyT = str_replace('<br />', PHP_EOL, $emailBody);
  ini_set('SMTP', $mailHost);
  ini_set('smtp_port', $mailPort);

  $mail = new Zend_Mail();
  $mail->setBodyText($emailBodyT);//$mail->setBodyHTML($emailBodyT);
  $mail->setFrom($emailFromAddr, $emailFromName);
  $mail->addTo($emailToAddr, $emailToName);
  $mail->setSubject($emailSubject);

  try {
    $mail->send();
    if ($debug){
      echo __FUNCTION__.': Sending Email suceeded!'.$lineBreak;
    }
  }
  catch(Exception $e) {
    //Mage::logException($e);
    Mage::getSingleton('core/session')
        ->addError(Mage::helper('novalnet')
        ->__('Unable to send email'));
    if ($debug) {echo 'Email sending failed: '.$e->getMessage();}
    //Mage::throwException('Email sending failed, reason:'.$lineBreak).$e->getMessage();
    return false;
  }
  return true;
}
function showDebug(){
  global $debug, $emailBody;
  if($debug) {
    echo $emailBody;
  }
}
function checkParams($_request){
  global $lineBreak, $hParamsRequired, $emailBody, $aPaymentTypes;
  $error = false;
  $emailBody = '';

  if(!$_request){
    $emailBody .= 'No params passed over!'.$lineBreak;
    return false;
  }
  if (!isset($_request['payment_type'])){
    $emailBody .= "Novalnet callback received. But Param payment_type missing$lineBreak";
		return false;
  }

  if (!in_array($_request['payment_type'], $aPaymentTypes)){
    $emailBody .= "Novalnet callback received. But passed payment_type (".$_request['payment_type'].") not defined in \$aPaymentTypes: (".implode('; ', $aPaymentTypes).")$lineBreak";
    return false;
  }

  if($hParamsRequired){
    foreach ($hParamsRequired as $k=>$v){
      if (!isset($_request[$k])){
        $error = true;
        $emailBody .= 'Required param ('.$k.') missing!'.$lineBreak;
      }
    }
    if ($error){
      return false;
    }
  }
  if(!isset($_request['status']) or 100 != $_request['status']) {
    $emailBody .= 'The status codes [' . $_request['status'] . '] is not valid: Only 100 is allowed.' . "$lineBreak$lineBreak".$lineBreak;
    return false;
  }
  return true;
}
function BasicValidation($_request){
  global $lineBreak, $tableOrderPayment, $tableOrder, $emailBody, $debug;
  $orderDetails = array();
  $orderNo      = $_request['order_no']? $_request['order_no']: $_request['order_id'];
  $order = getOrderByIncrementId($orderNo);
  if ($debug) {echo'Order Details:<pre>'; print_r($order);echo'</pre>';}

  //check amount
  $amount  = $_request['amount'];
  $_amount = isset($order['base_grand_total']) ? $order['base_grand_total'] * 100 : 0;

  if(!$_amount || (intval("$_amount") != intval("$amount"))) {
    $emailBody .= "The order amount ($_amount) does not match with the request amount ($amount)$lineBreak$lineBreak";
    return false;
  }

  #$order = getOrderByIncrementId($orderDetails['increment_id']);
  $paymentType = getPaymentMethod($order);
  if(!in_array($paymentType, array('novalnetPrepayment', 'novalnetInvoice'))) {
    $emailBody .= "The order payment type ($paymentType) is not Prepayment/Invoice!$lineBreak$lineBreak";
    return false;
  }
  return true;// == true
}
function setOrderStatus ($incrementId) {
  global $lineBreak, $createInvoice, $emailBody, $orderStatus, $orderState, $tableOrderPayment, $addSubsequentTidToDb;
  //echo "$orderStatus, $orderState"; exit;
  if ($order = getOrderByIncrementId($incrementId)) {
    $order->getPayment()->getMethodInstance()->setCanCapture(true);

    if ($createInvoice){
      saveInvoice($order);
    }

    if ($invoice = $order->getInvoiceCollection()->getFirstItem()) {
      $order->setState($orderState, true, 'Novalnet callback set state '.$orderState.' for Order-ID = ' . $incrementId); //processing: ok; complete: not ok -> would cause the error msg: 'Der Bestellzustand "complete" darf nicht manuell gesetzt werden'
      $order->addStatusToHistory($orderStatus, 'Novalnet callback added order status '. $orderStatus);// this line must be located after $order->setState()
      $emailBody .= 'Novalnet callback set state to '.$orderState.$lineBreak;
      $emailBody .= 'Novalnet callback set status to '.$orderStatus.' ... '.$lineBreak;
      $order->save();

      //Add subsequent TID to DB column last_trans_id
      if ($addSubsequentTidToDb){
	  
		$payment = $order->getPayment();
		$payment->setLastTransId($_REQUEST['tid_payment'] . '. Novalnet Callback Script executed successfully. The subsequent TID: (' . $_REQUEST['tid'] . ') on ' . date('Y-m-d H:i:s') . '');
		$order->setPayment($payment)
			   ->save();

      }
    } else {
      $emailBody .= "Novalnet Callback: No invoice for order (".$order->getId().") found";
      return false;
    }
  } else {
    $emailBody .= "Novalnet Callback: No order for Increment-ID $incrementId found.";
    return false;
  }
  $emailBody .= "succeeded.";
  return true;
}
function getOrderByIncrementId($incrementId) {
  $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
  return $order;
}
function getPaymentMethod ($order) {
  return $order->getPayment()->getData('method');
}
function checkIP($_REQUEST){
  global $lineBreak, $ipAllowed, $test, $emailBody;
  $callerIp  = Mage::helper('novalnet')->getRealIpAddr();
  if ($test){
      $ipAllowed = '127.0.0.1';
      if ($callerIp == '::1'){//IPv6 Issue
        $callerIp = '127.0.0.1';
      }
    }

  if($ipAllowed != $callerIp) {
    $emailBody .= 'Unauthorised access from the IP [' . $callerIp . ']' .$lineBreak.$lineBreak;
    $emailBody .= 'Request Params: ' . print_r($_REQUEST, true);
    return false;
  }
  return true;
}

function saveInvoice (Mage_Sales_Model_Order $order) {
  global $lineBreak, $emailBody;
  if ($order->canInvoice()) {
    $invoice = $order->prepareInvoice();
    $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)
            ->register();
    Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder())
            ->save();
    //$invoice->sendEmail(true, '');//this would send orig. order details to customer again
	$emailBody .= 'Novalnet Callback Script executed successfully'.$lineBreak;	
    $emailBody .= 'Payment for order id: '.$_REQUEST['order_no']." received".$lineBreak;
	$emailBody .= 'New TID:'.$_REQUEST['tid'].$lineBreak;
    $emailBody .= "Invoice created".$lineBreak;
	echo $emailBody;
  }else{
    $emailBody .= "Invoice Already Exists !!!".$lineBreak;
    return false;
  }
  return true;
}
?>
