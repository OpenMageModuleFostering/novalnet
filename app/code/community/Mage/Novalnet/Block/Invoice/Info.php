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
 * @category   design_default
 * @package    Mage
 * @copyright  Copyright (c) 2008 Novalnet AG
 * @version    1.0.0
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Novalnet_Block_Invoice_Info extends Mage_Payment_Block_Info
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('novalnet/invoice/info.phtml');
    }

    public function getInfo()
    {
        $info = $this->getData('info');
        if (!($info instanceof Mage_Payment_Model_Info)) {
            Mage::throwException($this->__('Can not retrieve payment info model object.'));
        }
        return $info;
    }

    /**
     * Retrieve payment method model
     *
     * @return Mage_Payment_Model_Method_Abstract
     */
    public function getMethod()
    {
        return $this->getInfo()->getMethodInstance();
    }
    public function getInfoData($field)
    {
        return $this->htmlEscape($this->getMethod()->getInfoInstance()->getData($field));
    }
    public function getPaymentMethod()
    {
        return $this->htmlEscape($this->getMethod()->getConfigData('title'));
    }
	public function getDuedate()
    {
        $payment_duration = $this->getMethod()->getConfigData('payment_duration');
        $due_date = '';
        $due_date_string = '';
        if($payment_duration)
        {
            $due_date = date("d.m.Y",mktime(0,0,0,date("m"),date("d")+$payment_duration,date("Y")));
            $due_date_string = '&due_date='.date("Y-m-d",mktime(0,0,0,date("m"),date("d")+$payment_duration,date("Y")));

			/*if ($payment->getNnElvCountry()=="DE") or $payment->getNnElvCountry()=="AT"))
			{
				$due_date = explode('-', $due_date);
				$due_date = $due_date[];
			}*/
        }
        
        /*if($due_date)
        {
            $order->info['comments'] .= '<BR><B>'.MODULE_PAYMENT_NOVALNET_INVOICE_TEXT_DURATION_LIMIT_INFO." $due_date ".MODULE_PAYMENT_NOVALNET_INVOICE_TEXT_DURATION_LIMIT_END_INFO.".</B><BR><BR>";
        }
        else
        {
            $order->info['comments'] = '<BR><B>'.MODULE_PAYMENT_NOVALNET_INVOICE_TEXT_TRANSFER_INFO.'</B><BR><BR>';
        }*/
          return$due_date;
    }
}
