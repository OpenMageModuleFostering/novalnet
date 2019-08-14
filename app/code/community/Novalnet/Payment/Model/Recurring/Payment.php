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
class Novalnet_Payment_Model_Recurring_Payment
{

    /**
     * Submit RP (recurring profile) to the gateway
     *
     * @param  Mage_Payment_Model_Recurring_Profile $profile
     * @param  Mage_Payment_Model_Info              $paymentInfo
     * @return mixed
     */
    public function submitRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile,
        Mage_Payment_Model_Info $paymentInfo)
    {
        $this->helper = Mage::helper('novalnet_payment'); // Novalnet payment helper
        $this->paymentInfo = $paymentInfo; // Get current payment instance
        $this->requestModel = $this->helper->getModel('Service_Api_Request'); // Get Novalnet Api request model
        $this->responseModel = $this->helper->getModel('Service_Api_Response'); // Get Novalnet Api request model

        $this->payment = $paymentInfo->getQuote()->getPayment(); // Get payment object
        $this->code = $this->payment->getMethodInstance()->getCode(); // Payment method code
        $methodSession = $this->helper->getMethodSession($this->code); // Payment method session

        if ($methodSession->hasOrderAmount() && $methodSession->getOrderAmount()) {
            // Get Novalnet Api request model
            $fraudPreventionModel = $this->helper->getModel('Service_Api_FraudPrevention');
            // Validate fraud prevention response process
            $fraudPreventionModel->validateCallbackProcess($methodSession);
            $request = $methodSession->getPaymentReqData(); // Recurring profile payment request
            $response = $methodSession->getPaymentResData(); // Recurring profile payment response
        } else {
            $request = $this->buildRecurringRequest($profile); // Get RP payment request
            $redirectPayments = array(Novalnet_Payment_Model_Config::NN_CC, Novalnet_Payment_Model_Config::NN_PAYPAL);

            if (in_array($this->code, $redirectPayments)) {
                $order = $this->setOriginalPrice($profile); // Get order with original product price values
                $methodSession->setOrderId($order->getId())
                    ->setPaymentReqData($request)
                    ->setIncrementId($order->getIncrementId());
                return true;
            } else {
                // Send RP payment request to Novalnet gateway
                $response = $this->payment->getMethodInstance()->postRequest($request);
            }
        }
        $this->validateRecurringResponse($profile, $request, $response); // Validate the RP response
    }

    /**
     * Prepare RP payment request
     *
     * @param  Mage_Payment_Model_Recurring_Profile $profile
     * @return Varien_Object $request
     */
    protected function buildRecurringRequest($profile)
    {
        $subsequentPeriod = $this->requestModel->getNovalnetConfig('subsequent_period', true);
        $tariffPeriod = $this->getRecurringPeriod($profile); // Get subscription payment periods
        // Get RP order amount
        $subsequentAmount = $amount = round(
            ($profile->getBillingAmount() + $profile->getShippingAmount() + $profile->getTaxAmount()), 2
        ) * 100;
        if ($profile->getInitAmount()) { // Add initial fees if exist
            $amount = round(
                ($profile->getInitAmount() + $profile->getBillingAmount()
                 + $profile->getShippingAmount() + $profile->getTaxAmount()), 2
            ) * 100;
        }
        // Build RP payment request
        $request = $this->requestModel->getPayportParams($this->paymentInfo, $this->code, $amount);
        $request->setTariffPeriod($tariffPeriod)
            ->setTariffPeriod2($subsequentPeriod ? $subsequentPeriod : $tariffPeriod)
            ->setTariffPeriod2Amount($subsequentAmount)
            ->setInput4('profile_id')
            ->setInputval4($profile->getId());

        return $request;
    }

    /**
     * Get subscription payment periods
     *
     * @param  Mage_Payment_Model_Recurring_Profile $profile
     * @param  Varien_Object                        $paymentObj
     * @return string $tariffPeriod
     */
    protected function getRecurringPeriod($profile)
    {
        // Get recurring profile period informations
        $periodUnitFormat = array("day" => "d", "month" => "m", "year" => "y");

        if ($profile->getPeriodUnit() == "semi_month") {
            $tariffPeriod = "14d";
        } elseif ($profile->getPeriodUnit() == "week") {
            $tariffPeriod = ($profile->getPeriodFrequency() * 7) . "d";
        } else {
            $tariffPeriod = $profile->getPeriodFrequency() . $periodUnitFormat[$profile->getPeriodUnit()];
        }

        return $tariffPeriod;
    }

    /**
     * Verify the RP payment process
     *
     * @param  Mage_Payment_Model_Recurring_Profile $profile
     * @param  Varien_Object                        $request
     * @param  Varien_Object                        $response
     * @return none
     */
    protected function validateRecurringResponse($profile, $request, $response)
    {
        $profile->setReferenceId(trim($response->getTid())); // Set profile reference id

        // Novalnet successful transaction
        if ($response->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE);
            $this->recurringPaymentCapture($profile, $request, $response); // Capture RP payment
            $this->helper->unsetMethodSession($this->code); // Unset current payment method session
            $this->updateInventory($this->paymentInfo->getQuote()); // Update the product inventory
            $statusText = $this->responseModel->getStatusText($response); // Get payment transaction status message
            $this->helper->getCoreSession()->addSuccess($statusText);
        } else {  // Novalnet unsuccessful transaction
            // Get payment transaction status message
            $statusText = $this->responseModel->getUnSuccessPaymentText($response);
            $this->helper->showException($statusText);
        }
    }

    /**
     * Capture the RP payment
     *
     * @param  Mage_Payment_Model_Recurring_Profile $profile
     * @param  Varien_Object                        $request
     * @param  Varien_Object                        $response
     * @return none
     */
    protected function recurringPaymentCapture($profile, $request, $response)
    {
        $order = $this->setOriginalPrice($profile); // Get order with original product price values
        $status = $this->requestModel->getNovalnetConfig('order_status'); // Set order status
        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $status, 'Payment was successful')->save();
        $payment = $order->getPayment(); // Get payment object

        $this->responseModel->logTransactionStatus($response, $order); // Log Novalnet payment transaction informations
        // Log Novalnet payment transaction traces informations
        $this->responseModel->logTransactionTraces($request, $response, $order);

        // Novalnet payment transaction mode in Novalnet global configuration
        $shopMode = $this->requestModel->getNovalnetConfig('live_mode', true);
        $testMode = ($response->getTestMode() == 1 || $shopMode == 0) ? 1 : 0; // Get Novalnet payment transaction mode
        // Get Novalnet payment additional informations
        $data = $this->responseModel->getPaymentAddtionaldata($response, $request, $testMode);
        $data['paidUntil'] = $response->getNextSubsCycle() ? $response->getNextSubsCycle() : $response->getPaidUntil();

        // Save the additional transaction informations for reference
        $payment->setPreparedMessage($this->createIpnComment($response->getTidStatus(), $order))
            ->setAdditionalData(serialize($data))
            ->setAdditionalInformation('subs_id', $response->getSubsId())
            ->save();

        // Save the transaction informations
        $this->responseModel->capturePayment($order, $response);
        // Log affiliate user informations
        $this->responseModel->logAffiliateUserInfo($request);

        // Send order email for successful Novalnet transaction
        Mage::dispatchEvent('novalnet_sales_order_email', array('order' => $order));
    }

    /**
     * Generate an "IPN" comment with additional explanation.
     * Returns the generated comment or order status history object
     *
     * @param  string        $paymentStatus
     * @param  Varien_Object $order
     * @param  string        $paymentStatus
     * @param  boolean       $addToHistory
     * @return string
     */
    public function createIpnComment($paymentStatus, $order, $comment = '', $addToHistory = false)
    {
        $message = Mage::helper('novalnet_payment')->__('IPN "%s".', $paymentStatus);
        if ($comment) {
            $message .= ' ' . $comment;
        }
        if ($addToHistory) {
            $message = $order->addStatusHistoryComment($message);
            $message->setIsCustomerNotified(null);
        }
        return $message;
    }

    /**
     * Create order with original product price values
     *
     * @param  Mage_Payment_Model_Recurring_Profile $profile
     * @return Varien_Object $order
     */
    protected function setOriginalPrice($profile)
    {
        $productItemInfo = new Varien_Object;
        $productItemInfo->setPaymentType(Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_REGULAR);
        $productItemInfo->setTaxAmount($profile->getTaxAmount());
        $productItemInfo->setShippingAmount($profile->getShippingAmount());
        $productItemInfo->setPrice($profile->getBillingAmount());
        // Create an order with respected price values
        $order = $profile->createOrder($productItemInfo);
        $order->save();
        // Add related orders to profile
        $profile->addOrderRelation($order->getId());

        return $order;
    }

    /**
     * Update the product inventory (stock)
     *
     * @param  Varien_Object $quote
     * @return none
     */
    protected function updateInventory($quote)
    {
        foreach ($quote->getAllVisibleItems() as $quoteItem) {
            $itemsQtyOrdered = $quoteItem->getQty();
            $productId = $quoteItem->getProductId();
            break;
        }

        if ($productId) {
            $stockObj = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
            $productQtyBefore = (int) $stockObj->getQty();
        }

        if (isset($productQtyBefore) && $productQtyBefore > 0) {
            $productQtyAfter = (int) ($productQtyBefore - $itemsQtyOrdered);
            $stockObj->setQty($productQtyAfter);
            $stockObj->save();
        }
    }

}
