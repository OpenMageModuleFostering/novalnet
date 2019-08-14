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
class Novalnet_Payment_Model_Factory
{
    /**
     * Save capture response
     *
     * @param float $amount
     * @param varien_object $loadTransStatus
     * @param int $transStatus
     * @param varien_object $payment
     * @param int $lastTranId
     */
    public function captureResponseSave($amount, $loadTransStatus, $transStatus, $payment, $lastTranId)
    {
        if ($amount) {
            $loadTransStatus->setTransactionStatus(Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED)
                    ->setAmount($amount)
                    ->save();
        } else {
            $loadTransStatus->setTransactionStatus(Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED)
                    ->save();
        }

        $magentoVersion = Mage::helper('novalnet_payment')->getMagentoVersion();
        // make capture transaction open for lower versions to make refund
        if (version_compare($magentoVersion, '1.6', '<')) {
            $payment->setIsTransactionClosed(false)
                    ->save();
        }

        if ($transStatus != Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED) {

            $transMode = (version_compare($magentoVersion, '1.6', '<')) ? false : true;
            $payment->setTransactionId($lastTranId)
                    ->setIsTransactionClosed($transMode);
            $transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, null, false);
            $transaction->setParentTxnId(null)
                    ->save();
        }
    }

    /**
     * Call Transation status refund and void
     *
     * @param int $getTid
     * @param varien_object $payment
     * @param int $amountAfterRefund
     * @param string $call
     * @param int $refundTid
     * @param int $customerId
     * @param array $response
     * @return mixed
     */
    public function callTransactionStatus($getTid, $payment, $amountAfterRefund, $call, $refundTid
    = NULL, $customerId = NULL, $response = NULL)
    {
        $helper = Mage::helper('novalnet_payment');
        $paymentObj = $payment->getMethodInstance();
        $getTransactionStatus = $paymentObj->doNovalnetStatusCall($getTid, $payment, Novalnet_Payment_Model_Config::TRANS_STATUS, NULL, NULL);
        $amount = $helper->getFormatedAmount($getTransactionStatus->getAmount(), 'RAW');
        if ($call == 1) {
            $loadTransaction = $helper->loadTransactionStatus($getTid);
            $loadTransaction->setTransactionStatus($getTransactionStatus->getStatus())
                    ->setAmount($amount)
                    ->save();
          if (in_array($paymentObj->getCode(), array(Novalnet_Payment_Model_Config::NN_INVOICE,
                            Novalnet_Payment_Model_Config::NN_PREPAYMENT))) {
                    $loadTransaction->setAmount($amountAfterRefund)
                            ->save();
          }
        } else {
            if ($refundTid) { // Only log the novalnet transaction which contains TID
                $response->setStatus($getTransactionStatus->getStatus());
                $paymentObj->logNovalnetStatusData($response, $refundTid, $customerId, NULL, $amount);
            }
        }
        return $getTransactionStatus;
    }

    /**
     * Check Magento version refund progress save
     *
     * @param Mage_Checkout_Model_Session $helper
     * @param varien_object $payment
     * @param int $refundTid
     * @param array $data
     */
    public function refundValidateProcess($helper, $payment, $refundTid,$data)
    {
        // make capture transaction open for lower versions to make refund
        if (version_compare($helper->getMagentoVersion(), '1.6', '<')) {
            $order = $payment->getOrder();
            $canRefundMore = $order->canCreditmemo();

            $payment->setTransactionId($refundTid)
                    ->setLastTransId($refundTid)
                    ->setAdditionalData(serialize($data))
                    ->setIsTransactionClosed(1) // refund initiated by merchant
                    ->setShouldCloseParentTransaction(!$canRefundMore)
                    ->save();
        } else {
            $payment->setTransactionId($refundTid)
                    ->setLastTransId($refundTid)
                    ->setAdditionalData(serialize($data))
                    ->save();
        }
    }

    /**
     * Set RequestParams Form
     *
     * @param varien_object $request
     * @param varien_object $infoObject
     * @param int $orderId
     * @param int $amount
     * @param int $livemode
     */
    public function requestParams($request, $infoObject, $orderId, $amount, $livemode)
    {
        $helper = Mage::helper('novalnet_payment');
        $billing = $infoObject->getBillingAddress();
        $shipping = $infoObject->getShippingAddress();
        $company = $billing->getCompany() ? $billing->getCompany() : ($shipping->getCompany() ? $shipping->getCompany() : '');
        $email = $billing->getEmail() ? $billing->getEmail() : $infoObject->getCustomerEmail();
        $request = $company ? $request->setCompany($company) : $request;
        $request->setTestMode($livemode)
                ->setAmount($amount)
                ->setCurrency($infoObject->getBaseCurrencyCode())
                ->setCustomerNo($helper->getCustomerId())
                ->setUseUtf8(1)
                ->setFirstName($billing->getFirstname())
                ->setLastName($billing->getLastname())
                ->setSearchInStreet(1)
                ->setStreet(implode(',', $billing->getStreet()))
                ->setCity($billing->getCity())
                ->setZip($billing->getPostcode())
                ->setCountry($billing->getCountry())
                ->setCountryCode($billing->getCountry())
                ->setLanguage(strtoupper($helper->getDefaultLanguage()))
                ->setLang(strtoupper($helper->getDefaultLanguage()))
                ->setTel($billing->getTelephone())
                ->setFax($billing->getFax())
                ->setRemoteIp($helper->getRealIpAddr())
                ->setGender('u')
                ->setEmail($email)
                ->setOrderNo($orderId)
                ->setSystemUrl(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB))
                ->setSystemIp($helper->getServerAddr())
                ->setSystemName('Magento')
                ->setSystemVersion($helper->getMagentoVersion() . '-' . $helper->getNovalnetVersion())
                ->setInput1('order_id')
                ->setInputval1($orderId);
    }

    /**
     * Set Request profile params
     *
     * @param varien_object $request
     * @param mixed $subsequentPeriod
     * @return null
     */
    public function requestProfileParams($request, $subsequentPeriod)
    {
        $helper = Mage::helper('novalnet_payment');
        $checkoutSession = $helper->getCheckoutSession();
        $periodUnit = $checkoutSession->getNnPeriodUnit();
        $periodFrequency = $checkoutSession->getNnPeriodFrequency();
        $periodUnitFormat = array("day" => "d", "month" => "m", "year" => "y");

        if ($periodUnit == "semi_month") {
            $tariffPeriod = "14d";
        } elseif ($periodUnit == "week") {
            $tariffPeriod = ($periodFrequency * 7) . "d";
        } else {
            $tariffPeriod = $periodFrequency . $periodUnitFormat[$periodUnit];
        }

        $subsequentPeriod = $subsequentPeriod ? $subsequentPeriod : $tariffPeriod;
        $regularAmount = $helper->getFormatedAmount($helper->getCheckoutSession()->getNnRegularAmount());
        $request->setTariffPeriod($tariffPeriod)
                ->setTariffPeriod2($subsequentPeriod)
                ->setTariffPeriod2Amount($regularAmount);
    }

    /**
     * Save Profile Active State
     *
     * @param int $lastTransId
     */
    public function saveProfileState($lastTransId)
    {
        $recurringProfileId = Mage::getModel('sales/recurring_profile')->load($lastTransId, 'reference_id');
        if ($recurringProfileId->getState() != 'canceled') {
            $recurringProfileId->setState('active');
            $recurringProfileId->save();
        }
    }

    /**
     * Set recurring profile state as canceled
     *
     * @param int $lastTransId
     */
    public function saveProfileCancelState($lastTransId)
    {
        $count = $this->recurringCollection($lastTransId);
        if ($count) {
            $recurringProfileId = Mage::getModel('sales/recurring_profile')->load($lastTransId, 'reference_id');
            $recurringProfileId->setState('canceled');
            $recurringProfileId->save();
        }
    }

    /**
     * Set reference id for recurring profile
     *
     * @param int $lastTransId
     * @param int $tid
     */
    public function saveProfileTID($lastTransId,$tid)
    {
        $count = $this->recurringCollection($lastTransId);
        if ($count) {
            $recurringProfileId = Mage::getModel('sales/recurring_profile')->load($lastTransId, 'reference_id');
            $recurringProfileId->setReferenceId($tid);
            $recurringProfileId->save();
        }
    }

    /**
     * Save Profile Cancel State
     *
     * @param int $lastTransId
     * @return int
     */
    private function recurringCollection($lastTransId)
    {
        $recurringCollection = Mage::getModel('sales/recurring_profile')->getCollection()
                                                                        ->addFieldToFilter('reference_id', $lastTransId)
                                                                        ->addFieldToSelect('reference_id');
        $countRecurring = count($recurringCollection);
        return $countRecurring;
    }



    /**
     * Request Types Capture,Refund and void
     *
     * @param varien_object $request
     * @param string $requestType
     * @param Novalnet_Payment_Helper_Data $helper
     * @param int $getTid
     * @param int $refundAmount
     */
    public function requestTypes($request, $requestType, $helper, $getTid, $refundAmount
    = NULL)
    {
        $setStatus = ($requestType == 'capture') ? Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
                    : Novalnet_Payment_Model_Config::PAYMENT_VOID_STATUS;

        if ($requestType == 'void' || $requestType == 'capture') {
            $request->setTid($helper->makeValidNumber($getTid))
                    ->setStatus($setStatus)
                    ->setEditStatus(true);
        } else {
            $request->setTid($helper->makeValidNumber($getTid))
                    ->setRefundRequest(true)
                    ->setRefundParam($refundAmount);
            $this->refundAdditionalParam($request);
        }
    }

    /**
     * Add additonal params for refund process
     *
     * @param varien_object $request
     */
    private function refundAdditionalParam($request)
    {
        $getParamRequest = Mage::app()->getRequest();
        $helper = Mage::helper('novalnet_payment');
        $refundAccountholder = $getParamRequest->getParam('refund_payment_type_accountholder');
        $refundIban = $getParamRequest->getParam('refund_payment_type_iban');
        $refundBic = $getParamRequest->getParam('refund_payment_type_bic');
        $refundRef = $getParamRequest->getParam('nn_refund_ref');
        $refundType = $getParamRequest->getParam('refund_payment_type');

        if ($refundType == 'SEPA' && (!$refundIban || !$refundBic)) {
            Mage::throwException($helper->__('Please enter valid account details'));
        } elseif ($refundRef && !$helper->checkIsValid($refundRef)) {
            Mage::throwException($helper->__('Please enter valid account details'));
        }

        if ($refundRef) {
            $request->setRefundRef($refundRef);
        }

        if ($refundIban && $refundBic) {
            $request->setAccountHolder($refundAccountholder)
                    ->setIban($refundIban)
                    ->setBic($refundBic);
        }
    }

    /**
     * get last successful payment method
     *
     * @param int $customerId
     * @param Mage_Checkout_Model_Session $checkoutSession
     */
    public function getlastSuccesOrderMethod($customerId,$checkoutSession)
    {
        $table_prefix = Mage::getConfig()->getTablePrefix();
        $order_table = $table_prefix.'sales_flat_order';
        $on_condition = "main_table.parent_id = $order_table.entity_id";
        $orderCollection =  Mage::getModel('sales/order_payment')->getCollection()
                                                                 ->addAttributeToSort('created_at', 'DESC')
                                                                 ->addFieldToFilter('customer_id', $customerId)
                                                                 ->addFieldToFilter('method',array('like' => '%novalnet%'))
                                                                 ->addFieldToSelect('method')
                                                                 ->setPageSize(1);
        $orderCollection->getSelect()->join($order_table,$on_condition);
        $count = $orderCollection->count();
        if ($count > 0) {
            foreach($orderCollection as $order):
                $paymentMethod = $order->getMethod();
            endforeach;
            $checkoutSession->getQuote()->getPayment()->setMethod($paymentMethod);
        }
    }

    /**
     * Verify the final amount for the transaction id
     *
     * @param string $currency
     * @param int $getTid
     * @param varien_object $payment
     * @param int $refundAmount
     * @return int
     */
    public function checkNovalnetCardAmount($currency, $getTid, $payment, $refundAmount)
    {
        $helper = Mage::helper('novalnet_payment');
        $paymentObj = $payment->getMethodInstance();
        $statusCallSub = $paymentObj->doNovalnetStatusCall($getTid,$payment);
        $respnseCode = $statusCallSub->getStatus();
        $cardAmount = $statusCallSub->getAmount();

        if($respnseCode == 100 && $refundAmount > $cardAmount) {
            Mage::throwException($helper->__('Refund amount greater than Novalnet card Amount, amount in card') . ' '. $currency . $helper->getFormatedAmount($cardAmount, 'RAW'));
        }
        return $cardAmount;
    }

    /**
     * Build the refund details for reference
     *
     * @param int $refAmount
     * @param array $data
     * @param int $refundTid
     * @param int $getTid
     * @return array
     */
    public function refundTidData($refAmount,$data,$refundTid,$getTid)
    {
        if (!isset($data['refunded_tid'])) {
            $refundedTid = array('refunded_tid'=> array($refundTid => array('reftid' => $refundTid , 'refamount' => $refAmount , 'reqtid' => $getTid)));
            $data = array_merge($data, $refundedTid);
        } else {
            $data['refunded_tid'][$refundTid]['reftid'] = $refundTid;
            $data['refunded_tid'][$refundTid]['refamount'] = $refAmount;
            $data['refunded_tid'][$refundTid]['reqtid'] = $getTid;
        }
        return $data;
    }
}
