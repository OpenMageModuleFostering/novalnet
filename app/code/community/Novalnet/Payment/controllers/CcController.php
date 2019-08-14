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
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Novalnet AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_CcController extends Mage_Core_Controller_Front_Action
{

    /**
     * Get Credit Card iframe
     *
     * @param none
     * @return none
     */
    public function indexAction()
    {
        // Loading current layout
        $this->loadLayout();

        // Creating a new block
        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template', 'CcForm', array(
            'template' => 'novalnet/payment/method/form/Cciframe.phtml'));
        $this->getLayout()->getBlock('content')->append($block);

        // Set on-hold status
        $helper = $this->_getHelper();
        $order = $this->_getOrder();
        $payment = $order->getPayment();
        $paymentObj = $payment->getMethodInstance();
        $authorizeKey = $paymentObj->loadAffAccDetail();
        $paymentRequest = $helper->getCheckoutSession()->getPaymentReqData();

        if(!$payment->getLastTransId()) {
            // Get Vendor configuration values from payport request
            $authCode = $helper->getDecodedParam($paymentRequest->getVendorAuthcode(), $authorizeKey);
            $productId = $helper->getDecodedParam($paymentRequest->getProductId(), $authorizeKey);
            $tariffId = $helper->getDecodedParam($paymentRequest->getTariffId(), $authorizeKey);

            // Payment additional data
            $data = array('vendor' => $paymentRequest->getVendorId(),
                    'auth_code' => $authCode,
                    'product' => $productId,
                    'tariff' => $tariffId,
                    'key' => $paymentRequest->getKey(),
                    'authorize_key' => $authorizeKey
                );

            $payment->setAdditionalData(serialize($data))->save();
            $status = $state = Mage_Sales_Model_Order::STATE_HOLDED; //set State,Status to HOLD
            $order->setState($state, $status, $helper->__('Customer was redirected to Novalnet'), false)->save();
            $helper->getCheckoutSession()->unsRecurringProfileNumber(); //unset recurring profile data

            //Now showing it with rendering of layout
            $this->renderLayout();
        } else {
            $this->_redirect('checkout/cart');
        }
    }

    /**
     * Get Last placed order object
     *
     * @param none
     * @return Varien_Object
     */
    private function _getOrder()
    {
        $incrementId = $this->_getHelper()->getCheckoutSession()->getLastRealOrderId();
        return Mage::getModel('sales/order')->loadByIncrementId($incrementId);
    }

    /**
     * Get Novalnet payment helper
     *
     * @param none
     * @return Novalnet_Payment_Helper_Data
     */
    private function _getHelper()
    {
        return Mage::helper('novalnet_payment');
    }

}
