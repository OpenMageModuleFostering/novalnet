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
class Novalnet_Payment_Block_Adminhtml_Recurring_Profile_View extends Mage_Sales_Block_Adminhtml_Recurring_Profile_View
{

    /**
     * Prepare layout for recurring profile
     *
     * @param  none
     * @return Mage_Sales_Block_Adminhtml_Recurring_Profile_View
     */
    protected function _prepareLayout()
    {
        $profile = Mage::registry('current_recurring_profile');

        if (!preg_match("/novalnet/i", $profile->getMethodCode())) {
            return parent::_prepareLayout();
        }

        $this->_addButton(
            'back', array(
            'label' => Mage::helper('adminhtml')->__('Back'),
            'onclick' => "setLocation('{$this->getUrl('*/*/')}')",
            'class' => 'back'
            )
        );
        // Get transaction information
        $transactionStatus = $this->getTransactionStatus($profile);

        $comfirmationMessage = Mage::helper('sales')->__('Are you sure you want to do this?');

        // cancel
        if ($profile->canCancel() && $transactionStatus->getAmount()) {
            $url = $this->getUrl(
                '*/*/updateState', array('profile' => $profile->getId(),
                'action' => 'cancel')
            );
            $this->_addButton(
                'cancel', array(
                'label' => Mage::helper('sales')->__('Cancel'),
                'onclick' => "cancelButtonViewStatus('recurring_buttons_view','recurring_cancel_button_view')",
                'class' => 'delete',
                )
            );
        }
        // suspend
        $state = $profile->getState();
        if ($profile->canSuspend() && $state != 'pending' && $transactionStatus->getAmount()) {
            $url = $this->getUrl(
                '*/*/updateState', array('profile' => $profile->getId(),
                'action' => 'suspend')
            );
            $this->_addButton(
                'suspend', array(
                'label' => Mage::helper('sales')->__('Suspend'),
                'onclick' => "confirmSetLocation('{$comfirmationMessage}', '{$url}')",
                'class' => 'delete',
                )
            );
        }

        // activate
        if ($profile->canActivate() && $state != 'pending') {
            $url = $this->getUrl(
                '*/*/updateState', array('profile' => $profile->getId(),
                'action' => 'activate')
            );
            $this->_addButton(
                'activate', array(
                'label' => Mage::helper('sales')->__('Activate'),
                'onclick' => "confirmSetLocation('{$comfirmationMessage}', '{$url}')",
                'class' => 'add',
                )
            );
        }
    }

    /**
     * Set title and a hack for tabs container
     *
     * @param  none
     * @return Mage_Sales_Block_Adminhtml_Recurring_Profile_View
     */
    protected function _beforeToHtml()
    {
        $profile = Mage::registry('current_recurring_profile');
        $this->_headerText = Mage::helper('sales')->__('Recurring Profile # %s', $profile->getReferenceId());
        $this->setViewHtml('<div id="' . $this->getDestElementId() . '"></div>');
        return parent::_beforeToHtml();
    }

    /**
     * Get cancel reasons for recurring cancel
     *
     * @param  none
     * @return mixed
     */
    protected function _getCancelButtonWithReasons()
    {
        $profile = Mage::registry('current_recurring_profile');
        $comfirmationMessage = Mage::helper('sales')->__('Are you sure you want to do this?');
        $helper = Mage::helper('sales');
        $lang = $helper->__("Please select reason,");
        $lang .= $helper->__("Product is costly,");
        $lang .= $helper->__("Cheating,");
        $lang .= $helper->__("Partner interfered,");
        $lang .= $helper->__("Financial problem,");
        $lang .= $helper->__("Content does not match my likes,");
        $lang .= $helper->__("Content is not enough,");
        $lang .= $helper->__("Interested only for a trial,");
        $lang .= $helper->__("Page is very slow,");
        $lang .= $helper->__("Satisfied customer,");
        $lang .= $helper->__("Logging in problems,");
        $lang .= $helper->__("Other reasons");
        $cancelview = "";
        if ($profile->canCancel()) {
            $cancelReason = $helper->__("Please select the reason of subscription cancellation");
            $select = Mage::app()->getLayout()->createBlock('core/html_select')
                    ->setName("cancel_reason")
                    ->setId("reason-unsubscribe")
                    ->setOptions(explode(",", $lang));

            $cancelview .= $select->getHtml();

            $url = $this->getUrl(
                '*/*/updateState', array('profile' => $profile->getId(),
                'action' => 'cancel')
            );
            $this->setChild(
                'cancel', $this->getLayout()->createBlock('adminhtml/widget_button')->setData(
                    array(
                        'label' => Mage::helper('sales')->__('Cancel'),
                        'onclick' => "subscriptionCancel('{$comfirmationMessage}', '{$url}', '{$cancelReason}')",
                        'class' => 'delete',
                    )
                )
            );
            $cancelview .= $this->getChildHtml('cancel');
        }
        return $cancelview;
    }

    /**
     * Get transaction information
     *
     * @param  Varien_Object $profile
     * @return Varien_Object
     */
    public function getTransactionStatus($profile)
    {
        $transactionId = $profile->getReferenceId();
        // load transaction status information
        $helper = Mage::helper('novalnet_payment'); // Novalnet payment helper
        $transactionStatus = $helper->getModel('Mysql4_TransactionStatus')
            ->loadByAttribute('transaction_no', $transactionId);
        return $transactionStatus;
    }

}
