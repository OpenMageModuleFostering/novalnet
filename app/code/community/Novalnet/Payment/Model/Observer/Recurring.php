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
class Novalnet_Payment_Model_Observer_Recurring
{

    /**
     * Get recurring product custom option values
     *
     * @param  varien_object $observer
     * @return Novalnet_Payment_Model_Observer_Recurring
     */
    public function getProfilePeriodValues(Varien_Event_Observer $observer)
    {
        $quote = $observer->getEvent()->getCart()->getQuote(); // Get quote object

        foreach ($quote->getAllItems() as $items) {
            if ($items->getProduct()->isRecurring()) {
                $recurringProfile = $items->getProduct()->getRecurringProfile(); // Get profile object
                // Get recurring profile period values
                Mage::getSingleton('checkout/session')->setNnPeriodUnit($recurringProfile['period_unit'])
                        ->setNnPeriodFrequency($recurringProfile['period_frequency']);
            }
        }
    }

    /**
     * Set redirect url for recurring payment (Credit Card PCI)
     *
     * @param  varien_object $observer
     * @return Novalnet_Payment_Model_Observer_Recurring
     */
    public function setPaymentRedirectUrl(Varien_Event_Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote(); // Get quote object
        $paymentCode = $quote->getPayment()->getMethodInstance()->getCode(); // Get payment method code
        $helper = Mage::helper('novalnet_payment'); // Novalnet payment helper
        $checkoutSession = $helper->getCheckoutSession(); // Get checkout session

        // Get recurring profile id
        $profileIds = $checkoutSession->getLastRecurringProfileIds();
        $recurringProfileIds = !empty($profileIds) ? array_filter($profileIds) : '';
        $redirectPayments = array(Novalnet_Payment_Model_Config::NN_CC, Novalnet_Payment_Model_Config::NN_PAYPAL);

        if (!empty($recurringProfileIds) && in_array($paymentCode, $redirectPayments)) {
            $methodSession = $helper->getMethodSession($paymentCode); // Payment method session
            if ($paymentCode == Novalnet_Payment_Model_Config::NN_CC) {
                $redirectUrl = $methodSession->getNnCcTid()
                    ? $helper->getUrl(Novalnet_Payment_Model_Config::GATEWAY_DIRECT_URL)
                    : ($methodSession->getCcFormType() == 1
                      ? $helper->getUrl(Novalnet_Payment_Model_Config::CC_IFRAME_URL)
                      : $helper->getUrl(Novalnet_Payment_Model_Config::GATEWAY_REDIRECT_URL));
            } elseif ($paymentCode == Novalnet_Payment_Model_Config::NN_PAYPAL) {
                $redirectUrl = $helper->getUrl(Novalnet_Payment_Model_Config::GATEWAY_REDIRECT_URL);
            }

            $checkoutSession->setLastOrderId($methodSession->getOrderId())
                ->setLastRealOrderId($methodSession->getIncrementId())
                ->setRedirectUrl($redirectUrl);
        }
    }

}
