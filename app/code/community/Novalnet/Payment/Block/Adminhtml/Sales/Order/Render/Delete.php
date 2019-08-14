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
class Novalnet_Payment_Block_Adminhtml_Sales_Order_Render_Delete
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Render the delete button
     *
     * @param  Varien_Object $row
     * @return mixed $result
     */
    public function render(Varien_Object $row)
    {
        $info = $row->getData();
        $orderId = $info['entity_id'];
        $message = Mage::helper('sales')->__('Are you sure you want to delete this order?');
        $viewLink = $this->getUrl('adminhtml/sales_order/view', array('order_id'=> $orderId));
        $deleteLink = $this->getUrl(
            'adminhtml/novalnetpayment_sales_deleteorder/delete', array('order_id' => $orderId)
        );
        $result = '<a href="'.$viewLink.'">View</a>';
        $result .= '&nbsp;&nbsp;&nbsp;&nbsp;';
        $result .= '<a href="#" onclick="deleteConfirm(\''.$message.'\', \'' . $deleteLink . '\')">Delete</a>';
        return $result;
    }

}
