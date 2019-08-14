<?php
class Mage_Novalnet_SecureController extends Mage_Core_Controller_Front_Action
{
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
    protected $_redirectBlockType  = 'novalnet/secure_redirect';#secure_redirect = block/secure/redirect.php

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
        $order->addStatusToHistory($order->getStatus(), Mage::helper('novalnet')->__('Customer was redirected to Novalnet.'));
        $order->save();

        #update order status to pending
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
      global$response;
        $status = $this->_checkReturnedPost();

        $session = $this->getCheckout();

        $session->unsNovalnetRealOrderId();
        $session->setQuoteId($session->getNovalnetQuoteId(true));
        $session->getQuote()->setIsActive(false)->save();

        $order = Mage::getModel('sales/order');
        $order->load($this->getCheckout()->getLastOrderId());
        if($order->getId()) {
            $order->sendNewOrderEmail();
        }

        if ($status == 100) {
            $this->setOrderStatus($response['inputval1'], $_SESSION['status_zh']);#new
            unset($_SESSION['status_zh']);
            $this->_redirect('checkout/onepage/success');
        } else {
            #$this->_redirect('*/*/failure');#orig
            #$this->_redirect('checkout/onepage/failure');#ok, but not so good; $this->_redirect('*/*/failure');
            $this->deleteOrder($increment_id = $response['inputval1']);#new
            $this->_redirect('checkout/cart');#new; ok

        }
    }

    /**
     * Display failure page if error
     *
     */
    public function failureAction()
    {
        if (!$this->getCheckout()->getNovalnetErrorMessage()) {
            $this->norouteAction();
            return;
        }

        $this->getCheckout()->clear();

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Checking POST variables.
     * Creating invoice if payment was successfull or cancel order if payment was declined
     */
    protected function _checkReturnedPost()
    {
      global$response;
        if (!$this->getRequest()->isPost()) {
            $this->norouteAction();
            return;
        }
        $status = true;
        $response = $this->getRequest()->getPost();
        //error_log(print_r($response,true),3,'/tmp/magento_response.log');

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($response['inputval1']);
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

                $note = $order->getCustomerNote();
                if ($note){
                  $note = '<br />'.Mage::helper('novalnet')->__('Comment').': '.$note;
                }
                if ( $response['test_mode']) {
                  $note .= '<br /><b><font color="red">'.strtoupper(Mage::helper('novalnet')->__('Testorder')).'</font></b>';
                }
                $order->setCustomerNote($note);
                $order->setCustomerNoteNotify(true);
                $order->setComment($note);
            //}
        } else {
            $paymentInst->setTransactionId($response['tid']);
            $payment->setLastTransId($response['tid']);
            $payment->setCcTransId($response['tid']);
            $order->cancel();
            $order->addStatusToHistory($order->getStatus(), Mage::helper('novalnet')->__('Customer was rejected by Novalnet'));
            $status = false;
            $this->getCheckout()->setNovalnetErrorMessage($response['status_text']);
        }

        $order->save();

        return $status;
    }
  private function debug2($object, $filename, $debug)
	{
		if (!$debug){return;}
		$fh = fopen("/tmp/$filename", 'a+');
		if (gettype($object) == 'object' or gettype($object) == 'array'){
			fwrite($fh, serialize($object));
		}else{
			fwrite($fh, date('Y-m-d H:i:s').' '.$object);
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
}
?>