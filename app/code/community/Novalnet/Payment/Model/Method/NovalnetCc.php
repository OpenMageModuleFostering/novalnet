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
class Novalnet_Payment_Model_Method_NovalnetCc extends Novalnet_Payment_Model_Method_Abstract
{
    protected $_code = Novalnet_Payment_Model_Config::NN_CC;
    protected $_canUseInternal = Novalnet_Payment_Model_Config::NN_CC_CAN_USE_INTERNAL;
    protected $_canUseForMultishipping = Novalnet_Payment_Model_Config::NN_CC_CAN_USE_MULTISHIPPING;
    protected $_formBlockType = Novalnet_Payment_Model_Config::NN_CC_FORM_BLOCK;
    protected $_infoBlockType = Novalnet_Payment_Model_Config::NN_CC_INFO_BLOCK;

    /**
     * Validate payment method information object
     *
     * @param  none
     * @return Mage_Payment_Model_Abstract
     */
    public function validate()
    {
        parent::validate();
        $info = $this->getInfoInstance(); // Current payment instance

        if ($info instanceof Mage_Sales_Model_Quote_Payment) {
            $this->validateModel->validateFormInfo(); // Validate the form values
        }

        return $this;
    }

    /**
     * Assign data to info model instance
     *
     * @param  mixed $data
     * @return Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        if ($data) {
            if (!($data instanceof Varien_Object)) {
                $data = new Varien_Object($data);
            }

            $methodSession = $this->helper->getMethodSession($this->_code); // Get payment method session

            if ($this->getFormValues('cc_oneclick_shopping')
                && $this->getFormValues('cc_enter_data') && $data->getCcCid()
            ) {
                $maskedCardInfo = $this->getMaskedCardInfo();
                $methodSession->setNnCcTid($maskedCardInfo['nn_tid'])
                    ->setNnCcCvc($data->getCcCid());
            } elseif ($methodSession->hasNnCcTid()) {
                $methodSession->unsNnCcTid();
            }
        }

        return $this;
    }

    /**
     * Get Novalnet payment redirect URL
     *
     * @param  none
     * @return string $actionUrl
     */
    public function getOrderPlaceRedirectUrl()
    {
        $methodSession = $this->helper->getMethodSession($this->_code); // Get payment method session

        if ($methodSession->getNnCcTid()) {
            // Credit Card payment redirect url
            $actionUrl = $this->helper->getUrl(Novalnet_Payment_Model_Config::GATEWAY_DIRECT_URL);
        } elseif ($this->getConfigData('cc_form_type') == 1) {
            // Credit Card hosted iframe payment redirect url
            $actionUrl = $this->helper->getUrl(Novalnet_Payment_Model_Config::CC_IFRAME_URL);
        } else {
            // Credit Card 3D Secure payment redirect url
            $actionUrl = $this->helper->getUrl(Novalnet_Payment_Model_Config::GATEWAY_REDIRECT_URL);
        }

        return $actionUrl;
    }

    /**
     * Get the credit card informations
     *
     * @param  string $param
     * @return string
     */
    public function getFormValues($param)
    {
        return Mage::app()->getRequest()->getPost($param);
    }

    /**
     * Get existing card details
     *
     * @param  none
     * @return Varien_Object $paymentValues
     */
    public function getMaskedCardInfo()
    {
        $paymentValues = '';
        $customerId = $this->helper->getCustomerId(); // Get customer id

        if ($customerId) {
            // Get Masked Credit Card informations if available for the customer
            $collection = $this->helper->getModel('Mysql4_TransactionStatus')->getCollection()
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('payment_name', $this->_code)
                ->addFieldToFilter('reference_transaction', 0)
                ->addFieldToSelect('novalnet_acc_details');
            $paymentValues = $collection->getLastItem()->hasNovalnetAccDetails()
                    ? unserialize(base64_decode($collection->getLastItem()->getNovalnetAccDetails())) : '';
        }
        return $paymentValues;
    }
}
