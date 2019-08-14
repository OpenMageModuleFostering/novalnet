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
class Novalnet_Payment_GatewayController extends Mage_Core_Controller_Front_Action
{

    /**
     * when customer select payment method
     */
    public function redirectAction()
    {
        try {
            $session = $this->_getCheckout();
            $order = $this->_getOrder();
            $quoteId = $session->getQuoteId() ? $session->getQuoteId() : $session->getLastQuoteId();
            $items = Mage::getModel('sales/quote')->load($quoteId)->getItemsQty();

            $paymentObj = $order->getPayment()->getMethodInstance();
            $session->getQuote()->setIsActive(true)->save();
            if ($order->getPayment()->getAdditionalInformation($paymentObj->getCode() . '_redirectAction')
                    != 1) {
                $order->getPayment()->setAdditionalInformation($paymentObj->getCode() . '_redirectAction', 1);
                if ($session->getLastRealOrderId() && $items) {
                    $state = Mage_Sales_Model_Order::STATE_HOLDED; //set State,Status to HOLD
                    $status = Mage_Sales_Model_Order::STATE_HOLDED;
                    $order->setState($state, $status, $this->_getNovalnetHelper()->__('Customer was redirected to Novalnet'), false)->save();
                } else {
                    $this->_redirect('checkout/cart');
                }
                $this->getResponse()->setBody(
                        $this->getLayout()
                                ->createBlock(Novalnet_Payment_Model_Config::NOVALNET_REDIRECT_BLOCK)
                                ->setOrder($order)
                                ->toHtml()
                );
            } else {
                $this->_redirect('checkout/cart');
            }
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
    public function returnAction()
    {
        $session = $this->_getCheckout();
        $response = $this->getRequest()->getParams();
        $dataObj = new Varien_Object($response);
        $this->_getNovalnetHelper()->doTransactionOrderLog($dataObj, $response['order_no']); // Save return success response
        $status = $this->_checkReturnedData();

        if (!$response['order_no'] || !$status) {
            //OnFailure
            $session->getQuote()->setIsActive(false)->save();
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
    public function errorAction()
    {
        $order = $this->_getOrder();
        $session = $this->_getCheckout();
        $payment = $order->getPayment();
        $paymentObj = $payment->getMethodInstance();

        $paymentObj->unsetMethodSession($paymentObj->getCode());
        $session->getQuote()->setIsActive(false)->save();
        $response = $this->getRequest()->getParams();
        $dataObj = new Varien_Object($response);
        $this->_getNovalnetHelper()->doTransactionOrderLog($dataObj, $response['order_no']);  // Save return error response
        //Unhold an order:-
        if ($order->canUnhold()) {
            $order->unhold()->save();
        }

        //Cancel the order:-
        if ($order->getPayment()->getAdditionalInformation($paymentObj->getCode() . '_errorAction')
                != 1) {
            $order->getPayment()->setAdditionalInformation($paymentObj->getCode() . '_errorAction', 1);
            $dataObj = new Varien_Object($response);
            $paymentObj->saveCancelledOrder($dataObj, $payment);
            $statusMessage = ($dataObj->getStatusText() != NULL) ? $dataObj->getStatusText()
                        : $dataObj->getStatusDesc();
            Mage::getSingleton('core/session')->addError($statusMessage);
        }

        $this->_redirect('checkout/onepage/failure', array('_secure' => true));
    }

    /**
     * Recieves Server Response for Direct Paymetns.
     *
     * Redirects to success or failure page.
     */
    public function paymentAction()
    {
        $order = $this->_getOrder();
        $payment = $order->getPayment();
        $paymentObj = $payment->getMethodInstance();

        if ($order->getPayment()->getAdditionalInformation($paymentObj->getCode() . '_paymentAction')
                != 1) {

            $order->getPayment()->setAdditionalInformation($paymentObj->getCode() . '_paymentAction', 1);
            $paymentCode = $paymentObj->getCode();
            $helper = $this->_getNovalnetHelper();
            $methodSession = $this->_getCheckout()->getData($paymentCode);
            if ($paymentCode == Novalnet_Payment_Model_Config::NN_TELEPHONE) {
                $requestData = new Varien_Object();
                $txnId = $methodSession->getNnPhoneTid();
                $option = '<lang>' . strtoupper($helper->getDefaultLanguage()) . '</lang>';
                $result = $paymentObj->doNovalnetStatusCall($txnId, NULL, Novalnet_Payment_Model_Config::NOVALTEL_STATUS, $option, $requestData);

                if ($result) {
                    $result->setTid($txnId);
                    $result->setStatus($result->getNovaltelStatus());
                    $result->setStatusDesc($result->getNovaltelStatusMessage());

                    //For Manual Testing
                    //$result->setStatus(100);

                    /** @@ Update the transaction status and transaction overview * */
                    $paymentObj->logNovalnetTransactionData($requestData, $result, $txnId);
                }

                $error = $paymentObj->validateSecondCallResponse($result, $payment, $paymentCode);
            } else {

                if (!$paymentObj->isCallbackTypeCall()) {
                    $request = $methodSession->getPaymentReqData();
                    $response = $paymentObj->postRequest($request);
                    $error = $paymentObj->validateNovalnetResponse($payment, $response);
                } else {
                    $error = $paymentObj->validateNovalnetResponse($payment, $methodSession->getPaymentResData());
                }
            }

            if ($error !== false) {
                $paymentObj->unsetMethodSession($paymentCode);
                $this->_redirect('checkout/onepage/failure');
            } else {
                //sendNewOrderEmail
                if (!$order->getEmailSent() && $order->getId() && $error == false) {
                    try {
                        $order->sendNewOrderEmail()
                                ->setEmailSent(true)
                                ->save();
                    } catch (Exception $e) {
                        Mage::throwException($this->_getNovalnetHelper()->__('Cannot send new order email.'));
                    }
                }
                $paymentObj->unsetMethodSession($paymentCode);
                $this->_redirect('checkout/onepage/success');
            }
        } else {
            $this->_redirect('checkout/cart');
        }
    }

    /**
     * Checking Post variables.
     *
     */
    protected function _checkReturnedData()
    {
        try {
            $status = false;
            if (!$this->getRequest()->isPost()) {
                $this->norouteAction();
                return;
            }

            $order = $this->_getOrder();
            $payment = $order->getPayment();
            $paymentObj = $payment->getMethodInstance();
            $paymentCode = $paymentObj->getCode();
            $helper = $this->_getNovalnetHelper();
            $this->_getCheckout()->getQuote()->setIsActive(true)->save();
            $onHoldStatus = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('paymentOnholdStaus');
            array_push($onHoldStatus, '100');
            //Get response
            $response = $this->getRequest()->getParams();

            //Unhold an order:-
            if ($order->canUnhold()) {
                $order->unhold()->save();
            }
			
            $paymentObj->unsetMethodSession($paymentCode);
            $authorizeKey = $paymentObj->_getConfigData('password', true);
            if ($response['status'] == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
                    && $paymentCode != Novalnet_Payment_Model_Config::NN_CC) {
                $response['status'] = $helper->checkParams($response, $authorizeKey);
            }

            //success
            if (($paymentCode == Novalnet_Payment_Model_Config::NN_PAYPAL && $response['status']
                    == Novalnet_Payment_Model_Config::PAYPAL_PENDING_CODE) || in_array($response['status'], $onHoldStatus)) {
                //set Novalnet Mode
                $serverResponse = ($paymentCode != Novalnet_Payment_Model_Config::NN_CC)
                            ? $helper->getDecodedParam($response['test_mode'], $authorizeKey)
                            : $response['test_mode'];
                $shopMode = $paymentObj->_getConfigData('live_mode');
                $testMode = (((isset($serverResponse) && $serverResponse == 1) || (isset($shopMode)
                        && $shopMode == 0)) ? 1 : 0 );
                $data = unserialize($payment->getAdditionalData());
                $data['NnTestOrder'] = $testMode;
                $data['NnTid'] = $response['tid'];
                $transMode = ($paymentCode == Novalnet_Payment_Model_Config::NN_CC)
                            ? false : true;

                $payment->setStatus(Novalnet_Payment_Model_Payment_Method_Abstract::STATUS_SUCCESS)
                        ->setStatusDescription($helper->__('Payment was successful.'))
                        ->setAdditionalData(serialize($data))
                        ->setIsTransactionClosed($transMode)
                        ->save();
                $order->setPayment($payment);
                $order->save(); //Save details in order
                if ($payment->getAdditionalInformation($paymentCode . '_successAction')
                        != 1) {
                    $payment->setAdditionalInformation($paymentCode . '_successAction', 1);
                    $this->_saveSuccessOrder($order, $response, $authorizeKey);
                }
                $status = true;
            } else {
                $dataObj = new Varien_Object($response);
                $paymentObj->saveCancelledOrder($dataObj, $payment);
                $statusMessage = ($dataObj->getStatusText() != NULL) ? $dataObj->getStatusText()
                            : $dataObj->getStatusDesc();
                Mage::getSingleton('core/session')->addError($statusMessage);
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

    /**
     * validate the response and save the order
     *
     */
    private function _saveSuccessOrder($order, $response, $authorizeKey)
    {
        $payment = $order->getPayment();
        $paymentObj = $payment->getMethodInstance();
        $txnId = $response['tid'];
        $helper = $this->_getNovalnetHelper();
        $getAdminTransaction = $paymentObj->doNovalnetStatusCall($txnId, $payment);
        $magentoVersion = $helper->getMagentoVersion();
        $captureMode = (version_compare($magentoVersion, '1.6', '<')) ? false : true;
		
        if ($order->canInvoice() && $getAdminTransaction->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
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
                    ->save();
        }

        $setOrderAfterStatus = $paymentObj->_getConfigData('order_status_after_payment')
                    ? $paymentObj->_getConfigData('order_status_after_payment') : Mage_Sales_Model_Order::STATE_PROCESSING; // If after status is empty set default status
		if ($paymentObj->getCode() == Novalnet_Payment_Model_Config::NN_PAYPAL && $response['status']
                    == Novalnet_Payment_Model_Config::PAYPAL_PENDING_CODE) {
			$setOrderAfterStatus = $paymentObj->_getConfigData('order_status')
                    ? $paymentObj->_getConfigData('order_status') : Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
		}					
        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $setOrderAfterStatus, $helper->__('Customer successfully returned from Novalnet'), true
        )->save();

        //sendNewOrderEmail
        if (!$order->getEmailSent() && $order->getId()) {
            try {
                $order->sendNewOrderEmail()
                        ->setEmailSent(true)
                        ->save();
            } catch (Exception $e) {
                Mage::throwException($helper->__('Cannot send new order email.'));
            }
        }

        $dataObj = new Varien_Object($response);
        $paymentObj->doNovalnetPostbackCall($dataObj); //Do Second Call
        $statusText = ($response['status_text']) ? $response['status_text'] : $helper->__('successful');
        Mage::getSingleton('core/session')->addSuccess($statusText);

        $order->save();

        // Get Admin Transaction status via API
        $amount = is_numeric($response['amount']) ? $response['amount'] : $helper->getDecodedParam($response['amount'], $authorizeKey);
        $helper->doTransactionStatusSave($dataObj, $getAdminTransaction, $payment, $helper->getFormatedAmount($amount, 'RAW')); // Save the Transaction status
    }

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    public function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get Novalnet Helper
     *
     * @return Helper data
     */
    private function _getNovalnetHelper()
    {
        return Mage::helper('novalnet_payment');
    }

    /**
     * Get Last placed order object
     *
     * @return payment object
     */
    private function _getOrder()
    {
        return Mage::getModel('sales/order')->loadByIncrementId($this->_getCheckout()->getLastRealOrderId());
    }

}
