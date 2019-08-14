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
class Novalnet_Payment_Block_Method_Form_Sepa extends Mage_Payment_Block_Form
{

    /**
     * Init default template for block
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('novalnet/method/form/Sepa.phtml');
    }

    /**
     * Get information to end user from config
     *
     * @param  none
     * @return string
     */
    public function getUserInfo()
    {
        return trim(strip_tags($this->getMethod()->getConfigData('booking_reference')));
    }

    /**
     * Get billing address information
     *
     * @param  none
     * @return array
     */
    public function getBillingInfo()
    {
        $checkoutSession = Mage::helper('novalnet_payment')->getCheckout();
        return $checkoutSession->getQuote()->getBillingAddress();
    }

    /**
     * Get form refill values
     *
     * @param  none
     * @return mixed
     */
    public function getRefillParams()
    {
        $refill = $this->getMethod()->getConfigData('sepa_audo_refill');
        $paymentRefill = $this->getMethod()->getConfigData('sepa_payment_refill');

        $panHash = '';
        $methodSession = Mage::helper('novalnet_payment')->getMethodSession($this->getMethodCode());
        $newForm = $methodSession ? $methodSession->getSepaNewForm() : '';

        if ($paymentRefill && Mage::getSingleton('customer/session')->isLoggedIn()) {
            // Order collection
            $customerId = Mage::helper('novalnet_payment')->getCustomerId(); // Get customer id
            $orderCollection = Mage::getResourceModel('sales/order_collection')
                       ->addAttributeToSelect('*')
                       ->addFieldToFilter('customer_id', $customerId);
            $order = $orderCollection->getLastItem();
            $paymentMethod = $order->getPayment() ? $order->getPayment()->getMethod() : '';
            $maskedAccountInfo = $this->getMethod()->getMaskedAccountInfo();
            $panHash = ($paymentMethod == Novalnet_Payment_Model_Config::NN_SEPA && $maskedAccountInfo)
                ? $maskedAccountInfo['pan_hash'] : '';
        } elseif ($refill) {
            $panHash = $methodSession ? $methodSession->getSepaHash() : '';
        }

        return array('sepaHash' => $panHash, 'sepaNewForm' => $newForm);
    }

    /**
     * Get payment logo available status
     *
     * @param  none
     * @return boolean
     */
    public function logoAvailableStatus()
    {
        return Mage::getStoreConfig('novalnet_global/novalnet/enable_payment_logo');
    }

    /**
     * Get payment mode (test/live)
     *
     * @param  none
     * @return boolean
     */
    public function getPaymentMode()
    {
        $paymentMethod = Mage::getStoreConfig('novalnet_global/novalnet/live_mode');
        return (!preg_match('/' . $this->getMethodCode() . '/i', $paymentMethod)) ? false : true;
    }

}
