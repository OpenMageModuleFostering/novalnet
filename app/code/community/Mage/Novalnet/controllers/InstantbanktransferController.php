<?php
class Mage_Novalnet_InstantbanktransferController extends Mage_Core_Controller_Front_Action
{
	private $debug = false; #todo: set to false
    protected function _expireAjax()
    {
        if (!$this->getCheckout()->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
            exit;
        }
    }

    /**
     * Redirect Block
     * need to be redeclared
     */
    protected $_redirectBlockType  = 'novalnet/instantbanktransfer_redirect';#path = block/instantbanktransfer/redirect.php

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
     * when customer select novalnet payment method
     */
    public function redirectAction()
    {
        $session = $this->getCheckout();
        $session->setNovalnetQuoteId($session->getQuoteId());
        $session->setNovalnetRealOrderId($session->getLastRealOrderId());

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($session->getLastRealOrderId());
        $order->addStatusToHistory($order->getStatus(), Mage::helper('novalnet')->__('Customer was redirected to Novalnet'));

        $note = $order->getCustomerNote();
        if ($note){
          $note = '<br />'.Mage::helper('novalnet')->__('Comment').': '.$note;
        }
        #if (!$this->getConfigData('live_mode')) {
          $note .= '<br /><b><font color="red">'.strtoupper(Mage::helper('novalnet')->__('Testorder')).'</font></b>';
        #}
        $order->setComment($note);
        $order->setCustomerNote($note);
        $order->setCustomerNoteNotify(true);

        $order->save();
        #todo: update order status to open
        $_SESSION['status_zh']    = $order->getStatus();
        $this->setOrderStatus($session->getLastRealOrderId(), 'pending');

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock($this->_redirectBlockType)
                ->setOrder($order)
                ->toHtml()
        );

        $session->unsQuoteId();
    }

    /**
     * novalnet returns POST variables to this action
     */
    public function  successAction()
    {
        $status = $this->_checkReturnedPost();

        if ($status) {
            $session = $this->getCheckout();

            $session->unsNovalnetRealOrderId();
            $session->setQuoteId($session->getNovalnetQuoteId(true));
            $session->getQuote()->setIsActive(false)->save();

            $order = Mage::getModel('sales/order');
            $order->load($this->getCheckout()->getLastOrderId());

      $note  = $order->getCustomerNote();
      if ($note){
        $note = '<br />'.Mage::helper('novalnet')->__('Comment').': '.$note;
      }
      #if ( !$this->getConfigData('live_mode') ){
        $note .= '<br /><b><font color="red">'.strtoupper(Mage::helper('novalnet')->__('Testorder')).'</font></b>';
      #}
      $order->setComment($note);
      $order->setCustomerNote($note);
      $order->setCustomerNoteNotify(true);

            if($order->getId()) {
                $order->sendNewOrderEmail();
            }

        #if ($status) {#removed to line 61
            $this->_redirect('checkout/onepage/success');
        } else {
            #$this->_redirect('checkout/onepage/failure');#ok, but not so good; $this->_redirect('*/*/failure');
            $this->_redirect('checkout/cart');#new; ok
            #$this->_redirect('checkout/shipping');#new; not ok, nor: 'checkout/payment'
        }
    }

    /**
     * Display failure page if error
     *
     */
    public function failureAction()
    {
        $status = $this->_checkReturnedPost();#new
        if (!$this->getCheckout()->getNovalnetErrorMessage()) {
            $this->norouteAction();
            #$this->_redirect('checkout/onepage/payment');
            return;
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Checking POST variables.
     * Creating invoice if payment was successfull or cancel order if payment was declined
     */
    protected function _checkReturnedPost()
    {
        if (!$this->getRequest()->isPost()) {
            $this->norouteAction();
            return;
        }
        $status = true;
        $response = $this->getRequest()->getPost();
        //error_log(print_r($response,true),3,'/tmp/magento_response.log');

        if ($response['status'] == 100){
          $response['status'] = $this->checkParams($response);
          $response['amount'] = $this->decode($response['amount'], $_SESSION['mima']);
          if (preg_match('/\D/', $response['amount'], $aMatch)){
            $response['status'] = '93'; #decode amount failed
          }
        }

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($response['inputval1']);#order_id;
        $payment = $order->getPayment();
        $paymentInst = $payment->getMethodInstance();
      
        $paymentInst->setResponse($response);

        if ($response['status'] == 100 ) {
           // if ($order->canInvoice()) {
                $invoice = $order->prepareInvoice();
                $invoice->register()->capture();
                Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();

                $paymentInst->setTransactionId($response['tid']);
                $payment->setLastTransId($response['tid']);
                $payment->setCcTransId($response['tid']);
                $order->addStatusToHistory($order->getStatus(), Mage::helper('novalnet')->__('Customer successfully returned from Novalnet'));
                if ( $this->decode($response['test_mode'], $_SESSION['mima'])) {
                  $note = '<br /><b><font color="red">'.strtoupper(Mage::helper('novalnet')->__('Testorder')).'</font></b>';
                  $order->addStatusToHistory($order->getStatus(), $note);
                }

                $note = $order->getCustomerNote();
                if ($note){
                  $note = '<br />'.Mage::helper('novalnet')->__('Comment').': '.$note;
                }
                if ( $this->decode($response['test_mode'], $_SESSION['mima'])) {
                  $note .= '<br /><b><font color="red">'.strtoupper(Mage::helper('novalnet')->__('Testorder')).'</font></b>';
                }
                $order->setComment($note);
                $order->setCustomerNote($note);
                $order->setCustomerNoteNotify(true);

                $order->save();
                $this->setOrderStatus($response['inputval1'], $_SESSION['status_zh']);#new
                unset($_SESSION['status_zh']);
            //}
        } else {#failed
            $paymentInst->setTransactionId($response['tid']);
            $payment->setLastTransId($response['tid']);
            $payment->setCcTransId($response['tid']);

            if ($response['status'] == 94)
            {
                Mage::getSingleton('checkout/session')->addError("Customer aborted payment process");#new
                $order->addStatusToHistory($order->getStatus(), Mage::helper('novalnet')->__('Customer aborted payment process'));
            }elseif ($response['status'] >= 90 or $response['status'] <= 93) {#check encoded params failure
                Mage::getSingleton('checkout/session')->addError("Check encoded params failure");#new
                $order->addStatusToHistory($order->getStatus(), Mage::helper('novalnet')->__('Check encoded params failure'));
                $response['status_text'] = 'Check encoded params failure';
            }
            else
            {
                $order->addStatusToHistory($order->getStatus(), Mage::helper('novalnet')->__('Customer was rejected by Novalnet'));
            }
            #$order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, "Failure:'No extra information'");#new
            $order->cancel();
            $status = false;
            $this->getCheckout()->setNovalnetErrorMessage($response['status_text']);
            $order->save();
            $this->deleteOrder($increment_id = $response['inputval1']);#new
        }
        return $status;
    }

	private function debug2($object, $filename, $debug = false)
	{
		if (!$this->debug and !$debug){return;}
		$fh = fopen("/tmp/$filename", 'a+');
		if (gettype($object) == 'object' or gettype($object) == 'array'){
			fwrite($fh, serialize($object));
		}else{
			fwrite($fh, date('H:i:s').' '.$object);
		}
		fwrite($fh, "<hr />\n");
		fclose($fh);
	}

  private function deleteOrder($increment_id)
  {
      $conn = Mage::getSingleton('core/resource')->getConnection('core_write');
      #$conn   = Mage::getResourceSingleton('core/resource')->getConnection('core_write');
      $query  = "select entity_id from sales_order_entity where increment_id='$increment_id';";
      if ($result = $conn->query($query))
      {
          if ($rows = $result->fetch(PDO::FETCH_ASSOC))
          {
              $order_id = $row['entity_id'];
              $query    = "delete from sales_order_entity where entity_id='$order_id' or parent_id='$order_id';";
              $conn->query($query);
          }
      }
      $query = "delete from sales_order where increment_id='$increment_id';";
      $conn = Mage::getSingleton('core/resource')->getConnection('core_write');
      $conn->query($query);
  }

  private function checkParams($response){
    $status = 90;

    if (!$response['hash2']){
      return'90';
    }
    if (!$this->checkHash($response, $_SESSION['mima'])){
      return'91';
    }
    if (!preg_match('/\D/', $response['amount'], $aMatch)){
      return'92';
    }
    return'100';
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
      return false;
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
  function setOrderStatus($orderId, $status){
    #$status = 'pending';
    $sql = "select * from sales_order_entity_varchar where entity_id in ( select entity_id from sales_order_entity where parent_id = (SELECT entity_id FROM `sales_order` WHERE increment_id = '$orderId') and entity_type_id = 17 /*sales_order_history*/ order by updated_at desc) and attribute_id = 559 /*status*/;";
    #$this->debug2($sql, $filename='ibt_sql.txt', $debug = true);
    $aAll = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($sql);
    if ($aAll){
      foreach($aAll as $h){#set sales_order_history status to open
        $sql = "update sales_order_entity_varchar set value = '$status' where value_id = '".$h['value_id']."'";
        $conn = Mage::getSingleton('core/resource')->getConnection('core_write');
        $conn->query($sql);
      }
    }

    $sql = "select * from sales_order_varchar where entity_id in (SELECT entity_id FROM `sales_order` WHERE increment_id = '$orderId') and (attribute_id = 215 /*status*/)";#or attribute_id = 553 /*state*/
    #$this->debug2($sql, $filename='ibt_sql.txt', $debug = true);
    $aAll = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($sql);
    if ($aAll){
      foreach($aAll as $h){#set sales_order_status to open
        $sql = "update sales_order_varchar set value = '$status' where value_id = '".$h['value_id']."'";
        $conn = Mage::getSingleton('core/resource')->getConnection('core_write');
        $conn->query($sql);
      }
    }
  }
    /*order of func
    19:30:01 redirectAction<hr />controller
    19:30:03 getFormFields<hr />
    19:30:47 successAction<hr />controller
    19:30:47 _checkReturnedPost<hr />controller
    19:30:48 capture<hr />
  */
}
?>