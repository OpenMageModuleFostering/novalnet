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

class Novalnet_Payment_Adminhtml_Novalnetpayment_Sales_DeleteorderController extends Mage_Adminhtml_Controller_Action
{

    /**
     * Initialize order model instance
     *
     * @param  none
     * @return Mage_Sales_Model_Order
     */
    protected function _initOrder()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        $order = Mage::getModel('sales/order')->load($orderId);

        if (!$order->getId()) {
            $this->_getSession()->addError($this->__('This order no longer exists.'));
            $this->_redirect('*/*/');
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return false;
        }
        Mage::register('sales_order', $order);
        Mage::register('current_order', $order);
        return $order;
    }

    /**
     * Delete single order
     *
     * @param  none
     * @return none
     */
    public function deleteAction()
    {
        if ($order = $this->_initOrder()) {
            try {
                if ($this->removeOrderRelatedItems($order)) {
                    $message = Mage::helper('novalnet_payment')->__('Order was successfully deleted');
                    $url = Mage::helper('adminhtml')->getUrl('adminhtml/novalnetpayment_sales_order/index');
                    Mage::getSingleton('adminhtml/session')->addSuccess($message);
                    $this->_redirectUrl($url);
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/edit', array('id' => $this->getRequest()->getParam('order_ids')));
            }
        }
        $this->_redirectUrl(Mage::helper('adminhtml')->getUrl('adminhtml/novalnetpayment_sales_order/index'));
    }

    /**
     * Delete multiple orders
     *
     * @param  none
     * @return none
     */
    public function massDeleteAction()
    {
        $orderIds = $this->getRequest()->getParam('order_ids');
        $adminSession = Mage::getSingleton('adminhtml/session');

        if (!is_array($orderIds)) {
            $adminSession->addError(Mage::helper('novalnet_payment')->__('Please select the orders'));
        } else {
            try {
                foreach ($orderIds as $orderId) {
                    $order = Mage::getModel('sales/order')->load($orderId);
                    $this->removeOrderRelatedItems($order);
                }
                $message = Mage::helper('novalnet_payment')->__('Total of %d order(s) were successfully deleted', count($orderIds));
                $adminSession->addSuccess($message);
            } catch (Exception $e) {
                $adminSession->addError($e->getMessage());
            }
        }
        $this->_redirectUrl(Mage::helper('adminhtml')->getUrl('adminhtml/novalnetpayment_sales_order/index'));
    }

    /**
     * Delete the order related informations (invoice, creditmemo, shipment)
     *
     * @param  Varien_Object $order
     * @return boolean
     */
    public function removeOrderRelatedItems($order)
    {
        // delete order invoice
        $this->removeInvoice($order);
        // delete order creditmemo
        $this->removeCreditmemo($order);
        // delete order shipment
        $this->removeShipment($order);
        //delete order
        $order->delete();
        return true;
    }

    /**
     * delete the order invoice
     *
     * @param  Varien_Object $order
     * @return none
     */
    public function removeInvoice($order)
    {
        $collection = $order->getInvoiceCollection();
        foreach ($collection as $invoice) {
            $items = $invoice->getAllItems();
            foreach ($items as $item) {
                $item->delete();
            }
            $invoice->delete();
        }
    }

    /**
     * delete the order creditmemo
     *
     * @param  Varien_Object $order
     * @return none
     */
    public function removeCreditmemo($order)
    {
        $collection = $order->getCreditmemosCollection();
        foreach ($collection as $creditmemo) {
            $items = $creditmemo->getAllItems();
            foreach ($items as $item) {
                $item->delete();
            }
            $creditmemo->delete();
        }
    }

    /**
     * delete the order shipment
     *
     * @param  Varien_Object $order
     * @return none
     */
    public function removeShipment($order)
    {
        $collection = $order->getShipmentsCollection();
        foreach ($collection as $shipment) {
            $items = $shipment->getAllItems();
            foreach ($items as $item) {
                $item->delete();
            }
            $shipment->delete();
        }
    }

    /**
     * Check admin permissions for this controller
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('novalnetpayment_sales_deleteorder');
    }
}
