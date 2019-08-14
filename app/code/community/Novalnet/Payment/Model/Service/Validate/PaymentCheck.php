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
class Novalnet_Payment_Model_Service_Validate_PaymentCheck extends Novalnet_Payment_Model_Service_Abstract
{
    /**
     * Check payment visiblity based on payment configuration
     *
     * @param  Mage_Sales_Model_Quote|null $quote
     * @return boolean
     */
    public function checkVisiblity($quote = null)
    {
        if (!$this->validateBasicParams()) {
            return false;
        } elseif (!$this->checkOrdersCount()) {
            return false;
        } elseif (!$this->checkCustomerAccess()) {
            return false;
        } elseif (!empty($quote) && !$quote->hasNominalItems()
            && !$quote->getGrandTotal()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Validate Novalnet basic params
     *
     * @param  none
     * @return boolean
     */
    public function validateBasicParams()
    {
        if ($this->vendorId && $this->authcode
            && $this->productId && $this->tariffId && $this->accessKey
        ) {
            return true;
        }

        return false;
    }

    /**
     * Check orders count by customer id
     *
     * @param  none
     * @return boolean
     */
    public function checkOrdersCount()
    {
        // Load orders by customer id
        $customerId = $this->_helper->getCustomerId();
        $orders = Mage::getModel('sales/order')->getCollection()->addFieldToFilter('customer_id', $customerId);
        $ordersCount = $orders->count();
        // Get orders count from payment configuration
        $minOrderCount = $this->getNovalnetConfig('orders_count');
        return ($ordersCount >= trim($minOrderCount)) ? true : false;
    }

    /**
     * Check whether current user have access to process the payment
     *
     * @param  none
     * @return boolean
     */
    public function checkCustomerAccess()
    {
        // Excluded customer groups from payment configuration
        $excludedGroupes = $this->getNovalnetConfig('user_group_excluded');

        if (!$this->_helper->checkIsAdmin() && strlen($excludedGroupes)) {
            $excludedGroupes = explode(',', $excludedGroupes);
            $customerGroupId = $this->_helper->getCustomerSession()->getCustomerGroupId();
            return !in_array($customerGroupId, $excludedGroupes);
        }
        return true;
    }

    /**
     * validate Novalnet params to proceed checkout
     *
     * @param  Varien_Object $info
     * @return boolean
     */
    public function validateNovalnetParams($info)
    {
        if (!$this->validateBasicParams()) {
            $this->_helper->showException('Basic parameter not valid.');
            return false;
        } elseif (!$this->validateBillingInfo($info)) {
            $this->_helper->showException('Customer name/email fields are not valid');
            return false;
        }
        return true;
    }

    /**
     * Validate billing information
     *
     * @param  Varien_Object $info
     * @return boolean
     */
    public function validateBillingInfo($info)
    {
        $request = new Varien_Object();
        $requestModel = $this->_helper->getModel('Service_Api_Request');
        $requestModel->getBillingInfo($request, $info);

        if (!$this->validateEmail($request->getEmail())
            || !$request->getFirstName() || !$request->getLastName()
        ) {
            return false;
        }
        return true;
    }

    /**
     * validate Novalnet form data
     *
     * @param  none
     * @return throw Mage Exception|none
     */
    public function validateFormInfo()
    {
        $methodSession = $this->_helper->getMethodSession($this->code);

        switch ($this->code) {
        case Novalnet_Payment_Model_Config::NN_SEPA:
            $sepaDueDate = $methodSession->getSepaDuedate();

            // Validate the Direct Debit SEPA form data
            if (!$methodSession->getSepaMandateConfirm()) {
                $this->_helper->showException('Please accept the SEPA direct debit mandate');
            } elseif (strlen($sepaDueDate) > 0 && ($sepaDueDate < 7
                || !$this->_helper->checkIsNumeric($sepaDueDate))
            ) {
                $this->_helper->showException('SEPA Due date is not valid');
            } elseif ($methodSession->getSepaNewForm()
                && (!$methodSession->getSepaHash() || !$methodSession->getSepaUniqueId())
            ) {
                $this->_helper->showException('Your account details are invalid');
            }

            // Validate DOB
            if ($methodSession->getPaymentGuaranteeFlag()) {
                $customerDob = (string) $methodSession->getCustomerDob();
                if ($customerDob && !$this->validateBirthDate($customerDob)) {
                    $methodSession->unsPaymentGuaranteeFlag();
                    if (!$this->getNovalnetConfig('payment_guarantee_force')) {
                        $this->_helper->showException('You need to be at least 18 years old');
                    }
                }
            }

            // Validate fraud prevention form values
            if ($methodSession->getSepaNewForm()) {
                $this->fraudModuleValidation($methodSession);
            }
            break;
        case Novalnet_Payment_Model_Config::NN_INVOICE:
            // Validate the payment reference values
            $referenceOne = $this->getNovalnetConfig('payment_ref_one');
            $referenceTwo = $this->getNovalnetConfig('payment_ref_two');
            $referenceThree = $this->getNovalnetConfig('payment_ref_three');

            if (!$referenceOne && !$referenceTwo && !$referenceThree) {
                $this->_helper->showException('Please select atleast one payment reference.');
            }
            // Validate DOB
            if ($methodSession->getPaymentGuaranteeFlag()) {
                $customerDob = (string) $methodSession->getCustomerDob();
                if ($customerDob && !$this->validateBirthDate($customerDob)) {
                    $methodSession->unsPaymentGuaranteeFlag();
                    if (!$this->getNovalnetConfig('payment_guarantee_force')) {
                        $this->_helper->showException('You need to be at least 18 years old');
                    }
                }
            }
            // Validate fraud prevention form values
            $this->fraudModuleValidation($methodSession);
            break;
        case Novalnet_Payment_Model_Config::NN_PREPAYMENT:
            // Validate the payment reference values
            $referenceOne = $this->getNovalnetConfig('payment_ref_one');
            $referenceTwo = $this->getNovalnetConfig('payment_ref_two');
            $referenceThree = $this->getNovalnetConfig('payment_ref_three');

            if (!$referenceOne && !$referenceTwo && !$referenceThree) {
                $this->_helper->showException('Please select atleast one payment reference.');
            }
            break;
        }
    }

    /**
     * fraud modules validation
     *
     * @param  Varien_Object $methodSession
     * @return throw Mage Exception|none
     */
    public function fraudModuleValidation($methodSession)
    {
        $callbackType = $this->getNovalnetConfig('callback');

        if ($callbackType && !$this->_helper->checkIsAdmin() && $this->checkCallbackAmount()) {
            $callbackTelNo = "getCallbackTel" . ucfirst($this->code);
            $callbackEmail = "getCallbackEmail" . ucfirst($this->code);

            if ($callbackType == '1' && !$methodSession->$callbackTelNo()) {
                $this->_helper->showException('Please enter your telephone number');
            } elseif ($callbackType == '2' && !$methodSession->$callbackTelNo()) {
                $this->_helper->showException('Please enter your mobile number');
            } elseif ($callbackType == '3' && !$this->validateEmail($methodSession->$callbackEmail())) {
                $this->_helper->showException('Your E-mail address is invalid');
            }
        }
    }

    /**
     * Verify the payment method values
     *
     * @param  null
     * @return null
     */
    public function checkMethodSession()
    {
        if (!$this->_helper->checkIsAdmin()) {
            $checkoutSession = $this->_helper->getCheckoutSession();
            $customerSession = $this->_helper->getCustomerSession();
            $previousPaymentCode = $checkoutSession->getNnPaymentCode();

            // Unset payment method session (for pre-select Novalnet payment)
            $paymentCode = $checkoutSession->getQuote()->getPayment()->getMethod();
            if ($paymentCode && $previousPaymentCode
                && $paymentCode != $previousPaymentCode
            ) {
                $this->_helper->unsetMethodSession($previousPaymentCode);
            }

            $paymentSucess = $this->getNovalnetConfig('payment_last_success', true);
            if ($customerSession->isLoggedIn()
                && empty($paymentCode) && $paymentSucess
            ) {
                $this->getLastSuccessOrderMethod($customerSession->getId(), $checkoutSession);
            }
        }
    }

    /**
     * Get last successful payment method
     *
     * @param int                         $customerId
     * @param Mage_Checkout_Model_Session $checkoutSession
     */
    public function getLastSuccessOrderMethod($customerId, $checkoutSession)
    {
        $tablePrefix = Mage::getConfig()->getTablePrefix();
        $orderTable = $tablePrefix.'sales_flat_order';
        $onCondition = "main_table.parent_id = $orderTable.entity_id";
        $orderCollection =  Mage::getModel('sales/order_payment')->getCollection()
                                         ->addAttributeToSort('created_at', 'DESC')
                                         ->addFieldToFilter('customer_id', $customerId)
                                         ->addFieldToFilter('method', array('like' => '%novalnet%'))
                                         ->addFieldToSelect('method')
                                         ->setPageSize(1);
        $orderCollection->getSelect()->join($orderTable, $onCondition);
        $count = $orderCollection->count();
        if ($count > 0) {
            foreach($orderCollection as $order):
                $paymentMethod = $order->getMethod();
            endforeach;
            $checkoutSession->getQuote()->getPayment()->setMethod($paymentMethod);
        }
    }

    /**
     * Check customer DOB is valid
     *
     * @param  string $birthDate
     * @return boolean
     */
    public function validateBirthDate($birthDate)
    {
        $birthDate = strtotime($birthDate);
        $age = strtotime('+18 years', $birthDate);
        return (time() < $age) ? false : true;
    }

    /**
     * Check fraud prevention availability
     *
     * @param  none
     * @return boolean
     */
    public function checkCallbackAmount()
    {
        $grandTotal = $this->_helper->getCheckoutSession()->getQuote()->getBaseGrandTotal();
        $amount = $this->_helper->getFormatedAmount($grandTotal);
        $minimumAmount = (int) $this->getNovalnetConfig('callback_minimum_amount');
        return ($minimumAmount ? $amount >= $minimumAmount : true);
    }

    /**
     * Validate payment request params
     *
     * @param  Varien_Object $request
     * @return boolean
     */
    public function validateMandateParams($request)
    {
        if ($this->checkIsNumeric($request->getVendor()) && $request->getAuthCode()
            && $this->checkIsNumeric($request->getProduct())
            && $this->checkIsNumeric($request->getTariff())
        ) {
            return true;
        }
        // error request log - reference
        Mage::log($request->getData(), null, 'nn_error_req.log', true);
        return false;
    }

    /**
     * Check Novalnet invoice payment guarantee availability
     *
     * @param  Mage_Sales_Model_Quote|null $quote
     * @param  string                      $code
     * @return boolean
     */
    public function getPaymentGuaranteeStatus($quote, $code)
    {
        $this->code = $code; // Payment method code
        $methodSession = $this->_helper->getMethodSession($code); // Get payment method session
        $methodSession->setPaymentGuaranteeFlag(0);

        if ($this->getNovalnetConfig('payment_guarantee')) {
            $allowedCountryCode = Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('allowedCountry');
            $countryCode = strtoupper($quote->getBillingAddress()->getCountryId()); // Get country code
            $orderAmount = $this->_helper->getFormatedAmount($quote->getBaseGrandTotal()); // Get order amount
            if ($quote->hasNominalItems()) {
                $checkoutSession = $this->_helper->getCheckoutSession(); // Get checkout session
                // Get order amount (Add initial fees if exist)
                $orderAmount = $this->_helper->getFormatedAmount($checkoutSession->getNnRowAmount());
            }

            if (in_array($countryCode, $allowedCountryCode)
                && ($orderAmount >= 2000 && $orderAmount <= 500000)
                && ($quote->getBaseCurrencyCode() == 'EUR')
            ) {
                $methodSession->setPaymentGuaranteeFlag(1);
                return true;
            } elseif ($this->getNovalnetConfig('payment_guarantee_force')) {
                return true;
            }
            return false;
        }
        return true;
    }

}
