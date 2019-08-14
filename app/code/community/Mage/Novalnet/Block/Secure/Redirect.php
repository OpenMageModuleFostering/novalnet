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


class Mage_Novalnet_Block_Secure_Redirect extends Mage_Core_Block_Abstract
{
    protected function _toHtml()
    {
        $secure = $this->getOrder()->getPayment()->getMethodInstance();
		$formName = $formID = $secure->getCode();
        $form = new Varien_Data_Form();
        $form->setAction($secure->getCgiUrl())
            ->setId($formID)
            ->setName($formName)
            ->setMethod('POST')
            ->setUseContainer(true);
        foreach ($secure->getFormData()->toArray() as $field=>$value) {
            $form->addField($field, 'hidden', array('name'=>$field, 'value'=>$value));
        }
		//$form->addField('enter', 'Submit', array('name'=>'enter', 'value'=>'enter'));#for debug
        $html = '<html><body>';
        $html.= $this->__('You will be redirected to Novalnet AG 3D-Secure in a few seconds.');
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("' . $formID . '").submit();</script>';
        $html.= '</body></html>';
        return $html;
    }
}