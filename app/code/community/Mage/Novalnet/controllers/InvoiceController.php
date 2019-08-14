<?php
class Mage_Novalnet_InvoiceController extends Mage_Core_Controller_Front_Action
{
	public function invoicefunctionAction()
	{
		$session=Mage::getSingleton('checkout/session');
	
		$resultdata=$session->getInvoiceReqData();
		$resultnote=$session->getInvoiceReqDataNote();
				
		$last_order_id=$session->getLastRealOrderId();
		$order = Mage::getModel('sales/order')->loadByIncrementId($last_order_id);
		
		$payment = $order->getPayment();
		$payment->setStatusDescription(Mage::helper('novalnet')->__('Payment was successful.'))
				->setTransactionId($resultdata->getTid())
				->setSuTransactionId($resultdata->getTid())
				->setLastTransId($resultdata->getTid())
				->setNote($resultnote);
		$order->setPayment($payment)
			   ->save();
	
		if( $resultdata->hasTestMode() ) {
				Mage::getModel( 'sales/quote' )
					->load($session->getQuoteId())
					->getPayment()
					->setNnComments($resultnote)
					->setNnTestorder($resultdata->getTestMode())
					->save();
			}
		
		//$this->saveInvoice($order); // To generate Order Invoice 

		$session->unsInvoiceReqData()
				->unsInvoiceReqDataNote();
		
		$url = Mage::getModel('core/url')->getUrl("checkout/onepage/success");
		Mage::getSingleton('core/session')->addSuccess('Successful');
		Mage::app()->getResponse()->setRedirect($url);
        Mage::app()->getResponse()->sendResponse();			
		exit;
			
	}
	 
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
	  
}