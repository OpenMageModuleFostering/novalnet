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
class Novalnet_Payment_Model_Source_CustomerGroups
{
    /**
     * Options getter (customer groups)
     *
     * @param  none
     * @return array $options
     */
    public function toOptionArray()
    {
        $collection = Mage::getModel('customer/group')->getCollection();
        $groups = array();
        foreach ($collection as $group) {
            $groupInfo = array(
                'customer_group_id' => $group->getCustomerGroupId(),
                'customer_group_code' => $group->getCustomerGroupCode(),
            );

            if (!empty($groupInfo)) {
                array_push($groups, $groupInfo);
            }
        }

        foreach ($groups as $name) {
            $options[] = array(
                'value' => $name['customer_group_id'],
                'label' => $name['customer_group_code']
            );
        }

        return $options;
    }
}
