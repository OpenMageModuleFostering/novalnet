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
 * Part of the Paymentmodul of Novalnet AG
 * http://www.novalnet.de
 * If you have found this script usefull a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Copyright (c) Novalnet AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Block_Payment_Method_NovalnetRedirect extends Mage_Core_Block_Abstract {

    protected function _toHtml() {
        $payment = $this->getOrder()->getPayment()->getMethodInstance();
        $helper = Mage::helper('novalnet_payment');
	$paymentCode = $payment->getCode();

        if ($payment->_getConfigData('password', true)
			|| $paymentCode == Novalnet_Payment_Model_Config::NN_CC3D) {
            $form = new Varien_Data_Form();
            $form->setAction($payment->getConfigData('url'))
                    ->setId($paymentCode)
                    ->setName($paymentCode)
                    ->setMethod(Novalnet_Payment_Model_Config::NOVALNET_RETURN_METHOD)
                    ->setUseContainer(true);

            $getFormData = $payment->buildRequest()->getData();
            $replacedFormData = Mage::helper('novalnet_payment/AssignData')->replaceParamsBasedOnPayment($getFormData, $paymentCode);
            foreach ($replacedFormData as $field => $value) {
                $form->addField($field, 'hidden', array('name' => $field, 'value' => $value));
            }

            $logFormData = Mage::helper('novalnet_payment/AssignData')->doRemoveSensitiveData($replacedFormData, $paymentCode);
       	    $authorizeKey = $payment->_getConfigData('password', true);            
            $authCode = $paymentCode == Novalnet_Payment_Model_Config::NN_CC3D ? $logFormData['auth_code'] : $helper->getDecodedParam($logFormData['auth_code'] ,$authorizeKey);            
            $productId = $paymentCode == Novalnet_Payment_Model_Config::NN_CC3D ? $logFormData['product'] : $helper->getDecodedParam($logFormData['product'] ,$authorizeKey);            
            $tariffId = $paymentCode == Novalnet_Payment_Model_Config::NN_CC3D ? $logFormData['tariff'] : $helper->getDecodedParam($logFormData['tariff'] ,$authorizeKey);            
			$data = array('vendor' 	    => $logFormData['vendor'],
						  'auth_code'   => $authCode,
						  'product'     => $productId,
						  'tariff'      => $tariffId,
						  'key'         => $logFormData['key']
					   );			
	   $payment = $this->getOrder()->getPayment();
	   $payment->setAdditionalData(serialize($data))
		   ->save();

            //Save Transaction request data
            $modNovalTransactionOverview = $helper->getModelTransactionOverview();
            $modNovalTransactionOverview->setOrderId($replacedFormData['order_no'])
                    ->setRequestData(serialize($logFormData))
                    ->setCreatedDate($helper->getCurrentDateTime())
                    ->save();

            // IE & Firefox will not submit form if the form is full of hidden fileds.
            $form->addField('continue', 'submit', array('name' => 'Continue', 'value' => $this->__('Continue')));
            $html = '<html><body>';
            $html.= $this->__('You will be redirected to Novalnet AG website when you place an order.');
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
