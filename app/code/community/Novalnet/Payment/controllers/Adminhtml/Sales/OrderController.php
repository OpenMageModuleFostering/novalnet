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

    public function novalnetconfirmAction() {
		$order = $this->_initOrder();
        if ($order) {
            try {
				$payment = $order->getPayment();
				$paymentObj = $payment->getMethodInstance();
				$getTid = $payment->getTransactionId();
				$responseCodeApproved = Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED;
				$request = new Varien_Object();
				$storeId = $payment->getOrder()->getStoreId();
				$customerId = $payment->getOrder()->getCustomerId();
				$amount = Mage::helper('novalnet_payment')->getFormatedAmount($payment->getOrder()->getBaseGrandTotal());
				$lastTranId = Mage::helper('novalnet_payment')->makeValidNumber($payment->getLastTransId());
				$paymentObj->assignOrderBasicParams($request, $payment, $storeId);
				$request->setTid($lastTranId)
						->setStatus($responseCodeApproved)
						->setEditStatus(true);
				$loadTransStatus = Mage::helper('novalnet_payment')->loadTransactionStatus($lastTranId);
				$transStatus = $loadTransStatus->getTransactionStatus();
				if (!in_array(NULL, $request->toArray()) && !empty($transStatus) && $transStatus != $responseCodeApproved) {
					$buildNovalnetParam = http_build_query($request->getData());
					$response = Mage::helper('novalnet_payment/AssignData')->setRawCallRequest($buildNovalnetParam, Novalnet_Payment_Model_Config::PAYPORT_URL);
					if ($response->getStatus() == $responseCodeApproved) {
						$loadTransStatus->setTransactionStatus($responseCodeApproved)
										->save();
						$payment->setTransactionId($lastTranId);
						$transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, null, false);
						$transaction->setIsClosed(true)
									->save();
					}
					$paymentObj->logNovalnetTransactionData($request, $response, $payment->getLastTransId(), $customerId, $storeId);
				}
				if ($response->getStatus() != $responseCodeApproved) {
					$this->_getSession()->addSuccess(
                        $this->__('There was an error in refund request. Status Code : '. $response->getStatus())
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

	public function sepasignedAction() {
		 $sepa_date = $this->getRequest()->getParam('sepa_date');
		 $order = $this->_initOrder();

		 if ($order && $sepa_date) {
            try {
				$payment = $order->getPayment();
				$paymentObj = $payment->getMethodInstance();
				$lastTranId = Mage::helper('novalnet_payment')->makeValidNumber($payment->getLastTransId());
				$lastTranId = trim($lastTranId);
				$storeId = $payment->getOrder()->getStoreId();
				$vendorId = Mage::helper('novalnet_payment')->getModel('novalnetSepa')->_getConfigData('merchant_id', true, $storeId);
				$authcode = Mage::helper('novalnet_payment')->getModel('novalnetSepa')->_getConfigData('auth_code', true, $storeId);
				$sepaMandateConfirm = Novalnet_Payment_Model_Config::SEPA_MANDATE_CONFIRMATION;
				$data = unserialize($payment->getAdditionalData());
				$requestData = new Varien_Object();
				$request = '<?xml version="1.0" encoding="UTF-8"?>';
				$request .= '<nnxml><info_request>';
				$request .= '<vendor_id>' . trim($vendorId) . '</vendor_id>';
				$request .= '<vendor_authcode>' . trim($authcode) . '</vendor_authcode>';
				$request .= '<request_type>' . $sepaMandateConfirm . '</request_type>';
				$request .= '<mandate_signature_date>' . $sepa_date . '</mandate_signature_date>';
				$request .= '<order_no>' . $payment->getOrder()->getIncrementId() . '</order_no>';
				$request .= '<customer_no>' . $data['NnNcNo'] . '</customer_no>';
				$request .= '<tid>' . $lastTranId . '</tid>';
				$request .= '</info_request></nnxml>';

				if ($vendorId && $authcode) {
					$response = $paymentObj->_setNovalnetRequestCall($request, Novalnet_Payment_Model_Config::INFO_REQUEST_URL, 'XML');
					$requestData->setData($request);
				} else {
					$this->_getSession()->addError($this->__('Basic parameter not valid'));
				}

				$responseCodeApproved = Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED;
				$loadTransStatus = Mage::helper('novalnet_payment')->loadTransactionStatus($lastTranId);

				if ($response && $response->getStatus() == $responseCodeApproved) {
					$data['NnSepaSigned'] = 0;
					$data['NnSepaDueDate'] = $sepa_date;

					$payment->setAdditionalData(serialize($data))
                            ->save();

					//authorize type closed
					$loadTransStatus->setTransactionStatus($responseCodeApproved)
										->save();
                    $payment->setTransactionId($payment->getLastTransId());
					$transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, null, false);
					$transaction->setIsClosed(true)
									->save();

					//order transaction closed
					$order->setIsTransactionClosed(true)
							->save();

                    $payment->setTransactionId($lastTranId."-capture")
							->setParentTransactionId($lastTranId)
							->capture(null)
                            ->setIsTransactionClosed(true)
                            ->save();
					//set order status to processing
					$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)
						 ->save();
					$this->_getSession()->addSuccess(
						   $this->__('The order has been updated.')
					);
				} else {
					 $this->_getSession()->addError($response->getStatusMessage());
				}
				$paymentObj->logNovalnetTransactionData($requestData, $response, $payment->getLastTransId(), $data['NnNcNo'], $storeId);
			} catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            }
            $this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
         }
	}
}
