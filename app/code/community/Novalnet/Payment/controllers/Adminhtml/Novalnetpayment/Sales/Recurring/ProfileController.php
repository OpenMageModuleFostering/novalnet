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
 * @package    Novalnet_Payment
 * @copyright  Copyright (c) Novalnet AG. (https://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

require_once 'Mage' . DS . 'Adminhtml'. DS . 'controllers' . DS . 'Sales' . DS . 'Recurring' . DS . 'ProfileController.php';

class Novalnet_Payment_Adminhtml_Novalnetpayment_Sales_Recurring_ProfileController extends Mage_Adminhtml_Sales_Recurring_ProfileController
{
   /**
     * Recurring profiles list
     *
     * @return Mage_Adminhtml_Sales_Recurring_ProfileController
     */
    public function indexAction()
    {
        $this->_title(Mage::helper('novalnet_payment')->__('Sales'))->_title(Mage::helper('novalnet_payment')->__('Novalnet Recurring Profiles'))
            ->loadLayout()
            ->_setActiveMenu('novalnet')
            ->renderLayout();
        return $this;
    }

    /**
     * View recurring profile information's
     *
     */
    public function viewAction()
    {
        try {
            $this->_title(Mage::helper('sales')->__('Sales'))->_title(Mage::helper('sales')->__('Recurring Profiles'));
            $profile = $this->_initProfile();
            $this->loadLayout()
                ->_setActiveMenu('sales/recurring_profile')
                ->_title(Mage::helper('sales')->__('Profile #%s', $profile->getReferenceId()))
                ->renderLayout()
            ;
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
        }
        $this->_redirect('*/*/');
    }

    /**
     * Profiles ajax grid
     *
     */
    public function gridAction()
    {
        $this->getResponse()->setBody(
                $this->getLayout()->createBlock('novalnet_payment/adminhtml_recurring_profile_grid')->toHtml()
        );
    }

    /**
     * Profile orders ajax grid
     *
     */
    public function ordersAction()
    {
        try {
            $this->_initProfile();
            $this->loadLayout()->renderLayout();
        } catch (Exception $e) {
            Mage::logException($e);
            $this->norouteAction();
        }
    }

    /**
     * Profile state updater action
     *
     */
    public function updateStateAction()
    {
        $profile = null;
        try {
            $profile = $this->_initProfile();

            switch ($this->getRequest()->getParam('action')) {
                case 'cancel':
                    $profile->cancel();
                    break;
                case 'suspend':
                    $profile->suspend();
                    break;
                case 'activate':
                    $profile->activate();
                    break;
            }
            $this->_getSession()->addSuccess(Mage::helper('sales')->__('The profile state has been updated.'));
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError(Mage::helper('sales')->__('Failed to update the profile.'));
            Mage::logException($e);
        }
        if ($profile) {
            $this->_redirect('*/*/view', array('profile' => $profile->getId()));
        } else {
            $this->_redirect('*/*/');
        }
    }

    /**
     * Profile information updater action
     *
     */
    public function updateProfileAction()
    {
        $profile = null;
        try {
            $profile = $this->_initProfile();
            $profile->fetchUpdate();
            if ($profile->hasDataChanges()) {
                $profile->save();
                $this->_getSession()->addSuccess($this->__('The profile has been updated.'));
            } else {
                $this->_getSession()->addNotice($this->__('The profile has no changes.'));
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Failed to update the profile.'));
            Mage::logException($e);
        }
        if ($profile) {
            $this->_redirect('*/*/view', array('profile' => $profile->getId()));
        } else {
            $this->_redirect('*/*/');
        }
    }

    /**
     * Cutomer billing agreements ajax action
     *
     */
    public function customerGridAction()
    {
        $this->_initCustomer();
        $this->loadLayout(false)
            ->renderLayout();
    }

    /**
     * Initialize customer by ID specified in request
     *
     * @return Mage_Adminhtml_Sales_Billing_AgreementController
     */
    protected function _initCustomer()
    {
        $customerId = (int) $this->getRequest()->getParam('id');
        $customer = Mage::getModel('customer/customer');

        if ($customerId) {
            $customer->load($customerId);
        }

        Mage::register('current_customer', $customer);
        return $this;
    }

    /**
     * Load/set profile
     *
     * @return Mage_Sales_Model_Recurring_Profile
     */
    protected function _initProfile()
    {
        $profile = Mage::getModel('sales/recurring_profile')->load($this->getRequest()->getParam('profile'));
        if (!$profile->getId()) {
            Mage::throwException($this->__('Specified profile does not exist.'));
        }
        Mage::register('current_recurring_profile', $profile);
        return $profile;
    }
}