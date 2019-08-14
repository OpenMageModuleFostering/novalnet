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
 * @category  Novalnet
 * @package   Novalnet_Payment
 * @copyright Copyright (c) Novalnet AG. (https://www.novalnet.de)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Adminhtml_Novalnetpayment_TransactionoverviewController extends Mage_Adminhtml_Controller_Action
{

    /**
     * Init layout, menu and breadcrumb
     *
     * @param  none
     * @return Novalnet_Payment_Adminhtml_Novalnetpayment_TransactionoverviewController
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
     * Transaction status overview
     *
     * @param  none
     * @return none
     */
    public function indexAction()
    {
        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * Create transaction status block
     *
     * @param  none
     * @return none
     */
    public function gridAction()
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('novalnet_payment/adminhtml_transactionoverview_grid')->toHtml()
        );
    }

    /**
     * Get the transaction status information
     *
     * @param  none
     * @return none
     */
    public function viewAction()
    {
        $nnTxnId = $this->getRequest()->getParam('nntxn_id');
        $transactionStatus = Mage::getModel('novalnet_payment/Mysql4_TransactionStatus');
        $statusCollection = $transactionStatus->load($nnTxnId);

        if (empty($nnTxnId) || !$statusCollection->getNnTxnId()) {
            $this->_forward('noRoute');
        }

        $this->_title(sprintf("#%s", $statusCollection->getTransactionNo()));
        $transactionStatus->loadByTransactionStatusId($statusCollection);
        Mage::register('novalnet_payment_transactionoverview', $transactionStatus);
        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * Check admin permissions for this controller
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('novalnetpayment_transactionoverview');
    }
}
