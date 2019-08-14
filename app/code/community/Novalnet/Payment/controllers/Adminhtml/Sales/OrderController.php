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
require_once 'Mage' . DS . 'Adminhtml' . DS . 'controllers' . DS . 'Sales' . DS . 'OrderController.php';

class Novalnet_Payment_Adminhtml_Sales_OrderController extends Mage_Adminhtml_Sales_OrderController {

    var $module_name = 'novalnet_payment';

    /**
     * @return Mage_Adminhtml_Sales_OrderController
     */
    protected function _initAction() {
        $this->loadLayout()
                ->setUsedModuleName($this->module_name)
                ->_setActiveMenu('novalnet')
                ->_addBreadcrumb($this->__('Novalnet'), $this->__('Orders'));

        return $this;
    }

    /**
     *
     */
    public function indexAction() {
        $this->_title($this->__('Novalnet'))->_title($this->__('Orders'));

        $this->_initAction()
                ->renderLayout();
    }

    /**
     *
     */
    public function transactionStatusGridAction() {
        $this->_initOrder();
        $this->getResponse()->setBody(
                Mage::getBlockSingleton('novalnet_payment/adminhtml_sales_order_view_tab_transactionStatus')->toHtml()
        );
    }

    /**
     *
     */
    public function transactionOverviewGridAction() {
        $this->_initOrder();
        $this->getResponse()->setBody(
                Mage::getBlockSingleton('novalnet_payment/adminhtml_sales_order_view_tab_transactionOverview')->toHtml()
        );
    }

    /**
     *
     */
    public function gridAction() {
        $this->getResponse()->setBody(
                $this->getLayout()->createBlock('novalnet_payment/adminhtml_sales_order_grid')->toHtml()
        );
    }

    /**
     * Invoice update order
     */
    public function invoiceupdateAction() {
        $order = $this->_initOrder(); 
        if ($order) {
            try {
                Mage::helper('novalnet_payment/AssignData')->setNovalnetInvoiceUpdate($order);
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addError($this->__('The Invoice was not updated.'));
            }
        }
        $this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
    }

    public function holdAction() {
        $order = $this->_initOrder(); 
        if ($order) {
            try {
                Mage::helper('novalnet_payment/AssignData')->setNovalnetTidOnHold($order);
                $order->hold()
                        ->save();
                $this->_getSession()->addSuccess(
                        $this->__('The order has been put on hold.')
                );
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addError($this->__('The order was not put on hold.'));
            }
            $this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
        }
    }

}
