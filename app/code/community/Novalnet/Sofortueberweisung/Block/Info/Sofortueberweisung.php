<?php
class Novalnet_Sofortueberweisung_Block_Info_Sofortueberweisung extends Mage_Payment_Block_Info
{
    /**
     * Init default template for block
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('novalnetsofortueberweisung/info/sofortueberweisung.phtml');
    }
	
	 /**
     * Retrieve info model
     *
     * @return Mage_Sofortueberweisung_Model_Info
     */
     public function getInfo()
    {
		if (!$this->_localInfo) {			
			$this->_localInfo = $this->getData('info');
			$this->loadNovalnetData();
			if($this->getNnTestorder())
				$this->_localInfo->setNnTestorder($this->getNnTestorder());
			}
        if (!($this->_localInfo instanceof Mage_Payment_Model_Info)) {
            Mage::throwException($this->__('Can not retrieve payment info model object.'));
        }
        return $this->_localInfo;
    }	
	
	 /**
     * Retrieve payment method model
     *
     * @return Mage_Payment_Model_Method_Abstract
     */
    public function getMethod()
    {
        return $this->getInfo()->getMethodInstance();
    }
	
	public function toPdf()
    {
        $this->setTemplate('novalnetsofortueberweisung/info/pdf/sofortueberweisung.phtml');
        return $this->toHtml();
    }
	 public function loadNovalnetData() {
		$order_id = $this->getRequest()->getParam('order_id');
		$obj = NULL;
		if( $order_id ) {
			$objOrder = Mage::getModel('sales/order')->load($order_id);
			$objQuote = Mage::getModel( 'sales/quote' );
			$obj = $objQuotePayment = $objQuote->setStoreId($objOrder->getStoreId())
											   ->load($objOrder->getQuoteId())
											   ->getPayment();
		}else if( $this->getRequest()->getParam('invoice_id') ) {
   		$invoice_id = $this->getRequest()->getParam('invoice_id') ;
   		$invoice  = Mage::getModel('sales/order_invoice')->load($invoice_id);
   		$objOrder = $invoice->getOrder();
   		$objQuote = Mage::getModel( 'sales/quote' );
   		$obj = $objQuotePayment = $objQuote->setStoreId($objOrder->getStoreId())->load($objOrder->getQuoteId())->getPayment();
 	 	}else {
			$obj = $this->_localInfo;
		}
		$this->setNnTestorder($obj->getNnTestorder());
		$this->setNnComments($obj->getNnComments());
		return $this;
	}	
}
