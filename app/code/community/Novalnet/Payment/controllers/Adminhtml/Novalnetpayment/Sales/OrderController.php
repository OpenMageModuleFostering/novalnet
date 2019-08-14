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
require_once 'Mage' . DS . 'Adminhtml' . DS . 'controllers' . DS . 'Sales' . DS . 'OrderController.php';

class Novalnet_Payment_Adminhtml_Novalnetpayment_Sales_OrderController extends Mage_Adminhtml_Sales_OrderController
{
    /**
     * Init layout, menu and breadcrumb
     *
     * @param  none
     * @return Mage_Adminhtml_Sales_OrderController
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->setUsedModuleName('novalnet_payment')
            ->_setActiveMenu('novalnet')
            ->_addBreadcrumb($this->__('Novalnet'), $this->__('Orders'));

        return $this;
    }

    /**
     * Novalnet payments order grid
     *
     * @param  none
     * @return none
     */
    public function indexAction()
    {
        $this->_title($this->__('Novalnet'))->_title($this->__('Orders'));

        $this->_initAction()
            ->renderLayout();
    }

    /**
     * Create sales order block for Novalnet payments
     *
     * @param  none
     * @return none
     */
    public function gridAction()
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('novalnet_payment/adminhtml_sales_order_grid')->toHtml()
        );
    }

    /**
     * Set transactionoverview grid in sales order view page
     *
     * @param  none
     * @return none
     */
    public function transactionOverviewGridAction()
    {
        $this->_initOrder();
        $this->getResponse()->setBody(
            Mage::getBlockSingleton('novalnet_payment/adminhtml_sales_order_view_tab_transactionOverview')->toHtml()
        );
    }

    /**
     * Set transactionoverview grid in sales order view page
     *
     * @param  none
     * @return none
     */
    public function transactionTracesGridAction()
    {
        $this->_initOrder();
        $this->getResponse()->setBody(
            Mage::getBlockSingleton('novalnet_payment/adminhtml_sales_order_view_tab_transactionTraces')->toHtml()
        );
    }

    /**
     * Order confirm process for Novalnet invoice payments (Invoice/Prepayment)
     *
     * @param  none
     * @return none
     */
    public function confirmAction()
    {
        $order = $this->_initOrder(); // Get order object
        $paymentObj = $order->getPayment()->getMethodInstance(); // Get payment method instance
        $this->code = $paymentObj->getCode(); // Get payment method code
        $this->helper = Mage::helper('novalnet_payment'); // Novalnet payment helper
        // Get payment last transaction id
        $transactionId = $this->helper->makeValidNumber($order->getPayment()->getLastTransId());
        // Build confirm payment request
        $request = $this->helper->getModel('Service_Api_Request')
            ->getprocessVendorInfo($order->getPayment()); // Get Novalnet authentication Data
        $request->setTid($transactionId)
            ->setStatus(100)
            ->setEditStatus(true);
        $response = $paymentObj->postRequest($request);  // Send confirm payment request
        $this->validateConfirmResponse($response, $order, $transactionId); // Validate the payport response
         // Save the transaction traces
        $responseModel = $this->helper->getModel('Service_Api_Response');
        $responseModel->logTransactionTraces($request, $response, $order, $transactionId);
        if ($response->getTidStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $this->_getSession()->addSuccess($this->__('The order has been updated.'));
        } else {
            $message = $this->__('Error in your process request. Status Code : ' . $response->getStatus());
            $this->_getSession()->addError($message);
        }
        $this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
    }

    /**
     * Validate the confirm payment response data
     *
     * @param  Varien_Object $request
     * @param  Varien_Object $order
     * @param  string        $transactionId
     * @return none
     */
    public function validateConfirmResponse($response, $order, $transactionId)
    {
        if ($response->getTidStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $payment = $order->getPayment(); // Get payment object

            // Save payment additional transaction details
            $data = unserialize($payment->getAdditionalData());
            $data['captureTid'] = $transactionId;
            $data['CaptureCreateAt'] = Mage::getModel('core/date')->date('Y-m-d H:i:s');
            $payment->setAdditionalData(serialize($data))->save();

            // Add transaction status information
            $transactionStatus = $this->helper->getModel('Mysql4_TransactionStatus')
                ->loadByAttribute('transaction_no', $transactionId);
            $transactionStatus->setTransactionStatus($response->getTidStatus())->save();

            // Create order invoice
            if ($this->code == Novalnet_Payment_Model_Config::NN_INVOICE && $order->canInvoice()) {
                $this->saveOrderInvoice($order, $transactionId);
            } elseif ($this->code == Novalnet_Payment_Model_Config::NN_PREPAYMENT) {
                $captureOrderStatus = Mage::getStoreConfig('novalnet_global/order_status_mapping/order_status', $order->getStoreId())
                    ? Mage::getStoreConfig('novalnet_global/order_status_mapping/order_status', $order->getStoreId())
                    : Mage_Sales_Model_Order::STATE_PROCESSING;
                $message = Mage::helper('novalnet_payment')->__('The transaction has been confirmed');
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $captureOrderStatus, $message, true)->save();
            }
        }
    }

    /**
     * Save order invoice
     *
     * @param  varien_object $order
     * @param  int           $transactionId
     * @return none
     */
    protected function saveOrderInvoice($order, $transactionId)
    {
        // Create order invoice
        $invoice = $order->prepareInvoice();
        $invoice->setTransactionId($transactionId);
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE)
            ->register();
        $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_OPEN)
            ->save();
        Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();

        $captureMode = (version_compare($this->helper->getMagentoVersion(), '1.6', '<')) ? false : true;
        $payment = $order->getPayment(); // Get payment object
        // Add capture transaction
        $payment->setTransactionId($transactionId)
            ->setIsTransactionClosed($captureMode);
        $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, null, false);
        $transaction->setParentTxnId(null)
            ->save();
    }

}
