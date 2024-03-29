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
class Novalnet_Payment_Adminhtml_TransactionoverviewController extends Mage_Adminhtml_Controller_Action
{

    /**
     * @return Novalnet_NovalnetIdeal_Adminhtml_TransactionController
     *
     */
    protected function _initAction()
    {
        $this->loadLayout();
        $this->setUsedModuleName('novalnet_payment');
        $this->_setActiveMenu('novalnet/transactionoverview');
        $this->_addBreadcrumb($this->__('Novalnet'), $this->__('Novalnet'));

        $this->_title($this->__('Novalnet'));
        $this->_title($this->__('Transaction Overview'));

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
     * Create transaction overview block
     *
     */
    public function gridAction()
    {
        $this->getResponse()->setBody(
                $this->getLayout()->createBlock('novalnet_payment/adminhtml_transactionoverview_grid')->toHtml()
        );
    }

    /**
     * View the transaction overview information
     *
     */
    public function viewAction()
    {
        $id = $this->getRequest()->getParam('nnlog_id');
        $modelTransOverview = Mage::helper('novalnet_payment')->getModelTransactionOverview()->load($id);

        if (empty($id) || !$modelTransOverview->getNnLogId()) {
            $this->_forward('noRoute');
        }

        $this->_title(sprintf("#%s", $modelTransOverview->getTransactionId()));

        // @var $modelTransOverview Novalnet_Payment_Model_Transactionoverview
        $modelTransaction = Mage::helper('novalnet_payment')->getModelTransactionOverview();
        $modelTransaction->loadByOrderLogId($modelTransOverview);

        Mage::register('novalnet_payment_transactionoverview', $modelTransOverview);

        $this->_initAction();
        $this->renderLayout();
    }

}
