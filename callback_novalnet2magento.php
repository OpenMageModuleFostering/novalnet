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
 * @package    Novalnet_Payment
 * @copyright  Copyright (c) Novalnet AG. (https://www.novalnet.de)
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
$test           = false;//false|true; adapt: set to false for go-live
$createInvoice  = true; //false|true; adapt for your need
$useZendEmail   = true;//false|true; adapt for your need
$lineBreak      = empty($_SERVER['HTTP_HOST'])? PHP_EOL: '<br />';
$addSubsequentTidToDb = true;//whether to add the new tid to db; adapt if necessary

$allowedPayment = array('novalnetcc'=>array('CREDITCARD', 'CREDITCARD_BOOKBACK'), 'novalnetsecure'=>array('CREDITCARD', 'CREDITCARD_BOOKBACK'), 'novalnetelvaustria'=>array('DIRECT_DEBIT_AT'), 'novalnetelvgerman'=>array('DIRECT_DEBIT_DE'), 'novalnetinvoice'=>array('INVOICE_CREDIT'), 'novalnetprepayment'=>array('INVOICE_CREDIT'), 'novalnetideal'=>array('IDEAL'), 'novalnetpaypal'=>array('PAYPAL'), 'novalnetsofortueberweisung'=>array('ONLINE_TRANSFER'));
$invoiceAllowed = array('INVOICE_CREDIT');
$paypalAllowed = array('PAYPAL');
$aPaymentTypes = array('CREDITCARD', 'CREDITCARD_BOOKBACK', 'DIRECT_DEBIT_AT', 'DIRECT_DEBIT_DE', 'DIRECT_DEBIT_ES', 'DIRECT_DEBIT_SEPA', 'ONLINE_TRANSFER', 'INVOICE_CREDIT', 'PAYPAL', 'REFUND_BY_BANK_TRANSFER_EU', 'IDEAL');//adapt here if needed;

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
//$ipAllowed = '127.0.0.1';

//Reporting Email Addresses Settings
$shopInfo      = 'Magento '.$lineBreak; //manditory;adapt for your need
$mailHost      = Mage::getStoreConfig('system/smtp/host');//adapt or Mage::getStoreConfig('system/smtp/host')
$mailPort      = Mage::getStoreConfig('system/smtp/port');//adapt or Mage::getStoreConfig('system/smtp/port')
$emailFromAddr = '';//sender email addr., manditory, adapt it
$emailToAddr   = '';//recipient email addr., manditory, adapt it
$emailSubject  = 'Novalnet Callback Script Access Report'; //adapt if necessary;
$emailBody     = 'Novalnet Callback Script Access Report : '.$lineBreak;//Email text's 1. line, can be let blank, adapt for your need
$emailFromName = ""; // Sender name, adapt
$emailToName   = ""; // Recipient name, adapt
$callBackExecuted = false;		
//Parameters Settings
$hParamsRequired = array(
  'vendor_id'    => '',
  'tid'          => '',
  'payment_type' => '',
  'status'       => '',
  'amount'       => '',
  'order_no'     => '',
  'tid_payment'  => '',
  'tid'          => '');

if(!in_array($_REQUEST['payment_type'],$invoiceAllowed)) {
    unset($hParamsRequired['tid_payment']);
}

ksort($hParamsRequired);

// ################### Main Prog. ##########################
try {

    //Check Params
    if (checkIP($_REQUEST)){
	  
		$response = $_REQUEST;
	  	$orderNo  = $response['order_no'] ? $response['order_no'] : $response['order_id'];
	    if(empty($response['payment_type'])) {
			$emailBody .= "Required param (payment_type) missing!";
		} elseif(empty($orderNo)) {
			$emailBody .= "Order no is missing !".$lineBreak;
		} elseif(!empty($response['payment_type']) and in_array(strtoupper($response['payment_type']), $aPaymentTypes)) {
			//Complete the order incase response failure from novalnet server
			$order = getOrderByIncrementId($orderNo);
			if($order->getIncrementId()) {
				$payment = $order->getPayment();
				$paymentObj = $payment->getMethodInstance();
				$storeId = $order->getStoreId();
				$paymentObj->_vendorId = $paymentObj->_getConfigData('merchant_id', true, $storeId);
				$paymentObj->_authcode = $paymentObj->_getConfigData('auth_code', true, $storeId);			
				// Get Admin Transaction status via API
				$getAdminTransaction = $paymentObj->doNovalnetStatusCall($response['tid'], $storeId);
				// if ($debug) {echo'Order Details:<pre>'; print_r($order);echo'</pre>';}
				$helper = Mage::helper('novalnet_payment');
				$checkTidExist = $payment->getLastTransId();
				if(!empty($orderNo) && $order->getIncrementId() == $orderNo && empty($checkTidExist)) {
					
					 //Unhold an order:-
					if ($order->canUnhold()) {
						$order->unhold()->save();
					}
					
					$serverResponse = $response['test_mode'];   
					$shopMode = $paymentObj->_getConfigData('live_mode', '', $storeId);
					$testMode = (((isset($serverResponse) && $serverResponse == 1) || (isset($shopMode) && $shopMode == 0)) ? 1 : 0 );
					$data = array('NnTestOrder' => $testMode);
					$txnId = $response['tid'];
					$transMode = ($paymentObj->getCode() == Novalnet_Payment_Model_Config::NN_CC3D) ? false : true;
					$payment->setStatus(Novalnet_Payment_Model_Payment_Method_Abstract::STATUS_SUCCESS)
							->setStatusDescription(Mage::helper('novalnet_payment')->__('Payment was successful.'))
							->setAdditionalData(serialize($data))
							->setIsTransactionClosed($transMode)
							->save();			
					doTransactionStatusSave($response, $getAdminTransaction, $orderNo); // Save the Transaction status				
					if ($getAdminTransaction->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
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
								->authorize(true, Mage::helper('novalnet_payment')->getFormatedAmount($response['amount'], 'RAW'))
								->save();
					}
					
					$setOrderAfterStatus = $paymentObj->_getConfigData('order_status_after_payment') ? $paymentObj->_getConfigData('order_status_after_payment') : Mage_Sales_Model_Order::STATE_PROCESSING; // If after status is empty set default status
										$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $setOrderAfterStatus, $helper->__('Customer successfully returned from Novalnet'), true
										)->save();
				   $callBackExecuted = true;			
				   doTransactionOrderLog($response, $orderNo);
		 
					//Add subsequent TID to DB column last_trans_id
				/*	if ($addSubsequentTidToDb){
					$payment = $order->getPayment();
					$data = unserialize($payment->getAdditionalData());
					if(in_array($_REQUEST['payment_type'],$paypalAllowed)) {
						$script = 'Novalnet Callback Script executed successfully on ' . date('Y-m-d H:i:s');
					} else {
						$script = 'Novalnet Callback Script executed successfully. The subsequent TID: (' . $_REQUEST['tid'] . ') on ' . date('Y-m-d H:i:s');
					}
					$arr = array('NnComments'=>$script);
					$payment->setAdditionalData(serialize(array_merge($data,$arr)));
					$order->setPayment($payment)
						   ->save();
					}
					if(in_array($response['payment_type'],$paypalAllowed)) {
						$emailBody .= "Novalnet Callback Script executed successfully. Payment for order id :" . $orderNo . ' on ' . date('Y-m-d H:i:s').$lineBreak;
					} else {
						$emailBody .= "Novalnet Callback Script executed successfully. Payment for order id :" . $orderNo . '. New TID: ('. $txnId . ') on ' . date('Y-m-d H:i:s').$lineBreak;
					}	*/		
					
					//sendNewOrderEmail
					if (!$order->getEmailSent() && $order->getId()) {
						try {
							$order->sendNewOrderEmail()
									->setEmailSent(true)
									->save();
						} catch (Exception $e) {
							Mage::throwException(Mage::helper('novalnet_payment')->__('Cannot send new order email.'));
						}
					}
					$order->save();
				} 
				//	if (!empty($response['payment_type']) and in_array(strtoupper($response['payment_type']),$aPaymentTypes)) {	  
				if (checkParams($_REQUEST)){
				  //Get Order ID and Set New Order Status
				  if ($ordercheckstatus = BasicValidation($_REQUEST)){
					$orderNo = $_REQUEST['order_no']? $_REQUEST['order_no']: $_REQUEST['order_id'];
					if ($getAdminTransaction->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {	  
						setOrderStatus($orderNo);//and send error mails if any
					}
				  }
				}
			} else {
				$emailBody .= "Order no [".$orderNo."] is not valid! $lineBreak";
			}
		} else {
			$emailBody .= "Payment type [".$response['payment_type']."] is mismatched! $lineBreak";
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
    }
  }
  if ($debug){
	echo $emailBody;
  }
}

// ############## Sub Routines #####################
function sendMail($emailBody){
  global $lineBreak, $debug, $emailFromAddr, $emailToAddr, $emailFromName, $emailToName, $emailSubject, $storeId, $shopInfo, $useZendEmail;
  if ($useZendEmail){
    if (!sendEmailZend($emailBody)){
      return false;
    }
  }else{
    if (!sendEmailMagento($emailBody)){
      return false;
    }
  }

  /*if ($debug){
    echo 'This text has been sent:'.$lineBreak.$emailBody;
  }*/
  return true;
}
function sendEmailMagento($emailBody){
  global $lineBreak, $debug, $emailFromAddr, $emailToAddr, $emailFromName, $emailToName, $emailSubject, $storeId, $shopInfo, $mailHost, $mailPort;
  $emailBodyT = str_replace('<br />', PHP_EOL, $emailBody);

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
        ->addError(Mage::helper('novalnet_payment')
        ->__('Unable to send email'));
    if ($debug) {echo 'Email sending failed: '.$e->getMessage();}
    #Mage::throwException('Email sending failed, reason:'.$lineBreak).$e->getMessage();
    return false;
  }
  return true;
}
function sendEmailZend($emailBody){
  global $lineBreak, $debug, $emailFromAddr, $emailToAddr, $emailFromName, $emailToName, $emailSubject, $storeId, $shopInfo, $mailHost, $mailPort;
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

  global $lineBreak, $hParamsRequired, $emailBody, $aPaymentTypes, $paypalAllowed, $invoiceAllowed;
  $error = false;
 // $emailBody = '';

  if(!$_request){
    $emailBody .= 'Novalnet callback received. No params passed over!'.$lineBreak;
    return false;
  }

  if($hParamsRequired){
    foreach ($hParamsRequired as $k=>$v){
      if (!isset($_request[$k]) || empty($_request[$k])){
        $error = true;
        $emailBody .= 'Required param ('.$k.') missing!'.$lineBreak;
      }
    }
    if ($error){
      return false;
    }
  }

  if(in_array($_REQUEST['payment_type'],$invoiceAllowed)) {
    if(strlen($_request['tid_payment']) != 17) {
    	$emailBody .= 'Novalnet callback received. Invalid TID ['.$_request['tid_payment'].'] for Order:'.$_request['order_no'].' ' . "$lineBreak$lineBreak".$lineBreak;
    	return false;
    }
  }

  if(strlen($_request['tid']) != 17){
	if(in_array($_REQUEST['payment_type'],$invoiceAllowed)) {
	    $emailBody .= 'Novalnet callback received. New TID is not valid.' . "$lineBreak$lineBreak".$lineBreak;
	} else {
		$emailBody .= 'Novalnet callback received. Invalid TID ['.$_request['tid'].'] for Order:'.$_request['order_no'].' ' . "$lineBreak$lineBreak".$lineBreak;	    		
	}
	return false;
  }

	if (!empty($_request['status']) and 100 != $_request['status']) {
		$emailBody .= 'Novalnet callback received. Status [' . $_request['status'] . '] is not valid: Only 100 is allowed.' . "$lineBreak$lineBreak" . $lineBreak;
		return false;
	}
  return true;
}
function BasicValidation($_request){
  global $lineBreak, $tableOrderPayment, $tableOrder, $emailBody, $debug, $allowedPayment, $invoiceAllowed, $paypalAllowed;;
  $orderDetails = array();
  $orderNo      = $_request['order_no']? $_request['order_no']: $_request['order_id'];
  $order = getOrderByIncrementId($orderNo);
  // if ($debug) {echo'Order Details:<pre>'; print_r($order);echo'</pre>';}
  if($order->getIncrementId() == $orderNo && !empty($orderNo)) {
	  //check amount
	  $amount  = $_request['amount'];
	  $_amount = isset($order['base_grand_total']) ? $order['base_grand_total'] * 100 : 0;
	  if(!$amount || intval($amount) < 0) {
		$emailBody .= "Novalnet callback received. The requested amount ($amount) must be greater than zero.$lineBreak$lineBreak";
		return false;
	  }
	  $OrderPaymentName = strtolower(getPaymentMethod($order));
	  #modified
	  if(in_array($_REQUEST['payment_type'],$invoiceAllowed)) {
		$org_tid = $_request['tid_payment'];	
	  } else {  
		$org_tid = $_request['tid'];
	  }

	  $paymentType = $allowedPayment[$OrderPaymentName];
	  //  if($paymentType != $_request['payment_type']) {
	  if(!in_array($_request['payment_type'], $paymentType)) {
			$emailBody .= "Novalnet callback received. Payment type (".$_request['payment_type'].") is not matched with $OrderPaymentName!$lineBreak$lineBreak";
			return false;	  
	  }
	  
	/*  if(($paymentType == 'novalnetpaypal' && !in_array($_request['payment_type'], $paypalAllowed))
			|| ( in_array($paymentType, array('novalnetinvoice', 'novalnetprepayment')) && !in_array($_request['payment_type'], $invoiceAllowed) ) ) {
			$emailBody .= "Novalnet callback received. Payment type (".$_request['payment_type'].") is not matched with $paymentType!$lineBreak$lineBreak";
			return false;
	  } */

	  if(!preg_match('/^'.$org_tid.'/i',$order->getPayment()->getLastTransId())){
		$emailBody .= 'Novalnet callback received. Order no is not valid' . "$lineBreak$lineBreak".$lineBreak;
		return false;
	  }

	  return true;// == true
  } else {
	$emailBody .='Novalnet callback received. Order no is not valid'. $lineBreak;
	return false;
  }
}
function setOrderStatus ($incrementId) {
  global $lineBreak, $createInvoice, $emailBody, $orderStatus, $orderState, $tableOrderPayment, $addSubsequentTidToDb, $paypalAllowed, $invoiceAllowed;
  if ($order = getOrderByIncrementId($incrementId)) {
    $order->getPayment()->getMethodInstance()->setCanCapture(true);

    if ($createInvoice){
      $saveinvoice = saveInvoice($order);
    }
    if ($invoice = $order->getInvoiceCollection()->getFirstItem()) {
		if ($saveinvoice) {
		  $order->setState($orderState, true, 'Novalnet callback set state '.$orderState.' for Order-ID = ' . $incrementId); //processing: ok; complete: not ok -> would cause the error msg: 'Der Bestellzustand "complete" darf nicht manuell gesetzt werden'
		  $order->addStatusToHistory($orderStatus, 'Novalnet callback added order status '. $orderStatus);// this line must be located after $order->setState()
		  $emailBody .= 'Novalnet callback set state to '.$orderState.$lineBreak;
		  $emailBody .= 'Novalnet callback set status to '.$orderStatus.' ... '.$lineBreak;
		  $order->save();

		  //Add subsequent TID to DB column last_trans_id
		  if ($addSubsequentTidToDb){
			$payment = $order->getPayment();
			$data = unserialize($payment->getAdditionalData());
			if(in_array($_REQUEST['payment_type'],$invoiceAllowed)) {			    
				$script = 'Novalnet Callback Script executed successfully. The subsequent TID: (' . $_REQUEST['tid'] . ') on ' . date('Y-m-d H:i:s');
			} else {
			    $script = 'Novalnet Callback Script executed successfully on ' . date('Y-m-d H:i:s');
			}
			$arr = array('NnComments'=>$script);
			$payment->setAdditionalData(serialize(array_merge($data,$arr)));
			$order->setPayment($payment)
				   ->save();
		  }
	    }
    } else {
      $emailBody .= "Novalnet Callback: No invoice for order (".$order->getId().") found";
      return false;
    }
  } else {
    $emailBody .= "Novalnet Callback: No order for Increment-ID $incrementId found.";
    return false;
  }
  return true;
}
function getOrderByIncrementId($incrementId) {
  $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
  return $order;
}
function getPaymentMethod ($order) {
  return $order->getPayment()->getData('method');
}
function checkIP($_request){
  global $lineBreak, $ipAllowed, $test, $emailBody;
  $callerIp  = Mage::helper('novalnet_payment')->getRealIpAddr();
  if ($test){
      $ipAllowed = '127.0.0.1';
      if ($callerIp == '::1'){//IPv6 Issue
        $callerIp = '127.0.0.1';
      }
    }

  if($ipAllowed != $callerIp && !$test) {
    $emailBody .= 'Unauthorised access from the IP [' . $callerIp . ']' .$lineBreak.$lineBreak;
    return false;
  }
  return true;
}

function saveInvoice (Mage_Sales_Model_Order $order) {
  global $lineBreak, $emailBody, $paypalAllowed, $invoiceAllowed, $callBackExecuted;
	if(!$callBackExecuted) {
		if ($order->canInvoice()) {
				$invoice = $order->prepareInvoice();
				$invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)
					->register();
				Mage::getModel('core/resource_transaction')
					->addObject($invoice)
					->addObject($invoice->getOrder())
					->save();
				//$invoice->sendEmail(true, '');//this would send orig. order details to customer againif			
				if(in_array($_REQUEST['payment_type'],$invoiceAllowed)) {					
					$emailBody .= "Novalnet Callback Script executed successfully. Payment for order id :" . $_REQUEST['order_no'] . '. New TID: ('. $_REQUEST['tid'] . ') on ' . date('Y-m-d H:i:s').$lineBreak;
				} else {
					$emailBody .= "Novalnet Callback Script executed successfully. Payment for order id :" . $_REQUEST['order_no'] . ' on ' . date('Y-m-d H:i:s').$lineBreak;
				}			
		}else{
			$emailBody .= "Novalnet callback received. Callback Script executed already. Refer Order :".$_REQUEST['order_no'].$lineBreak;
			return false;
		}
	}
  return true;
}

function doTransactionStatusSave($response, $transactionStatus, $orderNo) {
	$order = getOrderByIncrementId($orderNo);
	$amount = $response['amount'];
	$ncNo = (isset($response['nc_no'])) ? $response['nc_no'] : NULL;
	$helper = Mage::helper('novalnet_payment');
	$payment = $order->getPayment();
	$paymentObj = $payment->getMethodInstance();
	$storeId = $order->getStoreId();
	$modNovalTransactionStatus = Mage::getModel('novalnet_payment/transactionstatus');
	$modNovalTransactionStatus->setTransactionNo($response['tid'])
			->setOrderId($response['order_no'])
			->setTransactionStatus($transactionStatus->getStatus()) //Novalnet Admin transaction status
			->setNcNo($ncNo)   //nc number
			->setCustomerId($response['customer_no'])
			->setPaymentName($paymentObj->getCode())
			->setAmount($helper->getFormatedAmount($amount, 'RAW'))
			->setRemoteIp($helper->getRealIpAddr())
			->setStoreId($storeId)
			->setShopUrl($helper->getCurrentSiteUrl())
			->setCreatedDate($helper->getCurrentDateTime())
			->save();
}

 function doTransactionOrderLog($response, $orderno) {
 	$order = getOrderByIncrementId($orderNo);
	$helper = Mage::helper('novalnet_payment');
	$storeId = $order->getStoreId();
	$modNovalTransactionOverview = $helper->getModelTransactionOverview()->loadByAttribute('order_id', $orderno);
	$modNovalTransactionOverview->setTransactionId($response['tid'])
			->setResponseData(serialize($response))
			->setCustomerId($response['customer_no'])
			->setStatus($response['status']) //transaction status code
			->setStoreId($storeId)
			->setShopUrl($helper->getCurrentSiteUrl())
			->save();
}

?>
