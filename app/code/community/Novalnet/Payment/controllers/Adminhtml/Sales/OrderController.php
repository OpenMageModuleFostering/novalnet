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
     * Order confirmation process for Novalnet payments (Prepayment & Invoice)
     *
     */
    public function novalnetconfirmAction()
    {
        $order = $this->_initOrder();
        $orderItems = $order->getAllItems();
        $helper = Mage::helper('novalnet_payment');
        $nominalItem = $helper->checkNominalItem($orderItems);
        if ($order) {
            try {
                $payment = $order->getPayment();
                $paymentObj = $payment->getMethodInstance();
                $paymentCode = $paymentObj->getCode();
                $responseCodeApproved = Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED;
                $request = new Varien_Object();
                $storeId = $payment->getOrder()->getStoreId();                
                $lastTranId = $helper->makeValidNumber($payment->getLastTransId());
                $paymentObj->assignOrderBasicParams($request, $payment, $storeId, $nominalItem);
                $request->setTid($lastTranId)
                        ->setStatus($responseCodeApproved)
                        ->setEditStatus(true);
                $loadTransStatus = Mage::helper('novalnet_payment')->loadTransactionStatus($lastTranId);
                $transStatus = $loadTransStatus->getTransactionStatus();
                if (!in_array(NULL, $request->toArray()) && !empty($transStatus)
                        && $transStatus != $responseCodeApproved) {
                    $buildNovalnetParam = http_build_query($request->getData());
                    $payportUrl = $helper->getPayportUrl('paygate');
                    $response = Mage::helper('novalnet_payment/AssignData')->setRawCallRequest($buildNovalnetParam, $payportUrl);
                    $responseStatus = $response->getStatus();
                    if ($responseStatus == $responseCodeApproved) {
                        $captureMode = (version_compare($helper->getMagentoVersion(), '1.6', '<'))
                                    ? false : true;
                        $loadTransStatus->setTransactionStatus($responseCodeApproved)
                                ->save();
			$data = unserialize($payment->getAdditionalData());
			$data['captureTid'] = $lastTranId;
                        $data['CaptureCreateAt'] = Mage::getModel('core/date')->date('Y-m-d H:i:s');
                        $payment->setAdditionalData(serialize($data))
                                        ->save();
                        if ($paymentCode == Novalnet_Payment_Model_Config::NN_INVOICE) {
                            $order = $payment->getOrder();
                            if ($order->canInvoice()) {
                                $this->saveInvoice($order, $lastTranId);
                            }
                            $payment->setTransactionId($lastTranId)
                                    ->setIsTransactionClosed($captureMode);
                            $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, null, false);
                            $transaction->setParentTxnId(null)
                                    ->save();
                        }
                    }
		    $this->setOrderStatus($order, 'confirmStatus');
    		    $customerId = $payment->getOrder()->getCustomerId();
                    $paymentObj->logNovalnetTransactionData($request, $response, $payment->getLastTransId(), $customerId, $storeId);
                }
                if ($responseStatus != $responseCodeApproved) {
                    $this->_getSession()->addSuccess(
                            $this->__('There was an error in refund request. Status Code : ' . $responseStatus)
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
     * @param string $paymentCode
     */
    protected function saveInvoice($order, $txnId,$paymentCode = NULL)
    {
        $paid = $paymentCode ? Mage_Sales_Model_Order_Invoice::STATE_PAID : Mage_Sales_Model_Order_Invoice::STATE_OPEN;
        $invoice = $order->prepareInvoice();
        $invoice->setTransactionId($txnId);
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE)
                ->register();
        $invoice->setState($paid)
                ->save();
        Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();
         if ($paymentCode != NULL) {
                $magentoVersion = Mage::helper('novalnet_payment')->getMagentoVersion();
                $transMode = (version_compare($magentoVersion, '1.6', '<'))
                                            ? false : true;
                $payment = $order->getPayment();
                $payment->setTransactionId($txnId)
                        ->setIsTransactionClosed($transMode);
                $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, null, false);
                $transaction->setParentTxnId(null)
                        ->save();
        }
    }

    /**
     * Amount update process
     *
     */
    public function amountupdateAction()
    {
        $amountChanged = $this->getRequest()->getParam('amount_change');
        $invoiceDuedate = $this->getRequest()->getParam('invoice_duedate');
        $finalAmount = str_replace(array('.', ','), "", $amountChanged);
        $rawAmount = ($finalAmount / 100);

        $order = $this->_initOrder();
        $helper = Mage::helper('novalnet_payment');
        $incrementId = $order->getIncrementId();
        $currency = $order->getOrderCurrencyCode();
        $callbackTrans = $helper->loadCallbackValue($incrementId);
        $callbackValue = $callbackTrans && $callbackTrans->getCallbackAmount()!= NULL ? ($callbackTrans->getCallbackAmount() / 100) : '';

        try {
            if (empty($rawAmount) || !is_numeric($rawAmount) || $rawAmount < $callbackValue) {
                ($rawAmount < $callbackValue) ? Mage::throwException($helper->__('Customer already paid amount is ').$currency. ' ' .$callbackValue) : Mage::throwException('Enter the valid amount');
            }

            if ($order) {
                try {
                    $orderId = $order->getId();
                    $payment = $order->getPayment();
                    $paymentObj = $payment->getMethodInstance();
                    $paymentCode = $paymentObj->getCode();
                    $lastTranId = $helper->makeValidNumber($payment->getLastTransId());                    
                    $responseCodeApproved = Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED;
                    $storeId = $order->getStoreId();
                    $request = new Varien_Object();
                    $paymentObj->assignOrderBasicParams($request, $payment, $storeId);
                    $request->setTid($lastTranId)
                            ->setStatus($responseCodeApproved)
                            ->setEditStatus(true)
                            ->setUpdateInvAmount(1)
                            ->setAmount($amountChanged);
                    if ($invoiceDuedate != '') {
                        $request->setDueDate($invoiceDuedate);
                    }

                    $amountChanged = $rawAmount;
                    $loadTransStatus = $helper->loadTransactionStatus($lastTranId);
                    $transStatus = $loadTransStatus->getTransactionStatus();
            // set transaction amount
            $loadTransStatus->setAmount($amountChanged)
                                   ->save();

                    if (!in_array(NULL, $request->toArray()) && !empty($transStatus)) {
                        $buildNovalnetParam = http_build_query($request->getData());
                        $payportUrl = $helper->getPayportUrl('paygate');
                        $dataHelper =  Mage::helper('novalnet_payment/AssignData');
                        $response = $dataHelper->setRawCallRequest($buildNovalnetParam, $payportUrl);

                        if ($response->getStatus() == $responseCodeApproved) {
                            // make capture transaction open for lower versions to make refund
                            if (version_compare($helper->getMagentoVersion(), '1.6', '<')) {
                                $payment->setIsTransactionClosed(false)
                                        ->save();
                            }
                            $countAmount = $helper->getAmountCollection($orderId, NULL, NULL);
                            $modNovalamountchanged = $helper->getModelAmountchanged();
                            if ($paymentCode == Novalnet_Payment_Model_Config::NN_INVOICE || $paymentCode == Novalnet_Payment_Model_Config::NN_PREPAYMENT) {
                                $data = unserialize($payment->getAdditionalData());
                                if($invoiceDuedate != '')
                                {
                                    $note = explode('|',$data['NnNote']);
                                    $formatDate = Mage::helper('core')->formatDate($invoiceDuedate);
                                    $note[0] = "Due Date: <b>$formatDate</b>";
                                    $data['NnNote'] = implode('|',$note);
                                }
                                $data['NnNoteAmount'] = $dataHelper->getBankDetailsAmount($amountChanged);
                                $payment->setAdditionalData(serialize($data))
                                        ->save();
                                $modNovalamountchanged->setInvoiceDuedate($invoiceDuedate);
                            }
                            $countAmount = $helper->getAmountCollection($orderId, NULL, NULL);
                            $modNovalamountchanged = $countAmount ? $helper->getModelAmountchanged()->load($orderId, 'order_id')
                                        : $helper->getModelAmountchanged();
                            $modNovalamountchanged->setOrderId($orderId)
                                    ->setAmountChanged($amountChanged)
                                    ->setAmountDatetime($helper->getCurrentDateTime())
                                    ->save();
                             if ($amountChanged == $callbackValue && $paymentCode == Novalnet_Payment_Model_Config::NN_PREPAYMENT) {
                 $this->setOrderStatus($order);
                                 $this->saveInvoice($order,$lastTranId,$paymentCode);
                                 $this->setNNTotalPaid($order,$amountChanged);
                             }

                             if ($paymentCode == Novalnet_Payment_Model_Config::NN_INVOICE) {
                                $payment->setAmountRefunded(0)
                                        ->setBaseAmountRefunded(0)
                                        ->save();
                                $order->setTotalRefunded(0);
                                $order->setBaseTotalRefunded(0);
                                $order->save();
                                $this->setNNTotalPaid($order,$amountChanged);

                                if ($amountChanged == $callbackValue) {
                                    $this->setOrderStatus($order);
                                    $invoice = $order->getInvoiceCollection()->getFirstItem();
                                    $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);
                                    $invoice->save();
                                }
                            }
                        } else {
                            Mage::throwException($response->getStatusDesc());
                        }
                        $customerId = $order->getCustomerId();
                        $paymentObj->logNovalnetTransactionData($request, $response, $lastTranId, $customerId, $storeId);
                        $this->_getSession()->addSuccess(
                                $this->__('Transaction amount updated successfully.')
                        );
                    }
                } catch (Mage_Core_Exception $e) {
                    $this->_getSession()->addError($e->getMessage());
                } catch (Exception $e) {
                    $this->_getSession()->addError($e->getMessage());
                }
                $this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
            }
       } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
       }
    }

    /**
     * Set the total paid amount after amount update
     *
     * @param varien_object $order
     * @param int $amountChanged
     */
    private function setNNTotalPaid($order,$amountChanged)
    {
        $order->setTotalPaid($amountChanged)
              ->setBaseTotalPaid($amountChanged)
              ->save();
    }

    /**
     * Set callback status
     *
     * @param varien_object $order
     * @return null
     */
    private function setOrderStatus($order, $confirmStatus = NULL)
    {
        $payment = $order->getPayment();
        $storeId = $payment->getOrder()->getStoreId();
        $paymentObj = $payment->getMethodInstance();
        $orderStatus = $confirmStatus 
			? $paymentObj->_getConfigData('order_status', true)
			: $paymentObj->_getConfigData('order_status_after_payment', '', $storeId);
        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $orderStatus, '', true)->save();
    }
}
