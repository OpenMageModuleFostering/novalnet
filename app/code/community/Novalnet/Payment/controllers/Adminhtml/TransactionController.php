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
class Novalnet_Payment_Adminhtml_TransactionController extends Mage_Adminhtml_Controller_Action {

    /**
     * @return Novalnet_Payment_Adminhtml_TransactionController
     */
    protected function _initAction() {
        $this->loadLayout();
        $this->setUsedModuleName('novalnet_payment');
        $this->_setActiveMenu('novalnet/transactionstatus');
        $this->_addBreadcrumb(
                $this->__('Novalnet'), $this->__('Novalnet')
        );

        $this->_title($this->__('Novalnet'));
        $this->_title($this->__('Transaction'));

        return $this;
    }

    /**
     *
     */
    public function indexAction() {
        $this->_initAction();
        $this->renderLayout();
    }

    /**
     *
     */
    public function gridAction() {
        $this->getResponse()->setBody(
                $this->getLayout()->createBlock('novalnet_payment/adminhtml_transaction_grid')->toHtml()
        );
    }

    public function viewAction() {
        $id = $this->getRequest()->getParam('nntxn_id');
        $modelTransactionStatus = Mage::helper('novalnet_payment')->getModelTransactionStatus()->load($id);

        if (empty($id) || !$modelTransactionStatus->getNnTxnId()) {
            //$this->_redirect('*/*/');
            $this->_forward('noRoute');
        }

        $this->_title(sprintf("#%s", $modelTransactionStatus->getTransactionNo()));

        // @var $modelTransactionStatus Novalnet_Payment_Model_Transactionstatus
        $modelTransaction = Mage::helper('novalnet_payment')->getModelTransactionStatus();
        $modelTransaction->loadByTransactionStatusId($modelTransactionStatus);

        Mage::register('novalnet_payment_transactionstatus', $modelTransaction);

        $this->_initAction();
        $this->renderLayout();
    }

}