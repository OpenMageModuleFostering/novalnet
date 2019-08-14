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
 * @category  Novalnet
 * @package   Novalnet_Payment
 * @copyright Copyright (c) Novalnet AG
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Block_Method_Redirect extends Mage_Core_Block_Abstract
{

    protected function _toHtml()
    {
        $helper = Mage::helper('novalnet_payment'); // Get Novalnet payment helper
        $paymentCode = $this->getOrder()->getPayment()->getMethodInstance()->getCode(); // Get payment method code
        $actionUrl = $helper->getPayportUrl('redirect', $paymentCode); // Get Novalnet payport URL
        $params = $helper->getMethodSession($paymentCode)->getPaymentReqData(); // Get payment method session

        // Create form
        $form = new Varien_Data_Form();
        $form->setAction($actionUrl)
            ->setId($paymentCode)
            ->setName($paymentCode)
            ->setMethod(Novalnet_Payment_Model_Config::NOVALNET_RETURN_METHOD)
            ->setUseContainer(true);
        foreach ($params->getData() as $field => $value) {
            $form->addField($field, 'hidden', array('name' => $field, 'value' => $value));
        }

        // Save payment transaction request data
        $request = $helper->getModel('Service_Api_Response')->removeSensitiveData($params, $paymentCode);
        $transactionTraces = $helper->getModel('Mysql4_TransactionTraces');
        $transactionTraces->setOrderId($request->getOrderNo())
            ->setRequestData(serialize($request->getData()))
            ->setCreatedDate($helper->getCurrentDateTime())
            ->save();

        $submitButton = new Varien_Data_Form_Element_Submit(
            array(
            'value'    => $this->__('Click here if you are not redirected within 10 seconds...'),
            )
        );
        $submitButton->setId("submit_to_{$paymentCode}_button");
        $form->addElement($submitButton);

        $html = '<html><body>';
        $html.= $this->__('You will be redirected to Novalnet AG in a few seconds.');
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("' . $paymentCode . '").submit();</script>';
        $html.= '</body></html>';
        return $html;
    }

}
