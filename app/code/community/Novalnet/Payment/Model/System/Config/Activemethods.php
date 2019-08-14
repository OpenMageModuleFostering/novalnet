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
 * Part of the Paymentmodule of Novalnet AG
 * https://www.novalnet.de
 * If you have found this script usefull a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Novalnet AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Model_System_Config_Activemethods {

    public function toOptionArray() {
        $methods = array();
        $activePayment = false;
        $inactivePayment = false;
 
		if (strlen($code = Mage::getSingleton('adminhtml/config_data')->getStore()))  { // store level
			$scopeId = Mage::getModel('core/store')->load($code)->getId();
		}
		elseif (strlen($code = Mage::getSingleton('adminhtml/config_data')->getWebsite())) { // website level
			$website_id = Mage::getModel('core/website')->load($code)->getId();
			$scopeId = Mage::app()->getWebsite($website_id)->getDefaultStore()->getId();
		}
		else { // default level
			$scopeId = 0;
		}

        $payments = Mage::getSingleton('payment/config')->getActiveMethods($scopeId);
		foreach ($payments as $paymentCode => $paymentModel) {
		
            if (preg_match("/novalnet/i", $paymentCode)) {

                $paymentActive = Mage::getStoreConfig('payment/' . $paymentCode . '/active', $scopeId);
                if ($paymentActive == true) {
                    $paymentTitle = Mage::getStoreConfig('payment/' . $paymentCode . '/title');
                    $methods[$paymentCode] = array(
                        'label' => $paymentTitle,
                        'value' => $paymentCode,
                    );
                    $activePayment = true;
                }
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
