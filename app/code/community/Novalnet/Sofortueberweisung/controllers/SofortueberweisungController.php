<?php
class Novalnet_Sofortueberweisung_SofortueberweisungController extends Mage_Core_Controller_Front_Action
{

  protected $_redirectBlockType = 'sofortueberweisung/sofortueberweisung';
  protected $_status            = '100';

  /**
   * when customer select payment method
   */
  public function redirectAction() {
    $session = $this->getCheckout();
	$session->setNovalnetQuoteId($session->getQuoteId())
			->setNovalnetRealOrderId($session->getLastRealOrderId());
    $order = Mage::getModel('sales/order');
    $order->loadByIncrementId($session->getLastRealOrderId());
    $order->addStatusToHistory(Mage_Sales_Model_Order::STATE_HOLDED, Mage::helper('sofortueberweisung')->__('Novalnet payment loaded'));
    $order->save();

    $this->getResponse()->setBody(
        $this->getLayout()
            ->createBlock($this->_redirectBlockType)
            ->setOrder($order)
            ->toHtml()
    );
    $session->unsQuoteId();
  }

  public function returnAction() {

    $response = $this->getRequest()->getParams();
    $response['orderId'] = $response['inputval1'];#order_id;


    $status = $this->_checkReturnedData();


    if(!$response['orderId'] or !$status) {
      $session = $this->getCheckout();
      $session->getQuote()->setIsActive(false)->save();
      Mage::getSingleton('checkout/session')->addError("Payment error");#new
      $this->_redirect('checkout/cart');#new; ok
    } else {
      //load order and send mail
      $order = Mage::getModel('sales/order');
      $order->loadByIncrementId($response['orderId']);
      $paymentObj = $order->getPayment()->getMethodInstance();
      $payment = $order->getPayment();

      #if($order->getId()) {
        #Mage::log('sendNewOrderEmail');
        $order->sendNewOrderEmail();
      #}

      $session = $this->getCheckout();
      $session->getQuote()->setIsActive(false)->save();
		$session->unsNnInstantTestOrder();
      $this->_redirect('checkout/onepage/success');
    }
  }

  public function returnhttpAction() {
    if (!$this->getRequest()->isPost()) {
          $this->norouteAction();
          return;
      }

    $response = $this->getRequest()->getParams();	

    $order = Mage::getModel('sales/order');
    $order->loadByIncrementId($response['orderId']);
    $paymentObj = $order->getPayment()->getMethodInstance();
    $payment = $order->getPayment();


    $status = $this->_checkReturnedData();
    if ($status) {
      $order = Mage::getModel('sales/order');
      $order->loadByIncrementId($response['orderId']);
      if($order->getId()) {
        $order->sendNewOrderEmail();
      }
    } else {
      $order = Mage::getModel('sales/order');
      $order->loadByIncrementId($response['orderId']);
      $order->cancel();
      $order->addStatusToHistory($order->getStatus(), Mage::helper('sofortueberweisung')->__('Customer canceled payment or payment error'));
      $order->save();
    }
  }

  public function errorAction() {
    $session = $this->getCheckout();
    $session->getQuote()->setIsActive(false)->save();

    $order = Mage::getModel('sales/order');
    $order->load($this->getCheckout()->getLastOrderId());
    $order->cancel();
    $order->addStatusToHistory($order->getStatus(), Mage::helper('sofortueberweisung')->__('Customer canceled payment'));
    $order->save();
	$response = $this->getRequest()->getParams();
    Mage::getSingleton('checkout/session')->addError($response['status_text']);#new
    $this->_redirect('checkout/cart');#new; ok

  }

  public function errornoticeAction() {
    $session = $this->getCheckout();
    $session->getQuote()->setIsActive(false)->save();

    $order = Mage::getModel('sales/order');
    $order->load($this->getCheckout()->getLastOrderId());
    $order->cancel();
    $order->addStatusToHistory($order->getStatus(), Mage::helper('sofortueberweisung')->__('Payment error'));
    $order->save();

    Mage::getSingleton('checkout/session')->addError("Payment error");#new
    $this->_redirect('checkout/cart');#new
  }

  /**
   * Checking Post variables.
   * 
   */
  protected function _checkReturnedData() {
    $status = false;
    if (!$this->getRequest()->isPost()) {
      $this->norouteAction();
      return;
    }

    //Get response
    $response            = $this->getRequest()->getParams();
    $response['orderId'] = $response['inputval1'];#order_id;

        if ($response['status'] == 100) {
          $response['status'] = $this->checkParams($response);
        }


    $order = Mage::getModel('sales/order');
    $order->loadByIncrementId($response['orderId']);
    $paymentObj = $order->getPayment()->getMethodInstance();

    if ($response['status'] == 100 ) {
      $payment = $order->getPayment();
	  $session = $this->getCheckout();
      $payment->setStatus(Novalnet_Sofortueberweisung_Model_Sofortueberweisung::STATUS_SUCCESS);
      $payment->setStatusDescription(Mage::helper('sofortueberweisung')->__('Payment was successful.'));
      $payment->setLastTransId($response["tid"]);# to set TID in the payment info
      $payment->setNnTestorder($this->decode($response['test_mode'], $_SESSION['mima']));# to set Test order in the payment info
      $order->addStatusToHistory($paymentObj->getConfigData('order_status'), Mage::helper('sofortueberweisung')->__('Payment was successful.'));

	  if( $response['test_mode'] ) {
				Mage::getModel( 'sales/quote' )
					->load($session->getNovalnetQuoteId())
					->getPayment()
					->setNnTestorder($this->decode($response['test_mode'], $_SESSION['mima']))
					->save();
			$session->setNnInstantTestOrder($this->decode($response['test_mode'], $_SESSION['mima']));
		}
		
      $order->setPayment($payment);

      if($paymentObj->getConfigData('createinvoice') == 1){
        if ($this->saveInvoice($order)) {
          //$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
        }
      }

      $status = true;
      Mage::getSingleton('core/session')->addSuccess(Mage::helper('novalnet')->__('successful'));
    } else {
      $payment = $order->getPayment();
      $payment->setLastTransId($response["tid"]);# to set TID in the payment info
      $payment->setStatus(Novalnet_Sofortueberweisung_Model_Sofortueberweisung::STATUS_DECLINED);

      $order->setPayment($payment);
      $order->cancel();
      $order->addStatusToHistory($order->getStatus(), Mage::helper('sofortueberweisung')->__('Payment was not successfull'));
      $status = false;
    }

    $order->save();
    return $status;
  }

  /**
   *  Save invoice for order
   *
   *  @param    Mage_Sales_Model_Order $order
   *  @return	  boolean Can save invoice or not
   */
  protected function saveInvoice (Mage_Sales_Model_Order $order) {
    if ($order->canInvoice()) {
      $invoice = $order->prepareInvoice();

      $invoice->register();
      Mage::getModel('core/resource_transaction')
           ->addObject($invoice)
           ->addObject($invoice->getOrder())
           ->save();

      $invoice->sendEmail(true, '');
      return true;
    }

    return false;
  }

  /**
  * Get singleton of Checkout Session Model
  *
  * @return Mage_Checkout_Model_Session
  */
  public function getCheckout()
  {
    return Mage::getSingleton('checkout/session');
  }

  /**
   * 	checks server response and gets parameters  
   *  @return $data array|string response parameters or ERROR_WRONG_HASH|ERROR_NO_ORDER_DETAILS if error
   * 
   */
  public function getNotification($pwd){
    $pnSu =  Mage::helper('sofortueberweisung');
    $pnSu->classSofortueberweisung($pwd);
    return $pnSu->getNotification();
  }
  private function checkParams($response) {
    $status = '100';
    if (!$response['hash2']){
      $status = '90';
    }
    if (!$this->checkHash($response, $_SESSION['mima'])){
      $status = '91';
    }
    $response['amount'] = $this->decode($response['amount'], $_SESSION['mima']);
    if (preg_match('/\D/', $response['amount'], $aMatch)){
      $status = '92';
    }
    #Mage::log(__FUNCTION__.': status='.$status);
    $this->_status = $status;
    return $status;
  }
  function hash($h, $key)#$h contains encoded data
  {
    if (!$h) return'Error: no data';
    if (!function_exists('md5')){return'Error: func n/a';}
    return md5($h['auth_code'].$h['product_id'].$h['tariff'].$h['amount'].$h['test_mode'].$h['uniqid'].strrev($key));
  }
  function checkHash($request, $key)
  {
    if (!$request) return false; #'Error: no data';
    $h['auth_code']  = $request['auth_code'];#encoded
    $h['product_id'] = $request['product'];  #encoded
    $h['tariff']     = $request['tariff'];   #encoded
    $h['amount']     = $request['amount'];   #encoded
    $h['test_mode']  = $request['test_mode'];#encoded
    $h['uniqid']     = $request['uniqid'];   #encoded

    if ($request['hash2'] != $this->hash($h, $key)){
		
		Mage::getSingleton('core/session')
			->addError(Mage::helper('sofortueberweisung')->__('Die Hashfunktionen sind nicht verf&uuml;gbar!'));
		$url = Mage::getModel('core/url')->getUrl("checkout/onepage/failure");
		Mage::app()->getResponse()->setRedirect($url);
		Mage::app()->getResponse()->sendResponse();
		exit;
    }
    return true;
  }
  function decode($data, $key)
  {
    $data = trim($data);
    if ($data == '') {return'Error: no data';}
    if (!function_exists('base64_decode') or !function_exists('pack') or !function_exists('crc32')){return'Error: func n/a';}

    try {
      $data =  base64_decode(strrev($data));
      $data = pack("H".strlen($data), $data);
      $data = substr($data, 0, stripos($data, $key));
      $pos = strpos($data, "|");
      if ($pos === false){
        return("Error: CKSum not found!");
      }
      $crc = substr($data, 0, $pos);
      $value = trim(substr($data, $pos+1));
      if ($crc !=  sprintf('%u', crc32($value))){
        return("Error; CKSum invalid!");
      }
      return $value;
    }catch (Exception $e){
      echo('Error: '.$e);
    }
  }
}
