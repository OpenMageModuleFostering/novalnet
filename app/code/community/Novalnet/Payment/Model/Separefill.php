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
 * @package Novalnet_Payment
 * @copyright  Copyright (c) Novalnet AG. (https://www.novalnet.de)
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Model_Separefill extends Mage_Core_Model_Abstract
{
    /**
     * Constructor
     *
     * @see lib/Varien/Varien_Object#_construct()
     * @return Novalnet_Payment_Model_Separefill
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('novalnet_payment/separefill');
    }

    /**
     * Get PanHash
     *
     * @param Novalnet_Payment_Helper_Data $helper
     * @return mixed
     */
    public function getSepaPanHash($helper)
    {
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        $modNovalSeparefill = $helper->getModelSepaRefill()->getCollection();
        $modNovalSeparefill->addFieldToFilter('customer_id', $customerId);
        $modNovalSeparefill->addFieldToSelect('pan_hash');
        $modNovalSeparefillcount = count($modNovalSeparefill);
        if ($modNovalSeparefillcount > 0) {
            foreach ($modNovalSeparefill as $modNovalSeparefillvalue) {
                $panhash = $modNovalSeparefillvalue->getPanHash();
            }
        } else {
            $panhash = '';
        }
        return $panhash;
    }
}
