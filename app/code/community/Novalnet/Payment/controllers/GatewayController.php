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
class Novalnet_Payment_GatewayController extends Mage_Core_Controller_Front_Action {

    /**
     * when customer select payment method
     */
    public function redirectAction() {
        try {
            $session = $this->_getCheckout();
            $order = $this->_getOrder();
            $items = Mage::getModel('sales/quote')->load($session->getQuoteId())->getItemsQty();

            //$payment = $this->_getPayment();
            $paymentObj = $this->_getPaymentObject();
	    $session->getQuote()->setIsActive(true)->save();

            if ($session->getLastRealOrderId() && $items) {
                if ($order->getPayment()->getAdditionalInformation($paymentObj->getCode() . '_redirectAction') != 1) {
                    $order->getPayment()->setAdditionalInformation($paymentObj->getCode() . '_redirectAction', 1);
                    $state = Mage_Sales_Model_Order::STATE_HOLDED; //set State,Status to HOLD
                    $status = Mage_Sales_Model_Order::STATE_HOLDED;
                    $order->setState($state, $status, $this->_getNovalnetHelper()->__('Customer was redirected to Novalnet'), false)->save();
                }
            } else {
                $this->_redirect('checkout/cart');
            }
            $this->getResponse()->setBody(
                    $this->getLayout()
                            ->createBlock(Novalnet_Payment_Model_Config::NOVALNET_REDIRECT_BLOCK)
                            ->setOrder($order)
                            ->toHtml()
            );
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * When Novalnet returns after successful payment
     * The order information at this point is in POST
     */
    public function returnAction() {
        $session = $this->_getCheckout();
        $response = $this->getRequest()->getParams();
        $this->doTransactionOrderLog($response);
        $status = $this->_checkReturnedData();

        //OnFailure
        if (!$response['order_no'] || !$status) {
            $session->getQuote()->setIsActive(false)->save();
            Mage::getSingleton('core/session')->addError("Payment error");
            $this->_redirect('checkout/onepage/failure');
        } else {
            //OnSuccess
            $session->getQuote()->setIsActive(false)->save();
            $this->_redirect('checkout/onepage/success');
        }
    }

    /**
     * When Customer cancelled/error in the payment
     *
     */
    public function errorAction() {
        $order = $this->_getOrder();
        $session = $this->_getCheckout();
        $payment = $order->getPayment();
        $paymentObj = $payment->getMethodInstance();
	
	$order->getPayment()->getMethodInstance()->unsetFormMethodSession();		
        $session->getQuote()->setIsActive(false)->save();
        $response = $this->getRequest()->getParams();
        $this->doTransactionOrderLog($response);  // Save return error response
        $statusMessage = $response['status_text'];
        $paystatus = "<b><font color='red'>" . $this->_getNovalnetHelper()->__('Payment Failed') . "</font> - " . $statusMessage . "</b>";

        //Unhold an order:-
        if ($order->canUnhold()) {
            $order->unhold()->save();
        }

        if ($paymentObj->_getConfigData('save_cancelled_tid') == 1) {
            $payment->setLastTransId($response["tid"]);
        }
        $data = unserialize($payment->getAdditionalData());
        $data['NnComments'] = $paystatus;
        $payment->setAdditionalData(serialize($data));

        //Cancel the order:-
        $order->registerCancellation($statusMessage);
        if ($order->getPayment()->getAdditionalInformation($paymentObj->getCode() . '_errorAction') != 1) {
            $order->getPayment()->setAdditionalInformation($paymentObj->getCode() . '_errorAction', 1);
            Mage::getSingleton('core/session')->addError($statusMessage);
        }
        $order->save();
        $this->unsetNovalnetSessionData($paymentObj->getCode());
        $this->_redirect('checkout/onepage/failure', array('_secure' => true));
    }

    /**
     * Checking Post variables.
     *
     */
    protected function _checkReturnedData() {
        try {
            $status = false;
            if (!$this->getRequest()->isPost()) {
                $this->norouteAction();
                return;
            }

            $order = $this->_getOrder();
            $payment = $order->getPayment();
            $paymentObj = $payment->getMethodInstance();
            $this->_getCheckout()->getQuote()->setIsActive(true)->save();
            //Get response
            $response = Mage::helper('novalnet_payment/AssignData')->replaceParamsBasedOnPayment($this->getRequest()->getParams(), $paymentObj->getCode(), 'response');
            $dataObj = new Varien_Object($response);

            //Unhold an order:-
            if ($order->canUnhold()) {
                $order->unhold()->save();
            }

            $this->unsetNovalnetSessionData($paymentObj->getCode());
	    $order->getPayment()->getMethodInstance()->unsetFormMethodSession();
            $authorizeKey = $paymentObj->_getConfigData('password', true);
            if ($response['status'] == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED && $paymentObj->getCode() != Novalnet_Payment_Model_Config::NN_CC3D) {
                $response['status'] = $this->_getNovalnetHelper()->checkParams($response, $authorizeKey);
            }

            //success
            if (($paymentObj->getCode() == Novalnet_Payment_Model_Config::NN_PAYPAL && $response['status'] == Novalnet_Payment_Model_Config::PAYPAL_PENDING_CODE) || $response['status'] == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                //set Novalnet Mode
                $serverResponse = ($paymentObj->getCode() != Novalnet_Payment_Model_Config::NN_CC3D) ? $this->_getNovalnetHelper()->getDecodedParam($response['test_mode'], $authorizeKey) : $response['test_mode'];
                $shopMode = $paymentObj->_getConfigData('live_mode');
                $testMode = (((isset($serverResponse) && $serverResponse == 1) || (isset($shopMode) && $shopMode == 0)) ? 1 : 0 );
                $data = unserialize($payment->getAdditionalData());
                $data['NnTestOrder'] = $testMode;
                $amount = is_numeric($response['amount']) ? $response['amount'] : $this->_getNovalnetHelper()->getDecodedParam($response['amount'], $authorizeKey);
                $formatedAmount = $this->_getNovalnetHelper()->getFormatedAmount($amount, 'RAW');
                $transMode = ($paymentObj->getCode() == Novalnet_Payment_Model_Config::NN_CC3D) ? false : true;

                $payment->setStatus(Novalnet_Payment_Model_Payment_Method_Abstract::STATUS_SUCCESS)
                        ->setStatusDescription($this->_getNovalnetHelper()->__('Payment was successful.'))
                        ->setAdditionalData(serialize($data))
                        ->setIsTransactionClosed($transMode)
                        ->save();
                $order->setPayment($payment);
                $order->save(); //Save details in order

                $txnId = $response['tid'];
                $getAdminTransaction = $this->_getPaymentObject()->doNovalnetStatusCall($response['tid']);
                $magentoVersion = $this->_getNovalnetHelper()->getMagentoVersion();
				$captureMode = (version_compare($magentoVersion, '1.6', '<')) ? false : true;
                if ($getAdminTransaction->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {

                    $payment->setTransactionId($txnId) // Add capture text to make the new transaction
                            ->setParentTransactionId(null)
                            ->setIsTransactionClosed($captureMode)
                            ->setLastTransId($txnId)
                            ->capture(null)
                            ->save();
                } else {

                    $payment->setTransactionId($txnId)
                            ->setLastTransId($txnId)
                            ->setParentTransactionId(null)
                            ->authorize(true, $formatedAmount)
                            ->save();
                }

                if ($order->getPayment()->getAdditionalInformation($paymentObj->getCode() . '_successAction') != 1) {
                    $order->getPayment()->setAdditionalInformation($paymentObj->getCode() . '_successAction', 1);
                    $setOrderAfterStatus = $paymentObj->_getConfigData('order_status_after_payment') ? $paymentObj->_getConfigData('order_status_after_payment') : Mage_Sales_Model_Order::STATE_PROCESSING; // If after status is empty set default status
                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $setOrderAfterStatus, $this->_getNovalnetHelper()->__('Customer successfully returned from Novalnet'), true
                    )->save();

                    //sendNewOrderEmail
                    if (!$order->getEmailSent() && $order->getId()) {
                        try {
                            $order->sendNewOrderEmail()
                                    ->setEmailSent(true)
                                    ->save();
                        } catch (Exception $e) {
                            Mage::throwException($this->_getNovalnetHelper()->__('Cannot send new order email.'));
                        }
                    }

                    $paymentObj->doNovalnetPostbackCall($dataObj); //Do Second Call
                    $statusText = ($response['status_text']) ? $response['status_text'] : $this->_getNovalnetHelper()->__('successful');
                    Mage::getSingleton('core/session')->addSuccess($statusText);
                }

                $order->save();
                // Get Admin Transaction status via API
                $getAdminTransaction = $this->_getPaymentObject()->doNovalnetStatusCall($response['tid']);
                $this->doTransactionStatusSave($response, $getAdminTransaction); // Save the Transaction status
                $status = true;
            } else {
                $payment->setStatus(Novalnet_Payment_Model_Payment_Method_Abstract::STATUS_DECLINED);
                $order->setPayment($payment);

                //Cancel the order:-
                $order->registerCancellation($this->_getNovalnetHelper()->__('Payment was not successfull'))
                        ->save();
                $status = false;
            }
            $order->save();
            return $status;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function doTransactionStatusSave($response, $transactionStatus) {
        $order = $this->_getOrder();
        $authorizeKey = $order->getPayment()->getMethodInstance()->_getConfigData('password', true);
        $amount = is_numeric($response['amount']) ? $response['amount'] : $this->_getNovalnetHelper()->getDecodedParam($response['amount'], $authorizeKey);
        $ncNo = (isset($response['nc_no'])) ? $response['nc_no'] : NULL;
        $modNovalTransactionStatus = Mage::getModel('novalnet_payment/transactionstatus');
        $modNovalTransactionStatus->setTransactionNo($response['tid'])
                ->setOrderId($response['order_no'])
                ->setTransactionStatus($transactionStatus->getStatus()) //Novalnet Admin transaction status
                ->setNcNo($ncNo)   //nc number
                ->setCustomerId($this->_getNovalnetHelper()->getCustomerId())
                ->setPaymentName($this->_getPaymentObject()->getCode())
                ->setAmount($this->_getNovalnetHelper()->getFormatedAmount($amount, 'RAW'))
                ->setRemoteIp($this->_getNovalnetHelper()->getRealIpAddr())
                ->setStoreId($this->_getNovalnetHelper()->getMagentoStoreId())
                ->setShopUrl($this->_getNovalnetHelper()->getCurrentSiteUrl())
                ->setCreatedDate($this->_getNovalnetHelper()->getCurrentDateTime())
                ->save();
    }

    public function doTransactionOrderLog($response) {

        $modNovalTransactionOverview = $this->_getNovalnetHelper()->getModelTransactionOverview()->loadByAttribute('order_id', $response['order_no']);

        $modNovalTransactionOverview->setTransactionId($response['tid'])
                ->setResponseData(serialize($response))
                ->setCustomerId($this->_getNovalnetHelper()->getCustomerId())
                ->setStatus($response['status']) //transaction status code
                ->setStoreId($this->_getNovalnetHelper()->getMagentoStoreId())
                ->setShopUrl($this->_getNovalnetHelper()->getCurrentSiteUrl())
                ->save();
    }

    private function unsetNovalnetSessionData($paymentCode) {
        if ($paymentCode == Novalnet_Payment_Model_Config::NN_CC3D) {
            $session = $this->_getCheckout();
		   $session->unsNnCcNumber()
			    ->unsNnCcCvc()
			    ->unsNnCcOwner()
			    ->unsNnCcExpMonth()
			    ->unsNnCcExpYear();
        }
    }

	 /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    public function _getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get Novalnet Helper
     *
     * @return Helper data
     */
    private function _getNovalnetHelper() {
        return Mage::helper('novalnet_payment');
    }

    /**
     * Get Last placed order object
     *
     * @return payment object
     */
    private function _getOrder() {
        return Mage::getModel('sales/order')->loadByIncrementId($this->_getCheckout()->getLastRealOrderId());
    }

    /**
     * Get Current payment object from placed order
     *
     * @return payment object
     */
    private function _getPayment() {
        return $this->_getOrder()->getPayment();
    }

    /**
     * Get Current payment method instance
     *
     * @return payment method instance
     */
    private function _getPaymentObject() {
        return $this->_getPayment()->getMethodInstance();
    }

}
