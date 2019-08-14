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
class Novalnet_Payment_GatewayController extends Mage_Core_Controller_Front_Action
{
    /**
     * Initiate redirect payment process
     *
     * @param  none
     * @return none
     */
    public function redirectAction()
    {
        $helper = $this->_getHelper(); // Get Novalnet payment helper
        $session = $helper->getCheckoutSession(); // Get checkout session

        try {
            $order = $this->_getOrder(); // Get order object
            $payment = $order->getPayment(); // Get payment object
            $paymentObj = $payment->getMethodInstance(); // Get payment method instance
            $quoteId = $session->getQuoteId() ? $session->getQuoteId() : $session->getLastQuoteId();
            $items = Mage::getModel('sales/quote')->load($quoteId)->getItemsQty();
            $session->getQuote()->setIsActive(true)->save();
            $redirectActionFlag = $paymentObj->getCode() . '_redirectAction';

            if ($payment->getAdditionalInformation($redirectActionFlag) != 1
                && $session->getLastRealOrderId() && $items
            ) {
                $payment->setAdditionalInformation($redirectActionFlag, 1);
                // Set order status as on-hold
                $status = $state = Mage_Sales_Model_Order::STATE_HOLDED;
                $order->setState($state, $status, $helper->__('Customer was redirected to Novalnet'), false)->save();
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
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Get Novalnet payment transaction response
     *
     * @param  none
     * @return none
     */
    public function returnAction()
    {
        $helper = $this->_getHelper(); // Get Novalnet payment helper
        $order = $this->_getOrder(); // Get order object
        $response = new Varien_Object();
        $response->setData($this->getRequest()->getParams()); // Get payment response data
        $this->_savePayportResponse($response, $order); // Save payment response traces
        $responseModel = $helper->getModel('Service_Api_Response'); // Get Novalnet Api response model
        $status = $responseModel->checkReturnedData($response, $order);
        if ($status) {
            // Send order email for successful Novalnet transaction
            Mage::dispatchEvent('novalnet_sales_order_email', array('order' => $order));
        }
        $helper->getCheckoutSession()->getQuote()->setIsActive(false)->save();
        $this->_redirect(!$status ? 'checkout/onepage/failure' : 'checkout/onepage/success');
    }

    /**
     * Failure payment transaction
     *
     * @param  none
     * @return none
     */
    public function errorAction()
    {
        $helper = $this->_getHelper(); // Get Novalnet payment helper
        $order = $this->_getOrder(); // Get order object
        $response = new Varien_Object();
        $response->setData($this->getRequest()->getParams()); // Get payment response data
        $this->_savePayportResponse($response, $order); // Save payment response traces
        $responseModel = $helper->getModel('Service_Api_Response'); // Get Novalnet Api response model
        $responseModel->checkErrorReturnedData($response, $order); // Verify the payment response data
        $helper->getCheckoutSession()->getQuote()->setIsActive(false)->save();
        $this->_redirect('checkout/onepage/failure', array('_secure' => true)); // Redirects to failure page
    }

    /**
     * Send payment request to Novalnet gateway
     *
     * @param  none
     * @return none
     */
    public function paymentAction()
    {
        $helper = $this->_getHelper(); // Get Novalnet payment helper
        $order = $this->_getOrder(); // Get order object
        $paymentObj = $order->getPayment()->getMethodInstance(); // Get payment method instance
        $paymentCode = $paymentObj->getCode(); // Get payment method code
        $paymentActionFlag = $paymentCode . '_paymentAction';

        if ($order->getPayment()->getAdditionalInformation($paymentActionFlag) != 1) {
            $order->getPayment()->setAdditionalInformation($paymentActionFlag, 1);
            $methodSession = $helper->getMethodSession($paymentCode); // Get payment method session
            $responseModel = $helper->getModel('Service_Api_Response');// Get Novalnet Api response model
            $request = $methodSession->getPaymentReqData(); // Get Novalnet payment request data

            if ($methodSession->getPaymentResData()) { // For fraud prevention process
                $request = $methodSession->getPaymentReqData(); // Novalnet payment request data
                $response = $methodSession->getPaymentResData(); // Novalnet payment response data
            } else {
                $response = $paymentObj->postRequest($request); // Send payment request to Novalnet gatway
            }

            // Validate Novalnet payment response
            $status = $responseModel->validateResponse($order, $request, $response);

            if ($status) {
                // Send order email for successful Novalnet transaction
                Mage::dispatchEvent('novalnet_sales_order_email', array('order' => $order));
            }

            $helper->unsetMethodSession($paymentCode); // Unset payment method session
            Mage::unregister('payment_code'); // Unregister the payment code
            $actionUrl = $status !== true ? 'checkout/onepage/failure' : 'checkout/onepage/success';
        } else {
            $actionUrl = 'checkout/cart';
        }

        $this->_redirect($actionUrl); // Either redirects to success/failure page
    }

    /**
     * Log Novalnet payment response data
     *
     * @param  Varien_Object $response
     * @param  Varien_Object $order
     * @return none
     */
    protected function _savePayportResponse($response, $order)
    {
        // Get Novalnet transaction traces model
        $transactionTraces = Mage::getModel('novalnet_payment/Mysql4_TransactionTraces')
                                ->loadByAttribute('order_id', $response->getOrderNo());
        $transactionTraces->setTransactionId($response->getTid())
            ->setResponseData(base64_encode(serialize($response->getData())))
            ->setCustomerId($order->getCustomerId())
            ->setStatus($response->getStatus()) //transaction status code
            ->setStoreId($order->getStoreId())
            ->setShopUrl($response->getSystemUrl())
            ->save();
    }

    /**
     * Get Last placed order object
     *
     * @param  none
     * @return Varien_Object
     */
    protected function _getOrder()
    {
        $incrementId = $this->_getHelper()->getCheckoutSession()->getLastRealOrderId();
        return Mage::getModel('sales/order')->loadByIncrementId($incrementId);
    }

    /**
     * Get Novalnet payment helper
     *
     * @param  none
     * @return Novalnet_Payment_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('novalnet_payment');
    }

}
