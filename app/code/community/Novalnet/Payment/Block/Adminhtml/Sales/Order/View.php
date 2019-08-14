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
 * Part of the Paymentmodule of Novalnet AG
 * https://www.novalnet.de
 * If you have found this script usefull a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Novalnet AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Block_Adminhtml_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View {

    public function __construct() {

        parent::__construct();

        $order = $this->getOrder();
        $payment = $this->getOrder()->getPayment();
        $paymentMethod = $payment->getMethodInstance()->getCode();
        $helper = Mage::helper('novalnet_payment');
        $getTid = $helper->makeValidNumber($payment->getLastTransId());
		$sepaType = $helper->getModel('novalnetSepa')->_getConfigData('sepatypes');
        if (preg_match("/novalnet/i", $paymentMethod)) {
            $this->_removeButton('order_creditmemo');
		
		if ($paymentMethod == Novalnet_Payment_Model_Config::NN_SEPA) {
			$this->_removeButton('Capture');
		}
        $getTransactionStatus = $helper->loadTransactionStatus($getTid);

        $this->_updateButton('order_invoice', 'label', Mage::helper('novalnet_payment')->__('Capture'));

        if (in_array($paymentMethod, array(Novalnet_Payment_Model_Config::NN_PAYPAL, Novalnet_Payment_Model_Config::NN_SEPA, Novalnet_Payment_Model_Config::NN_TELEPHONE))) {
			$this->_removeButton('order_invoice');
		}

        if ($getTransactionStatus->getTransactionStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED || ($paymentMethod == Novalnet_Payment_Model_Config::NN_SEPA))
            $this->_removeButton('void_payment');

        if ($getTransactionStatus->getTransactionStatus() == Novalnet_Payment_Model_Config::PAYMENT_VOID_STATUS)
            $this->_removeButton('order_invoice');

        if (in_array($paymentMethod, array(Novalnet_Payment_Model_Config::NN_INVOICE, Novalnet_Payment_Model_Config::NN_PREPAYMENT))) {
			$this->_removeButton('order_invoice');
			 if($getTransactionStatus->getTransactionStatus() == 91) {
				$this->_addButton('novalnet_confirm', array(
                'label'     => 'Novalnet Capture',
                'onclick'   => 'setLocation(\'' . $this->getUrl('*/*/novalnetconfirm') . '\')',
            ), 0);
			}
		}

        }
    }

}
