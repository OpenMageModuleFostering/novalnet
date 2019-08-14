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
 * @category   Mage
 * @package    Mage_Novalnet
 * @copyright  Novalnet AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Model_Novalnet_Source_Sepatype
{
    /**
     * Return DD SEPA types for current method
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array( 'DD_SEPA' => Mage::helper('novalnet_payment')->__('SEPA'),
                      'DD_SEPA_SIGNED' => Mage::helper('novalnet_payment')->__('SEPA SIGNED'),
                   );
    }
}
