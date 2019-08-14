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
class Novalnet_Payment_GatewayController extends Mage_Core_Controller_Front_Action
{
    /**
     * Redirection process
     *
     */
    public function redirectAction()
    {
        try {
            $session = $this->_getCheckout();
            $order = $this->_getOrder();
            $payment = $order->getPayment();
            $paymentObj = $payment->getMethodInstance();
            $quoteId = $session->getQuoteId() ? $session->getQuoteId() : $session->getLastQuoteId();
            $items = Mage::getModel('sales/quote')->load($quoteId)->getItemsQty();
            $session->getQuote()->setIsActive(true)->save();
            $redirectActionFlag = $paymentObj->getCode() . '_redirectAction';

            if ($payment->getAdditionalInformation($redirectActionFlag) != 1
                && $session->getLastRealOrderId() && $items) {
                $payment->setAdditionalInformation($redirectActionFlag, 1);
                $status = $state = Mage_Sales_Model_Order::STATE_HOLDED; //set State,Status to HOLD
                $order->setState($state, $status, $this->_getNovalnetHelper()->__('Customer was redirected to Novalnet'), false)->save();
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
     *
     * The order information at this point is in POST
     */
    public function returnAction()
    {
        $session = $this->_getCheckout();
        $response = $this->getRequest()->getParams();
        $this->doTransactionOrderLog($response);
        $status = $this->_checkReturnedData();
        $session->getQuote()->setIsActive(false)->save();
        $this->_redirect((!$response['order_no'] || !$status) ? 'checkout/onepage/failure' : 'checkout/onepage/success' );
    }

    /**
     * When Customer cancelled/error in the payment
     *
     * Redirects to failure page.
     */
    public function errorAction()
    {
        $order = $this->_getOrder();
        $session = $this->_getCheckout();
        $payment = $order->getPayment();
        $helper = $this->_getNovalnetHelper();
        $paymentObj = $payment->getMethodInstance();

        $paymentObj->unsetFormMethodSession();
        $session->getQuote()->setIsActive(false)->save();
        $response = $this->getRequest()->getParams();
        $this->doTransactionOrderLog($response);  // Save return error response
        //Unhold an order:-
        if ($order->canUnhold()) {
            $order->unhold()->save();
        }

        //Cancel the order:-
       $errorActionFlag = $paymentObj->getCode() . '_errorAction';
       if ($payment->getAdditionalInformation($errorActionFlag)
                != 1) {
            $payment->setAdditionalInformation($errorActionFlag, 1);
            $dataObj = new Varien_Object($response);
            $paymentObj->saveCancelledOrder($dataObj, $payment);
            $statusMessage = ($dataObj->getStatusText()) ? $dataObj->getStatusText()
                        : $dataObj->getStatusDesc();
            $helper->getCoresession()->addError($statusMessage);
        }

        $this->unsetNovalnetSessionData($paymentObj->getCode());
        $this->_redirect('checkout/onepage/failure', array('_secure' => true));
    }

    /**
     * Receive server response for Novalnet direct payment methods.
     *
     * Redirects to success or failure page.
     */
    public function paymentAction()
    {
        $order = $this->_getOrder();
        $payment = $order->getPayment();
        $paymentObj = $payment->getMethodInstance();
        $paymentActionFlag = $paymentObj->getCode() . '_paymentAction';

        if ($payment->getAdditionalInformation($paymentActionFlag)
                != 1) {
            $payment->setAdditionalInformation($paymentActionFlag, 1);
            $session = $this->_getCheckout();

            if (!$paymentObj->isCallbackTypeCall()) {
                $request = $session->getPaymentReqData();
                $response = $paymentObj->postRequest($request);
                $error = $paymentObj->validateNovalnetResponse($payment, $response);
            } else {
                $error = $paymentObj->validateNovalnetResponse($payment, $session->getPaymentResData());
            }

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
            // unset form payment method session
            $paymentObj->unsetFormMethodSession();
            // unset payment request and response values
            $paymentObj->unsetPaymentReqResData();
            $actionUrl = $error !== false ? 'checkout/onepage/failure' : 'checkout/onepage/success';
        } else {
            $actionUrl = 'checkout/cart';
        }

        $this->_redirect($actionUrl);
    }

    /**
     * Checking Post variables.
     *
     * @return boolean
     */
    protected function _checkReturnedData()
    {
        try {
            $status = false;
            if (!$this->getRequest()->isPost()) {
                $this->norouteAction();
                return false;
            }

            $order = $this->_getOrder();
            $payment = $order->getPayment();
            $paymentObj = $payment->getMethodInstance();
            $this->_getCheckout()->getQuote()->setIsActive(true)->save();
            //Get response
            $response = $this->getRequest()->getParams();
            $dataObj = new Varien_Object($response);
            $helper = $this->_getNovalnetHelper();

            //Unhold an order:-
            if ($order->canUnhold()) {
                $order->unhold()->save();
            }

            $data = unserialize($payment->getAdditionalData());
            $authorizeKey = $data['authorize_key'];
            // unset payment method session
            $this->unsetNovalnetSessionData($paymentObj->getCode());
            $paymentObj->unsetFormMethodSession();

            // check response status
            $response = $this->responseStatus($authorizeKey,$response,$paymentObj);
            if ($paymentObj->getCode() != Novalnet_Payment_Model_Config::NN_CC) {
                $checkHash = $helper->checkHash($response, $authorizeKey);
                if (!$checkHash) {
                    $response['status_text'] = $helper->__('checkHash failed');
                    $dataObj = new Varien_Object($response);
                    $paymentObj->saveCancelledOrder($dataObj, $payment);
                    $helper->getCoresession()->addError($helper->__('checkHash failed'));
                    return false;
                }
            }
            //success
            if (($paymentObj->getCode() == Novalnet_Payment_Model_Config::NN_PAYPAL
                    && $response['status'] == Novalnet_Payment_Model_Config::PAYPAL_PENDING_CODE)
                    || $response['status'] == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
                //set Novalnet Mode
                $testMode = $this->setNovalnetMode($paymentObj,$response,$authorizeKey);
                $data['NnTestOrder'] = $testMode;
                $data['NnTid'] = $response['tid'];
                $amount = is_numeric($response['amount']) ? $response['amount'] : $helper->getDecodedParam($response['amount'], $authorizeKey);
                $transMode = ($paymentObj->getCode() == Novalnet_Payment_Model_Config::NN_CC)
                            ? false : true;

                $payment->setStatus(Novalnet_Payment_Model_Payment_Method_Abstract::STATUS_SUCCESS)
                        ->setStatusDescription($helper->__('Payment was successful.'))
                        ->setAdditionalData(serialize($data))
                        ->setIsTransactionClosed($transMode)
                        ->save();
                $order->setPayment($payment);
                $order->save(); //Save details in order
                $successActionFlag = $paymentObj->getCode() . '_successAction';

                if ($payment->getAdditionalInformation($successActionFlag)
                        != 1) {
                    $payment->setAdditionalInformation($successActionFlag, 1);
                    $this->_saveSuccessOrder($order, $response, $authorizeKey);
                }
                $status = true;
            } else {
                $paymentObj->saveCancelledOrder($dataObj, $payment);
                $statusMessage = ($dataObj->getStatusText()) ? $dataObj->getStatusText()
                            : $dataObj->getStatusDesc();
                $helper->getCoresession()->addError($statusMessage);
                $status = false;
            }
            $order->save();
            return $status;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
            return false;
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * validate the response and save the order
     *
     * @param varien_object $order
     * @param array $response
     * @param mixed $authorizeKey
     */
    private function _saveSuccessOrder($order, $response, $authorizeKey)
    {
        $payment = $order->getPayment();
        $paymentObj = $payment->getMethodInstance();
        $txnId = $response['tid'];
        $getAdminTransaction = $paymentObj->doNovalnetStatusCall($txnId, $payment);
        $helper = $this->_getNovalnetHelper();
        // save transaction id
        $payment->setTransactionId($txnId)
                    ->setLastTransId($txnId)
                    ->setParentTransactionId(null);
        // capture process
        if ($order->canInvoice() && $getAdminTransaction->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $captureMode = (version_compare($helper->getMagentoVersion(), '1.6', '<')) ? false : true;
            $payment->setIsTransactionClosed($captureMode)
                    ->capture(null);
        }
        $payment->save();

        $setOrderAfterStatus = $paymentObj->getNovalnetConfig('order_status_after_payment')
                    ? $paymentObj->getNovalnetConfig('order_status_after_payment')
                    : Mage_Sales_Model_Order::STATE_PROCESSING; // If after status is empty set default status
        if ($paymentObj->getCode() == Novalnet_Payment_Model_Config::NN_PAYPAL && $response['status']
        == Novalnet_Payment_Model_Config::PAYPAL_PENDING_CODE) {
            $setOrderAfterStatus = $paymentObj->getNovalnetConfig('order_status')
                                    ? $paymentObj->getNovalnetConfig('order_status') : Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
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
        $statusText = ($response['status_text']) ? $response['status_text']
                    : $helper->__('successful');
        $helper->getCoresession()->addSuccess($statusText);
        $order->save();
        $amount = is_numeric($response['amount']) ? $response['amount'] : $helper->getDecodedParam($response['amount'], $authorizeKey);
        // Save the Transaction status
        $this->doTransactionStatusSave($response, $getAdminTransaction,$amount, $paymentObj->getCode());
    }

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    private function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get Novalnet Helper
     *
     * @return Novalnet_Payment_Helper_Data
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

    /**
     * Get Current payment method instance
     *
     * @return payment method instance
     */
    private function _getPaymentObject()
    {
        return $this->_getOrder()->getPayment()->getMethodInstance();
    }

    /**
     * Log Novalnet transaction status data
     *
     * @param array $response
     * @param varien_object $transactionStatus
     * @param int $amount
     * @param string $paymentCode
     */
    public function doTransactionStatusSave($response, $transactionStatus,$amount, $paymentCode)
    {
        $helper = $this->_getNovalnetHelper();
        $ncNo = (isset($response['nc_no'])) ? $response['nc_no'] : NULL;
        Mage::getModel('novalnet_payment/transactionstatus')->setTransactionNo($response['tid'])
                ->setOrderId($response['order_no'])
                ->setTransactionStatus(trim($transactionStatus->getStatus())) //Novalnet Admin transaction status
                ->setNcNo($ncNo)   //nc number
                ->setCustomerId($helper->getCustomerId())
                ->setPaymentName($paymentCode)
                ->setAmount($helper->getFormatedAmount($amount, 'RAW'))
                ->setRemoteIp($helper->getRealIpAddr())
                ->setStoreId($helper->getMagentoStoreId())
                ->setShopUrl($helper->getCurrentSiteUrl())
                ->setCreatedDate($helper->getCurrentDateTime())
                ->save();
    }

    /**
     * Log Novalnet payment response data
     *
     * @param array $response
     */
    public function doTransactionOrderLog($response)
    {

        $modNovalTransactionOverview = $this->_getNovalnetHelper()->getModelTransactionOverview()->loadByAttribute('order_id', $response['order_no']);
        $helper = $this->_getNovalnetHelper();

        $modNovalTransactionOverview->setTransactionId($response['tid'])
                ->setResponseData(base64_encode(serialize($response)))
                ->setCustomerId($helper->getCustomerId())
                ->setStatus($response['status']) //transaction status code
                ->setStoreId($helper->getMagentoStoreId())
                ->setShopUrl($helper->getCurrentSiteUrl())
                ->save();
    }

    /**
     * Unset Novalnet session
     *
     * @param string $paymentCode
     */
    private function unsetNovalnetSessionData($paymentCode)
    {
        if ($paymentCode == Novalnet_Payment_Model_Config::NN_CC) {
            $this->_getCheckout()->unsNnCcCvc();
        }
    }

    /**
     * Set payment status for Novalnet redirect payment methods
     *
     * @param varien_object $paymentObj
     * @param array $response
     * @param mixed $authorizeKey
     * @return array
     */
    private function responseStatus($authorizeKey,$response,$paymentObj)
    {
        if ($response['status'] == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
                && $paymentObj->getCode() != Novalnet_Payment_Model_Config::NN_CC) {
            $response['status'] = $this->_getNovalnetHelper()->checkParams($response, $authorizeKey);
        }
        return $response;
    }

    /**
     * Set Novalnet payment mode (test/live)
     *
     * @param varien_object $paymentObj
     * @param array $response
     * @param mixed $authorizeKey
     * @return int
     */
    private function setNovalnetMode($paymentObj,$response,$authorizeKey)
    {
        $serverResponse = ($paymentObj->getCode() != Novalnet_Payment_Model_Config::NN_CC)
                            ? $this->_getNovalnetHelper()->getDecodedParam($response['test_mode'], $authorizeKey)
                            : $response['test_mode'];
        $shopMode = $paymentObj->getNovalnetConfig('live_mode');
        $testMode = (((isset($serverResponse) && $serverResponse == 1) || (isset($shopMode)
                        && $shopMode == 0)) ? 1 : 0 );
        return $testMode;
    }
}