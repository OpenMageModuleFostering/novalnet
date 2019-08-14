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
require_once 'Mage' . DS . 'Adminhtml' . DS . 'controllers' . DS . 'Sales' . DS . 'Recurring' . DS . 'ProfileController.php';

class Novalnet_Payment_Adminhtml_Novalnetpayment_Sales_Recurring_ProfileController
    extends Mage_Adminhtml_Sales_Recurring_ProfileController
{

    /**
     * Recurring profiles list
     *
     * @param  none
     * @return Mage_Adminhtml_Sales_Recurring_ProfileController
     */
    public function indexAction()
    {
        $helper = Mage::helper('novalnet_payment');
        $this->_title($helper->__('Sales'))->_title($helper->__('Novalnet Recurring Profiles'))
            ->loadLayout()
            ->_setActiveMenu('novalnet')
            ->renderLayout();
        return $this;
    }

    /**
     * Profiles ajax grid
     */
    public function gridAction()
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('novalnet_payment/adminhtml_recurring_profile_grid')->toHtml()
        );
    }

}
