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
class Novalnet_Payment_Adminhtml_Novalnetpayment_TransactiontracesController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Init layout, menu and breadcrumb
     *
     * @param  none
     * @return Novalnet_Payment_Adminhtml_Novalnetpayment_TransactiontracesController
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->setUsedModuleName('novalnet_payment')
            ->_setActiveMenu('novalnet/transactiontraces')
            ->_addBreadcrumb($this->__('Novalnet'), $this->__('Transaction Traces'));

        return $this;
    }

    /**
     * Transaction traces overview
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
     * Create transaction traces block
     *
     * @param  none
     * @return none
     */
    public function gridAction()
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('novalnet_payment/adminhtml_transactiontraces_grid')->toHtml()
        );
    }

    /**
     * Get the transaction traces information
     *
     * @param  none
     * @return none
     */
    public function viewAction()
    {
        $nnLogId = $this->getRequest()->getParam('nnlog_id');
        $transactionTraces = Mage::getModel('novalnet_payment/Mysql4_TransactionTraces');
        $tracesCollection = $transactionTraces->load($nnLogId);

        if (empty($nnLogId) || !$tracesCollection->getNnLogId()) {
            $this->_forward('noRoute');
        }

        $this->_title(sprintf("#%s", $tracesCollection->getTransactionId()));

        $transactionTraces->loadByOrderLogId($tracesCollection);

        Mage::register('novalnet_payment_transactiontraces', $tracesCollection);

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
        return Mage::getSingleton('admin/session')->isAllowed('novalnetpayment_transactiontraces');
    }
}
