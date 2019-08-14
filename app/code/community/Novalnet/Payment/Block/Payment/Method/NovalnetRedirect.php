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
 * http://www.novalnet.de
 * If you have found this script useful a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Copyright (c) Novalnet AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Block_Payment_Method_NovalnetRedirect extends Mage_Core_Block_Abstract
{

    protected function _toHtml()
    {
        $payment = $this->getOrder()->getPayment();
        $paymentObj = $payment->getMethodInstance();
        $helper = Mage::helper('novalnet_payment');
        $paymentCode = $payment->getMethodInstance()->getCode();
        $actionUrl = $helper->getPayportUrl('redirect', $paymentCode);
        $authorizeKey = $paymentObj->loadAffAccDetail();

        if ($authorizeKey) {
            $form = new Varien_Data_Form();
            $form->setAction($actionUrl)
                    ->setId($paymentCode)
                    ->setName($paymentCode)
                    ->setMethod(Novalnet_Payment_Model_Config::NOVALNET_RETURN_METHOD)
                    ->setUseContainer(true);

            $getFormData = $paymentObj->buildRequest()->getData();
            foreach ($getFormData as $field => $value) {
                $form->addField($field, 'hidden', array('name' => $field, 'value' => $value));
            }

            $logFormData = Mage::helper('novalnet_payment/AssignData')->doRemoveSensitiveData($getFormData, $paymentCode);
            $authCode = $paymentCode == Novalnet_Payment_Model_Config::NN_CC ? $logFormData['auth_code']
                        : $helper->getDecodedParam($logFormData['auth_code'], $authorizeKey);
            $productId = $paymentCode == Novalnet_Payment_Model_Config::NN_CC ? $logFormData['product']
                        : $helper->getDecodedParam($logFormData['product'], $authorizeKey);
            $tariffId = $paymentCode == Novalnet_Payment_Model_Config::NN_CC ? $logFormData['tariff']
                        : $helper->getDecodedParam($logFormData['tariff'], $authorizeKey);
            $data = array('vendor' => $logFormData['vendor'],
                'auth_code' => $authCode,
                'product' => $productId,
                'tariff' => $tariffId,
                'key' => $logFormData['key'],
                'authorize_key' => $authorizeKey
            );
            if ($paymentCode == Novalnet_Payment_Model_Config::NN_CC && $paymentObj->_getConfigData('active_cc3d')) {
                $data['ActiveCc3d'] = 1;
            }
            $payment->setAdditionalData(serialize($data))
                    ->save();

            //Save Transaction request data
            $modNnTransOverview = $helper->getModelTransactionOverview();
            $modNnTransOverview->setOrderId($getFormData['order_no'])
                    ->setRequestData(serialize($logFormData))
                    ->setCreatedDate($helper->getCurrentDateTime())
                    ->save();

            // IE & Firefox will not submit form if the form is full of hidden fileds.
            $form->addField('continue', 'submit', array('name' => 'Continue', 'value' => $this->__('Continue')));

            $html = '<html><body>';
            $html.= $this->__('You will be redirected to Novalnet AG in a few seconds.');
            $html.= $form->toHtml();
            $html.= '<script type="text/javascript">document.getElementById("' . $paymentCode . '").submit();</script>';
            $html.= '</body></html>';
            return $html;
        } else {
            $html = $this->__('Theres no password defined');
            return $html;
        }
    }
}
