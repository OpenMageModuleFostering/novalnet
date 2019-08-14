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
require_once 'Mage' . DS . 'Adminhtml' . DS . 'controllers' . DS . 'Sales' . DS . 'OrderController.php';

class Novalnet_Payment_Adminhtml_Sales_OrderController extends Mage_Adminhtml_Sales_OrderController
{

    var $moduleName = 'novalnet_payment';

    /**
     * Init layout, menu and breadcrumb
     *
     * @return Mage_Adminhtml_Sales_OrderController
     */
    protected function _initAction()
    {
        $this->loadLayout()
                ->setUsedModuleName($this->moduleName)
                ->_setActiveMenu('novalnet')
                ->_addBreadcrumb($this->__('Novalnet'), $this->__('Orders'));

        return $this;
    }

    /**
     * Orders grid
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('Novalnet'))->_title($this->__('Orders'));

        $this->_initAction()
                ->renderLayout();
    }

    /**
     * Set transactionstatus grid in sales order
     *
     */
    public function transactionStatusGridAction()
    {
        $this->_initOrder();
        $this->getResponse()->setBody(
                Mage::getBlockSingleton('novalnet_payment/adminhtml_sales_order_view_tab_transactionStatus')->toHtml()
        );
    }

    /**
     * Set transactionoverview grid in sales order
     *
     */
    public function transactionOverviewGridAction()
    {
        $this->_initOrder();
        $this->getResponse()->setBody(
                Mage::getBlockSingleton('novalnet_payment/adminhtml_sales_order_view_tab_transactionOverview')->toHtml()
        );
    }

    /**
     * Create sales order block for Novalnet payments
     *
     */
    public function gridAction()
    {
        $this->getResponse()->setBody(
                $this->getLayout()->createBlock('novalnet_payment/adminhtml_sales_order_grid')->toHtml()
        );
    }

    /**
     * Invoice update order
     *
     */
    public function invoiceupdateAction()
    {
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

    /**
     * Order confirmation process for Novalnet payments (Prepayment & Invoice)
     *
     */
    public function novalnetconfirmAction()
    {
        $order = $this->_initOrder();
        if ($order) {
            try {
                $payment = $order->getPayment();
                $paymentObj = $payment->getMethodInstance();
                $paymentCode = $paymentObj->getCode();
                $helper = Mage::helper('novalnet_payment');
                $responseCodeApproved = Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED;
                $request = new Varien_Object();
                $storeId = $payment->getOrder()->getStoreId();
                $customerId = $payment->getOrder()->getCustomerId();
                $lastTranId = $helper->makeValidNumber($payment->getLastTransId());
                $request = $this->_getVendorParams($request, $payment);
                $request->setTid($lastTranId)
                        ->setStatus($responseCodeApproved)
                        ->setEditStatus(true);
                $loadTransStatus = $helper->loadTransactionStatus($lastTranId);
                $transStatus = $loadTransStatus->getTransactionStatus();
                if (!in_array(NULL, $request->toArray()) && !empty($transStatus)
                        && $transStatus != $responseCodeApproved) {
                    $payportUrl = $helper->getPayportUrl('paygate');
                    $buildNovalnetParam = http_build_query($request->getData());
                    $response = $paymentObj->setRawCallRequest($buildNovalnetParam, $payportUrl);
                    if ($response->getStatus() == $responseCodeApproved) {
                        $magentoVersion = $helper->getMagentoVersion();
                        $transMode = (version_compare($magentoVersion, '1.6', '<'))
                                    ? false : true;
                        $loadTransStatus->setTransactionStatus($responseCodeApproved)
                                ->save();

                        if ($paymentCode == Novalnet_Payment_Model_Config::NN_INVOICE) {
                            if ($order->canInvoice()) {
                                $this->saveInvoice($order, $lastTranId);
                                $payment->setTransactionId($lastTranId)
                                        ->setIsTransactionClosed($transMode);
                                $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, null, false);
                                $transaction->setParentTxnId(null)
                                        ->save();
                            }
                        }
                    }
                    $paymentObj->logNovalnetTransactionData($request, $response, $payment->getLastTransId(), $customerId, $storeId);
                }
                if ($response->getStatus() != $responseCodeApproved) {
                    $this->_getSession()->addSuccess(
                            $this->__('There was an error in refund request. Status Code : ' . $response->getStatus())
                    );
                } else {
                    $this->_getSession()->addSuccess(
                            $this->__('The order has been updated.')
                    );
                }
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            }
            $this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
        }
    }

    /**
     * Save order invoice
     *
     * @param varien_object $order
     * @param int $txnId
     */
    protected function saveInvoice($order, $txnId)
    {
        $invoice = $order->prepareInvoice();
        $invoice->setTransactionId($txnId);
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE)
                ->register();
        $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_OPEN)
                ->save();
        Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();
    }

    /**
     * Set the Order basic params
     *
     * @param varien_object $request
     * @param varien_object $payment
     * @return mixed
     */
    private function _getVendorParams($request, $payment = NULL)
    {
        //get the Basic Params Based on store
        $getresponseData = NULL;
        if ($payment) {
            $getresponseData = unserialize($payment->getAdditionalData());
        }
        $request->setVendor($getresponseData['vendor'])
                ->setAuthCode($getresponseData['auth_code'])
                ->setProduct($getresponseData['product'])
                ->setTariff($getresponseData['tariff'])
                ->setKey($getresponseData['key']);
        return $request;
    }

}
