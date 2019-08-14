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
$createInvoice  = true; //false|true; adapt
$useZendEmail   = true;//false|true; adapt
$lineBreak      = empty($_SERVER['HTTP_HOST'])? PHP_EOL: '<br />';
$addSubsequentTidToDb = true;//whether to add the new tid to db; adapt if necessary

$aPaymentTypes = array('INVOICE_CREDIT');//adapt here if needed; Options are:
      /*
        COLLECTION_REVERSAL_AT
        COLLECTION_REVERSAL_DE
        CREDITCARD
        CREDITCARD_BOOKBACK
        CREDITCARD_CHARGEBACK
        CREDITCARD_REPRESENTMENT
        CREDIT_ENTRY_AT
        CREDIT_ENTRY_CREDITCARD
        CREDIT_ENTRY_DE
        DEBT_COLLECTION_AT
        DEBT_COLLECTION_CREDITCARD
        DEBT_COLLECTION_DE
        DIRECT_DEBIT_AT
        DIRECT_DEBIT_DE
        DIRECT_DEBIT_ES
        DIRECT_DEBIT_SEPA
        INVOICE
        INVOICE_CREDIT
        INVOICE_START
        NC_CONVERT
        NC_CREDIT
        NC_DEBIT
        NC_ENCASH
        NC_PAYOUT
        NOVALCARD
        NOVALTEL_DE
        NOVALTEL_DE_CB_REVERSAL
        NOVALTEL_DE_CHARGEBACK
        NOVALTEL_DE_COLLECTION
        ONLINE_TRANSFER
        PAYPAL
        PAYSAFECARD
        REFUND_BY_BANK_TRANSFER_EU
        RETURN_DEBIT_AT
        RETURN_DEBIT_DE
        REVERSAL
        WAP_CREDITCARD
        WAP_DIRECT_DEBIT_AT
        WAP_DIRECT_DEBIT_DE
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
$orderStatus = Mage_Sales_Model_Order::STATE_COMPLETE;//adapt for your need
$orderComment = $lineBreak.date('d.m.Y H:i:s').': Novalnet callback script changed order state to '.$orderState.' and order status to '. $orderStatus;

//Security Setting; only this IP is allowed for call back script
$ipAllowed = '195.143.189.210'; //Novalnet IP, is a fixed value, DO NOT CHANGE!!!!!

//Reporting Email Addresses Settings
$shopInfo      = 'Magento '.$lineBreak; //adapt for your need
$mailHost      = Mage::getStoreConfig('system/smtp/host');//'mail.novalnet.de';//adapt or Mage::getStoreConfig('system/smtp/host')
$mailPort      = Mage::getStoreConfig('system/smtp/port');//25;//adapt or Mage::getStoreConfig('system/smtp/port')
$emailFromAddr = 'test@novalnet.de';//sender email addr., adapt
$emailToAddr   = 'test@novalnet.de';//recipient email addr., adapt
$emailSubject  = 'Novalnet Callback Script Access Report'; //adapt if necessary; 
$emailBody     = 'Novalnet Callback Script Access Report.';//Email text, adapt
$emailFromName = "Magento Onlineshop"; // Sender name, adapt
$emailToName   = "test@novalnet.de"; // Recipient name, adapt

//Parameters Settings
$hParamsRequired = array(
  'vendor_id'    => '',
  'tid'          => '',
  'payment_type' => '',
  'status'       => '',
  'amount'       => '',
  'order_no'     => '');

if (in_array('INVOICE_CREDIT', $aPaymentTypes)){
  $hParamsRequired['tid_payment'] = '';
}

$hParamsTest = array(
  'vendor_id'    => '4',
  'status'       => '100',
  'amount'       => '15500',//must be avail. in shop database; 850 = 8.50
  'payment_type' => 'INVOICE_CREDIT',
  'tid'          => '12345678901234567',//subsequent tid, from Novalnet backend; can be a fake for test
  'order_no'	 => '200000008',	// Order number 
  );

if (in_array('INVOICE_CREDIT', $aPaymentTypes)){
  $hParamsTest['tid_payment'] = '12497500001209615'; //orig. tid; must be avail. in shop database; adapt for test;
}

//Test Data Settings
if ($test){
  $_REQUEST      = $hParamsTest;
  $emailFromName = "Novalnet test"; // Sender name, adapt
  $emailToName   = "Novalnet test"; // Recipient name, adapt
  $emailFromAddr = 'test@novalnet.de';//adapt
  $emailToAddr   = 'test@novalnet.de';//adapt
  $emailSubject  = $emailSubject.' - TEST';//adapt
}

// ################### Main Prog. ##########################
try {
  //Check Params
  if (checkIP($_REQUEST)){
    if (checkPaymentTypeAndStatus($_REQUEST['payment_type'], $_REQUEST['status'])){
      if (checkParams($_REQUEST)){
        //Get Order ID and Set New Order Status
        if ($ordercheckstatus = BasicValidation($_REQUEST)){
          setOrderStatus($_REQUEST['order_no']);//and send error mails if any
        }
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

  try {
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
function checkPaymentTypeAndStatus($paymentType, $status){
  global $emailBody, $aPaymentTypes;
  if (empty($paymentType)){
    $emailBody .= "Novalnet callback received. But Param payment_type missing $lineBreak";
		return false;
  }

  if (!in_array($paymentType, $aPaymentTypes)){
    $emailBody .= "Novalnet callback received. But passed payment_type ($paymentType) not defined in \$aPaymentTypes: (".implode('; ', $aPaymentTypes).")$lineBreak";
    return false;
  }

  if(empty($status) or 100 != $status) {
		$emailBody .= 'The status codes [' . $_request['status'] . '] is not valid: Only 100 is allowed.' . "$lineBreak$lineBreak".$lineBreak;
    return false;
	}
  return true;
}
function checkParams($_request){
  global $lineBreak, $hParamsRequired, $emailBody;
  $error = false;
  $emailBody = '';

  if(!$_request){
    $emailBody .= 'No params passed over!'.$lineBreak;
    return false;
  }elseif($hParamsRequired){
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
  return true;
}
function BasicValidation($_request){
  global $lineBreak, $tableOrderPayment, $tableOrder, $emailBody, $debug;
  $orderDetails = array();
  $orderDetails = getOrderByIncrementId($_request['order_no']);
  if ($debug) {echo'Order Details:<pre>'; print_r($orderDetails);echo'</pre>';}

  //check amount
  $amount  = $_request['amount'];
  $_amount = isset($orderDetails['base_grand_total']) ? $orderDetails['base_grand_total'] * 100 : 0;

  if(!$_amount || (intval("$_amount") != intval("$amount"))) {
    $emailBody .= "The order amount ($_amount) does not match with the request amount ($amount)$lineBreak$lineBreak";
    return false;
  }

  $order = getOrderByIncrementId($orderDetails['increment_id']);
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
      $emailBody .= 'Novalnet callback set state to '.$orderState.' ... ';
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
