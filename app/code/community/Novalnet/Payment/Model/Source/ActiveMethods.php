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
class Novalnet_Payment_Model_Source_ActiveMethods
{
    /**
     * Options getter (Active Novalnet payment methods)
     *
     * @param  none
     * @return array $methods
     */
    public function toOptionArray()
    {
        $methods = array();
        $activePayment = false;
        $inactivePayment = false;
        $scopeId = Mage::helper('novalnet_payment')->getScopeId(); // Get store id
        // Novalnet payment methods
        $novalnetPayments = array_keys(
            Novalnet_Payment_Model_Config::getInstance()->getNovalnetVariable('paymentMethods')
        );

        foreach ($novalnetPayments as $paymentCode) {
            $paymentActive = Mage::getStoreConfig('payment/' . $paymentCode . '/active', $scopeId);
            if ($paymentActive == true) {
                $paymentTitle = Mage::getStoreConfig('payment/' . $paymentCode . '/title', $scopeId);
                $methods[$paymentCode] = array(
                    'label' => $paymentTitle,
                    'value' => $paymentCode,
                );
                $activePayment = true;
            } else {
                $inactivePayment = true;
            }
        }

        if (!$activePayment && $inactivePayment) {
            $methods[$paymentCode] = array(
                'label' => Mage::helper('novalnet_payment')->__('No active payment method for this store'),
                'value' => false,
            );
        }
        return $methods;
    }

}
