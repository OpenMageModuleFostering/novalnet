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
class Novalnet_Payment_Model_Recurring extends Mage_Core_Model_Abstract
{
    /**
     * Constructor
     *
     * @see lib/Varien/Varien_Object#_construct()
     * @return Novalnet_Payment_Model_Recurring
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('novalnet_payment/recurring');
    }

    /**
     * Perform profile process
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function getProfileProgress($profile)
    {
        $helper = Mage::helper('novalnet_payment');
        $checkoutSession = $helper->getCheckoutSession();
        $quote = $checkoutSession->getQuote();
        $paymentObj = $quote->getPayment()->getMethodInstance();
        $paymentCode = $quote->getPayment()->getMethod();

        if ($checkoutSession->getNominalRequest() && $checkoutSession->getNominalResponse()) {
            $paymentObj->validateCallbackProcess(ucfirst($paymentCode));
            $request = $checkoutSession->getNominalRequest();
            $result = $checkoutSession->getNominalResponse();
        } else {
            $request = $this->_buildRecurringRequest($profile, $paymentObj);
            $result = $paymentObj->postRequest($request);
        }

        $txnId = trim($result->getTid());
        //set profile reference id
        $profile->setReferenceId($txnId);
        if ($result->getStatus() == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {
            $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE);
            $statusText = ($result->getStatusText()) ? $result->getStatusText() : $helper->__('successful');
            $helper->getCoresession()->addSuccess($statusText);
            if ($paymentCode == Novalnet_Payment_Model_Config::NN_SEPA) {
                // log sepa refill information for login users
                $paymentObj->sepaPaymentRefill();
            }

            $ipnRequest = Mage::getModel('novalnet_payment/ipn')->processIpnRequest($this->_buildPostBackRequestForRecurring($request,$profile, $result, $paymentObj), new Varien_Http_Adapter_Curl(), $request, $result);
            // unset form payment method session
            $paymentObj->unsetFormMethodSession();
            // unset payment request and response values
            $paymentObj->unsetPaymentReqResData();
            // unset current payment method session
            $paymentObj->unsetMethodSession();
            // update the inventory
            $this->updateInventory($quote);
        } else {
            $error = $result->getStatusMessage() ?  $result->getStatusMessage() : ($result->getStatusDesc() ?  $result->getStatusDesc()
            : ($result->getStatusText() ?  $result->getStatusText() : $this->helper->__('Error in capturing the payment')));
            if ($error !== false) {
                Mage::throwException($error);
            }
        }
    }

    /**
     * To build recurring post back request
     *
     * @param varien_object $config
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param varien_object $result
     * @param varien_object $paymentObj
     * @result varien_object
     */
    protected function _buildPostBackRequestForRecurring($config, $profile, $result, $paymentObj)
    {
        $request = Mage::getModel('novalnet_payment/novalnet_request');
        $shopMode = (!$paymentObj->getNovalnetConfig('live_mode')) ? 1 : 0;
        $serverResponse = $result->getTestMode();
        $testMode = (((isset($serverResponse) && $serverResponse == 1) || (isset($shopMode)
                && $shopMode == 0)) ? 1 : 0 );
        $data = $paymentObj->setPaymentAddtionaldata($result, $config);
        $data['paidUntil'] = $result->getPaidUntil();
        $request->setVendor($config->getVendor())
                ->setAuthCode($config->getAuthCode())
                ->setProduct($config->getProduct())
                ->setTariff($config->getTariff())
                ->setKey($config->getKey())
                ->setProfileId($profile->getId())
                ->setSignupTid($result->getTid())
                ->setTid($result->getTid())
                ->setStatus($result->getStatus())
                ->setAmount($result->getAmount())
                ->setTestMode($testMode)
                ->setAdditionalData(serialize($data));
        return $request;
    }

    /**
     * Prepare request to gateway
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param varien_object $paymentObj
     * @return mixed
     */
    protected function _buildRecurringRequest(Mage_Payment_Model_Recurring_Profile $profile, $paymentObj)
    {
        $request = Mage::getModel('novalnet_payment/novalnet_request');
        $peroidUnit = $profile->getPeriodUnit();
        $periodUnitFormat = array("day" => "d", "month" => "m", "year" => "y");

        if ($peroidUnit == "semi_month") {
            $tariffPeriod = "14d";
        } elseif ($peroidUnit == "week") {
            $tariffPeriod = ($profile->getPeriodFrequency() * 7) . "d";
        } else {
            $tariffPeriod = $profile->getPeriodFrequency() . $periodUnitFormat[$peroidUnit];
        }

        $storeId = $paymentObj->helper->getMagentoStoreId();
        $originalAmount = round(($profile->getBillingAmount() + $profile->getShippingAmount()
                        + $profile->getTaxAmount()), 2) * 100;
        $initAmount = $profile->getInitAmount();
        $amount = !empty($initAmount) ? $this->recurringAmount($profile) : $originalAmount;
        $request = $paymentObj->buildRequest(Novalnet_Payment_Model_Config::POST_NORMAL, $storeId, $amount);
        $period = $paymentObj->getNovalnetConfig('subsequent_period', true, $storeId);
        $subsequentPeriod = !empty($period) ? $period : $tariffPeriod;
        $request->setTariffPeriod($tariffPeriod)
                ->setTariffPeriod2($subsequentPeriod)
                ->setTariffPeriod2Amount($originalAmount);

        return $request;
    }

    /**
     * Get Recurring amount
     *
     * @param int @profile
     * @return int
     */
    public function recurringAmount($profile)
    {
        return round(($profile->getInitAmount() + $profile->getBillingAmount()
                        + $profile->getShippingAmount() + $profile->getTaxAmount()), 2) * 100;
    }

    /**
     * Get Recurring Increment Id
     *
     * @param int @profile
     * @return int
     */
    public function getRecurringOrderNo($profile)
    {
        $incrementId = array();
        $recurringProfileCollection = Mage::getResourceModel('sales/order_grid_collection')
                ->addRecurringProfilesFilter($profile->getId());
        foreach ($recurringProfileCollection as $recurringProfileCollectionValue) {
            $incrementId[] = $recurringProfileCollectionValue->getIncrementId();
        }
        return $incrementId;
    }

    /**
     * Get Sales Order
     *
     * @param int @incrementId
     * @return varien_object
     */
    public function getOrderByIncrementId($incrementId)
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        return $order;
    }

    /**
     * Get recurring capture total amount
     *
     * @param int $lastTranId
     * @param varien_object $order
     * @return int
     */
    public function getRecurringCaptureTotal($lastTransId, $order)
    {
        $profileInfo = Mage::getModel('sales/recurring_profile')->load($lastTransId, 'reference_id');
        $billingAmount = $profileInfo->getBillingAmount();
        $initialAmount = $profileInfo->getInitAmount();

        if (!empty($initialAmount) && !empty($billingAmount)) {
            $amount = round(($initialAmount + $billingAmount
                        + $profileInfo->getShippingAmount() + $profileInfo->getTaxAmount()), 2);
        } else {
            $amount = $order->getGrandTotal();
        }

        $loadTransaction = Mage::helper('novalnet_payment')->loadTransactionStatus($lastTransId)
                                                           ->setAmount($amount)
                                                           ->save();
        return $amount;
    }

    /**
     * update the product inventory (stock)
     *
     * @param varien_object $quote
     */
    private function updateInventory($quote)
    {
        foreach ($quote->getAllVisibleItems() as $quoteItem) {
            $itemsQtyOrdered = $quoteItem->getQty();
            $productId = $quoteItem->getProductId();
            break;
        }

        if ($productId) {
            $stockObj = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
            $productQtyBefore = (int)$stockObj->getQty();
        }

        if (isset($productQtyBefore) && $productQtyBefore > 0) {
            $productQtyAfter = (int)($productQtyBefore - $itemsQtyOrdered);
            $stockData['qty'] = $productQtyAfter;
            $stockObj->setQty($productQtyAfter);
            $stockObj->save();
        }

        Mage::getSingleton('checkout/session')->unsNnRegularAmount()
                                              ->unsNnRowAmount();
    }
}
