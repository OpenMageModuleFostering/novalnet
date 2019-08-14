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
 * Part of the payment module of Novalnet AG
 * https://www.novalnet.de
 * If you have found this script useful a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Copyright (c) Novalnet AG. (https://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Adminhtml_Novalnetpayment_TransactionController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Init layout, menu and breadcrumb
     *
     * @return Novalnet_Payment_Adminhtml_TransactionController
     *
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->setUsedModuleName('novalnet_payment')
            ->_setActiveMenu('novalnet/transactionstatus')
            ->_addBreadcrumb($this->__('Novalnet'), $this->__('Transaction'));

        return $this;
    }

    /**
     * Render layout
     *
     */
    public function indexAction()
    {
        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * Create transaction status block
     *
     */
    public function gridAction()
    {
        $this->getResponse()->setBody(
                $this->getLayout()->createBlock('novalnet_payment/adminhtml_transaction_grid')->toHtml()
        );
    }

    /**
     * View the transaction status information
     *
     */
    public function viewAction()
    {
        $nnTxnId = $this->getRequest()->getParam('nntxn_id');
        $transactionStatus = Mage::helper('novalnet_payment')->getModelTransactionStatus()->load($nnTxnId);

        if (empty($nnTxnId) || !$transactionStatus->getNnTxnId()) {
            $this->_forward('noRoute');
        }

        $this->_title(sprintf("#%s", $transactionStatus->getTransactionNo()));

        // @var $transactionStatus Novalnet_Payment_Model_Transactionstatus
        $modelTransaction = Mage::helper('novalnet_payment')->getModelTransactionStatus();
        $modelTransaction->loadByTransactionStatusId($transactionStatus);

        Mage::register('novalnet_payment_transactionstatus', $modelTransaction);

        $this->_initAction();
        $this->renderLayout();
    }
}