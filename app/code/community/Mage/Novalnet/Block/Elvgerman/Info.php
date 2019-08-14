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
 * Part of the Paymentmodul of Novalnet AG
 * http://www.novalnet.de 
 * If you have found this script usefull a small        
 * recommendation as well as a comment on merchant form 
 * would be greatly appreciated.
 * 
 * @category   design_default
 * @package    Mage
 * @copyright  Copyright (c) 2008 Novalnet AG
 * @version    1.0.0
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Novalnet_Block_Elvgerman_Info extends Mage_Payment_Block_Info
{
	protected $_localInfo = NULL;
	
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('novalnet/elvgerman/info.phtml');
    }

    public function getInfo()
    {
		if (!$this->_localInfo) {
			$this->_localInfo = $this->getData('info');
			$this->loadNovalnetData();
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
	
    public function getPaymentMethod()
    {
        return $this->htmlEscape($this->getMethod()->getConfigData('title'));
    }
	
    public function getInfoData($field)
    {
        return $this->htmlEscape($this->getMethod()->getInfoInstance()->getData($field));
    }
	public function toPdf()
    {
        $this->setTemplate('payment/info/pdf/cc.phtml');
        return $this->toHtml();
    }
	public function loadNovalnetData() {
		$order_id = $this->getRequest()->getParam('order_id');
		$obj = NULL;
		if($this->getRequest()->getControllerName() == 'sales_order_invoice') {
			$order_id = $this->getData('info')->getOrder()->getId();
		}
		if( $order_id ) {
			$objOrder = Mage::getModel('sales/order')->load($order_id);
			$objQuote = Mage::getModel( 'sales/quote' );
			$obj = $objQuotePayment = $objQuote->setStoreId($objOrder->getStoreId())->load($objOrder->getQuoteId())->getPayment();
        
		}else if( $this->getRequest()->getParam('invoice_id') ) {
   		$invoice_id = $this->getRequest()->getParam('invoice_id') ;
   		$invoice  = Mage::getModel('sales/order_invoice')->load($invoice_id);
   		$objOrder = $invoice->getOrder();
   		$objQuote = Mage::getModel( 'sales/quote' );
   		$obj = $objQuotePayment = $objQuote->setStoreId($objOrder->getStoreId())->load($objOrder->getQuoteId())->getPayment();
 	 	}else {
			$chSess = Mage::getSingleton('checkout/session');
			if($this->getRequest()->getControllerName() == 'onepage' && $this->getRequest()->getActionName() == 'saveOrder' && $chSess->hasLastSuccessQuoteId()){
				$objQuote = Mage::getModel( 'sales/quote' );
				$obj = $objQuotePayment = $objQuote->setStoreId($this->getMethod()->getStoreId())->load($chSess->getLastSuccessQuoteId())->getPayment();
			}else {
				$obj = $this->_localInfo;
			}
		}
		$this->setNnAccountHolder($obj->getNnAccountHolder());
		$this->setNnAccountNumber($obj->getNnAccountNumber());
		$this->setNnBankSortingCode($obj->getNnBankSortingCode());
		$this->setNnElvCountry($obj->getNnElvCountry());
		$this->setNnTestorder($obj->getNnTestorder());
		$this->setNnComments($obj->getNnComments());
		return $this;
	}
}
