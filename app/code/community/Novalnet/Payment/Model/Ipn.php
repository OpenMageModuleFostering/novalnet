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
class Novalnet_Payment_Model_Ipn
{
    /**
     * Default log filename
     *
     * @var string
     */
    const DEFAULT_LOG_FILE = 'novalnet_recurring_unknown_ipn.log';

    /**
     * Store order instance
     *
     * @var Mage_Sales_Model_Order
     */
    protected $_order = null;

    /*
     * Recurring profile instance
     *
     * @var Mage_Sales_Model_Recurring_Profile
     */
    protected $_recurringProfile = null;

    /**
     * IPN request data
     *
     * @var array
     */
    protected $_request = array();

    /**
     * Check the debug is enable
     *
     * @var array
     */
    protected $_debug = false;

    /**
     * Show the debug
     *
     * @var array
     */
    protected $_showdebug = false;

    /**
     * Collected debug information
     *
     * @var array
     */
    protected $_debugData = array();

    /**
     * IPN request data getter
     *
     * @param string $key
     * @return array|string
     */
    public function getRequestData($key = null)
    {
        if (null === $key) {
            return $this->_request;
        }
        return isset($this->_request[$key]) ? $this->_request[$key] : null;
    }

    /**
     * Process recurring profile request
     *
     * @param array $request
     * @param Zend_Http_Client_Adapter_Interface $httpAdapter
     * @param array $requestdata
     * @param array $resultdata
     * @throws Exception
     */
    public function processIpnRequest($request, Zend_Http_Client_Adapter_Interface $httpAdapter
    = null, $requestdata, $resultdata)
    {
        $this->_helper = Mage::helper('novalnet_payment');
        $this->_request = $request;

        try {
            $this->_getRecurringProfile();
            $statuscode = $this->_processRecurringProfile($httpAdapter, $requestdata, $resultdata);
        } catch (Exception $e) {
            $this->_debugData['exception'] = $e->getMessage();
            $this->_debug();
            throw $e;
        }
        $this->_debug();
        return $statuscode;
    }

    /**
     * Post back to Novalnet, check whether this request is a valid one
     *
     * @param Zend_Http_Client_Adapter_Interface $httpAdapter
     * @param array $postbackRequest
     */
    protected function _postBack(Zend_Http_Client_Adapter_Interface $httpAdapter, $postbackRequest)
    {
        $sReq = '';
        foreach ($postbackRequest->getData() as $k => $v) {
            $sReq .= '&' . $k . '=' . urlencode($v);
        }
        $sReq = substr($sReq, 1);
        $payportUrl = $this->_helper->getPayportUrl('paygate');
        $this->_debugData['postback'] = $sReq;
        $this->_debugData['postback_to'] = $payportUrl;
        $httpAdapter->write(Zend_Http_Client::POST, $payportUrl, '1.1', array(), $sReq);
        try {
            $response = $httpAdapter->read();
        } catch (Exception $e) {
            $this->_debugData['http_error'] = array('error' => $e->getMessage(),
                'code' => $e->getCode());
            throw $e;
        }
        $this->_debugData['postback_result'] = $response;
        unset($this->_debugData['postback'], $this->_debugData['postback_result']);
    }

    /**
     * Load recurring profile
     *
     * @return Mage_Sales_Model_Recurring_Profile
     * @throws Exception
     */
    protected function _getRecurringProfile()
    {
        if (empty($this->_recurringProfile)) {
            $recurringProfileId = "";
            if (isset($this->_request['profile_id'])) {
                $recurringProfileId = $this->_request['profile_id'];
            }

            $referenceId = $this->_request['signup_tid'];
            if ($recurringProfileId) {
                $this->_recurringProfile = Mage::getModel('sales/recurring_profile')
                        ->load($recurringProfileId, 'profile_id');
            } else {
                $this->_recurringProfile = Mage::getModel('sales/recurring_profile')
                        ->load($referenceId, 'reference_id');
            }

            if (!$this->_recurringProfile->getId()) {
                $debugMsg = sprintf('Wrong recurring profile REFERENCE_ID: "%s".', $referenceId);
                if ($this->_showdebug) {
                    echo $debugMsg;
                    exit;
                }
                throw new Exception($debugMsg);
            }

            $methodCode = $this->_recurringProfile->getMethodCode();
            if (!$this->isMethodActive($methodCode, $this->_recurringProfile->getStoreId())) {
                $debugMsg = sprintf('Method "%s" is not available.', $methodCode);
                if ($this->_showdebug) {
                    echo $debugMsg;
                    exit;
                }
                throw new Exception($debugMsg);
            }
            if (!$recurringProfileId) {
                $this->_recurringProfileOrderTid = null;
                $this->_getRecurringProfileOrdersTid();
            }
        }
        return $this->_recurringProfile;
    }

    /**
     * Load recurring profile
     *
     * @return Mage_Sales_Model_Recurring_Profile
     * @throws Exception
     */
    protected function _getRecurringProfileOrdersTid()
    {
        if (empty($this->_recurringProfileOrderTid)) {
            $recurringProfileOrderTid = $this->_request['tid'];
            $checkNovalnetTids = Mage::getModel("sales/order_payment")->getCollection()
                    ->addAttributeToSelect('last_trans_id')
                    ->addFieldToFilter('last_trans_id', array('like' => "%" . $recurringProfileOrderTid . "%"))
                    ->getData();
            if (!empty($checkNovalnetTids)) {
                $debugMsg = sprintf('Wrong recurring profile order TID: "%s".', $recurringProfileOrderTid);
                if ($this->_showdebug) {
                    echo $debugMsg;
                    exit;
                }
                throw new Exception($debugMsg);
            }
        }

        return $this->_recurringProfileOrderTid;
    }

    /**
     * Check whether method active in configuration and supported for merchant country or not
     *
     * @param string $method
     * @param int $storeId
     * @return boolean
     */
    public function isMethodActive($method, $storeId)
    {
        if (Mage::getStoreConfigFlag("payment/{$method}/active", $storeId)) {
            return true;
        }
        return false;
    }

    /**
     * Process notification from recurring profile payments
     *
     * @param Zend_Http_Client_Adapter_Interface $httpAdapter
     * @param array $requestdata
     * @param array $resultdata
     * @return int
     */
    protected function _processRecurringProfile(Zend_Http_Client_Adapter_Interface $httpAdapter, $requestdata, $resultdata)
    {
        $this->_recurringProfile = null;
        $this->_getRecurringProfile();
        try {
            // handle payment_status
            $paymentStatus = $this->_request['status'];
            switch ($paymentStatus) {
                // paid
                case Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED:
                    $statuscode = $this->_registerRecurringProfilePaymentCapture($httpAdapter, $requestdata, $resultdata);
                    break;

                default:
                    $debugMsg = sprintf("Novalnet callback received. Status $paymentStatus is not valid: Only 100 is allowed.");
                    if ($this->_showdebug) {
                        echo $debugMsg;
                        exit;
                    }
                    throw new Exception($debugMsg);
            }
        } catch (Mage_Core_Exception $e) {
            //TODO: add to payment profile comments
            $comment = $this->_createIpnComment($this->_helper->__('Note: %s', $e->getMessage()), true);
            $comment->save();
            throw $e;
        }
        return $statuscode;
    }

    /**
     * Register recurring payment notification, create and process order
     *
     * @param Zend_Http_Client_Adapter_Interface $httpAdapter
     * @param array $requestdata
     * @param array $resultdata
     * @return int
     */
    protected function _registerRecurringProfilePaymentCapture(Zend_Http_Client_Adapter_Interface $httpAdapter, $requestdata, $resultdata)
    {
        $tid = $this->getRequestData('tid');
        $product = $this->getRequestData('product');
        $paidAmount = $this->getRequestData('amount');
        $recurringProfile = $this->_recurringProfile;

        $billlingAmount = $recurringProfile->getBillingAmount();
        $taxAmount = $recurringProfile->getTaxAmount();
        $shipAmount = $recurringProfile->getShippingAmount();
        $referenceId = $recurringProfile->getReferenceId();
        $amount = round(($billlingAmount + $shipAmount + $taxAmount), 2);
        $price = $billlingAmount;
        $originalPrice = $amount;
        $productItemInfo = new Varien_Object;

        $productItemInfo->setPaymentType(Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_REGULAR);
        $productItemInfo->setTaxAmount($taxAmount);
        $productItemInfo->setShippingAmount($shipAmount);
        $productItemInfo->setPrice($price);

        $order = $recurringProfile->createOrder($productItemInfo);
        $this->_order = $order;
        $payment = $order->getPayment();         
        $order->save();
        $payment->setAdditionalData($this->getRequestData('additional_data'))
                ->save();
        $paymentObj = $payment->getMethodInstance();

        $paymentMethod = $paymentObj->getCode();
        $getStatus = $resultdata->getTidStatus();
        $subsId = $resultdata->getSubsId();
        $resultdata->setStatus($getStatus);
        if (!$referenceId
        && (in_array($paymentMethod, array(Novalnet_Payment_Model_Config::NN_PREPAYMENT, Novalnet_Payment_Model_Config::NN_INVOICE)) && $getStatus != 100)) {
            $payment->setIsTransactionPending(true);
        }

        $reslutStatus = $resultdata->getStatus();
        $recurringProfile->addOrderRelation($order->getId());
        $this->_helper->getCoresession()->setStatusCode($reslutStatus);
        $closed = $reslutStatus == Novalnet_Payment_Model_Config::RESPONSE_CODE_APPROVED
                    ? 1 : 0;
        $payment->setTransactionId($tid)
                ->setPreparedMessage($this->_createIpnComment(''))
                ->setAdditionalInformation('subs_id', $subsId)
                ->setIsTransactionClosed($closed); 
        $resultdata->setAmount($order->getGrandTotal());                 
        $paymentObj->logNovalnetStatusData($resultdata, trim($tid));
        $paymentObj->logNovalnetTransactionData($requestdata, $resultdata, trim($tid), $this->_helper->getCustomerId(), $this->_helper->getMagentoStoreId());
        $payment->registerCaptureNotification($originalPrice, 0);
        if ($reslutStatus == 100 && $paymentMethod != Novalnet_Payment_Model_Config::NN_PREPAYMENT) {
            $order->setTotalPaid($paidAmount);
            $order->setBaseTotalPaid($paidAmount);
        }

        $order->save();

        if (!$referenceId && $httpAdapter) {
            $request = Mage::getModel('novalnet_payment/novalnet_request');
            $request->setTid($tid)
                    ->setVendor($this->getRequestData('vendor'))
                    ->setAuthCode($this->getRequestData('auth_code'))
                    ->setTestMode($this->getRequestData('test_mode'))
                    ->setProduct($product)
                    ->setTariff($this->getRequestData('tariff'))
                    ->setKey($this->getRequestData('key'))
                    ->setOrderNo($order->getIncrementId())
                    ->setStatus(100);
            if (in_array($paymentMethod, array(Novalnet_Payment_Model_Config::NN_PREPAYMENT, Novalnet_Payment_Model_Config::NN_INVOICE))) {
                $request->setInvoiceRef("BNR-" . $product . "-" . $order->getIncrementId());
            }

            $this->_postBack($httpAdapter, $request); // second call
            // Log Affiliate user details
            $nnAffId = $this->_helper->getCoresession()->getNnAffId();
            if ($nnAffId) {
                $paymentObj->doNovalnetAffUserInfoLog($nnAffId);
            }
        }
        // notify customer
        $subscriptionPayments = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('subscriptionPayments');

        if (($reslutStatus != 100 && in_array($paymentMethod, $subscriptionPayments))
            || ($reslutStatus == 100 && $paymentMethod == Novalnet_Payment_Model_Config::NN_PREPAYMENT)) {
            if (!$order->getEmailSent() && $order->getId()) {
                            $order->sendNewOrderEmail()
                                    ->setEmailSent(true)
                                    ->save();
            }
        } else if ($invoice = $payment->getCreatedInvoice()) {
            $message = $this->_helper->__('Notified customer about invoice #%s.', $invoice->getIncrementId());
            $comment = $order->sendNewOrderEmail()->addStatusHistoryComment($message)
                    ->setIsCustomerNotified(true)
                    ->save();
        }
        return $getStatus;
    }

    /**
     * Generate an "IPN" comment with additional explanation.
     * Returns the generated comment or order status history object
     *
     * @param string $comment
     * @param bool $addToHistory
     * @return string|Mage_Sales_Model_Order_Status_History
     */
    protected function _createIpnComment($comment = '', $addToHistory = false)
    {
        $paymentStatus = $this->getRequestData('status');
        $message = $this->_helper->__('IPN "%s".', $paymentStatus);
        if ($comment) {
            $message .= ' ' . $comment;
        }
        if ($addToHistory) {
            $message = $this->_order->addStatusHistoryComment($message);
            $message->setIsCustomerNotified(null);
        }
        return $message;
    }

    /**
     * Log debug data to file
     *
     */
    protected function _debug()
    {
        if ($this->_debug) {
            $file = self::DEFAULT_LOG_FILE;
            Mage::getModel('core/log_adapter', $file)->log($this->_debugData);
        }
    }
}
