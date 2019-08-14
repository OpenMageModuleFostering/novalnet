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
class Novalnet_Payment_Block_Method_Info_Eps extends Mage_Payment_Block_Info
{

    /**
     * Init default template for block
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('novalnet/method/info/Eps.phtml');
    }

    /**
     * Render as PDF
     *
     * @param  none
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('novalnet/method/pdf/Eps.phtml');
        return $this->toHtml();
    }

    /**
     * Get some specific information
     *
     * @param  string $key
     * @return array
     */
    public function getAdditionalData($key)
    {
        return Mage::helper('novalnet_payment')->getAdditionalData($this->getInfo(), $key);
    }

    /**
     * Retrieve payment method model
     *
     * @param  none
     * @return Mage_Payment_Model_Method_Abstract
     */
    public function getMethod()
    {
        return $this->getInfo()->getMethodInstance();
    }

}
