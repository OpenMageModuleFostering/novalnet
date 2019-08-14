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
class Novalnet_Payment_Block_Payment_Method_Form_Cc extends Mage_Payment_Block_Form
{
    /**
     * Init default template for block
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('novalnet/payment/method/form/Cc.phtml');
    }

    /**
     * Check whether Callback type allowed
     *
     * @return bool
     */
    public function isCallbackTypeCall()
    {
        return $this->getMethod()->isCallbackTypeCall();
    }

    /**
     * Novalnet Callback data getter
     *
     * @return string
     */
    public function getCallbackConfigData()
    {
        return $this->getMethod()->getNovalnetConfig('callback');
    }

    /**
     * Retrieve availables Credit Card types
     *
     * @return array
     */
    public function getCcAvailableTypes()
    {
        $types = array(
            'VI' => 'Visa',
            'MC' => 'MasterCard',
            'AE' => 'American Express',
            'TO' => 'Maestro',
            'T' => 'CarteSi',
        );
        $method = $this->getMethod();
        if ($method) {
            $availableTypes = $method->getConfigData('cctypes');
            if ($availableTypes) {
                $availableTypes = explode(',', $availableTypes);
                foreach ($types as $code => $name) {
                    if (!in_array($code, $availableTypes)) {
                        unset($types[$code]);
                    }
                }
            }
        }
        return $types;
    }

    /**
     * Retrieve payment configuration object
     *
     * @return Mage_Payment_Model_Config
     */
    protected function _getConfig()
    {
        if (!$this->_localConfig) {
            $this->_localConfig = Mage::getModel('payment/config');
        }
        return $this->_localConfig;
    }

    /**
     * Retrieve Credit Card expiry months
     *
     * @return array
     */
    public function getCcMonths()
    {
        $months = $this->getData('cc_months');
        if (is_null($months)) {
            $months = $this->_getConfig()->getMonths();
            $this->setData('cc_months', $months);
        }
        return $months;
    }

    /**
     * Retrieve Credit Card expiry years
     *
     * @return array
     */
    public function getCcYears()
    {
        $years = $this->getData('cc_years');
        if (is_null($years)) {
            $years = $this->getYears();
            $this->setData('cc_years', $years);
        }
        return $years;
    }

    /**
     * Retrieve Credit Card expiry years from Novalnet configuration
     *
     * @return array
     */
    private function getYears()
    {
        $method = $this->getMethod();
        $configYears = $method->getConfigData('cc_valid_year');
        $count = $configYears ? $configYears : 25;

        $years = array();
        $first = date("Y");

        for ($index = 0; $index < $count; $index++) {
            $year = $first + $index;
            $years[$year] = $year;
        }
        return $years;
    }

    /**
     * Get information to end user from config
     *
     * @return string
     */
    public function getUserInfo()
    {
        return trim(strip_tags(trim($this->getMethod()->getConfigData('booking_reference'))));
    }

}
