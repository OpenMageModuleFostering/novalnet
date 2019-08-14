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
class Novalnet_Payment_NovalnetCcController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
        //Loading current layout
        $this->loadLayout();

        //Creating a new block
        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template', 'CcForm', array('template' => 'novalnet/payment/method/form/Ccform.phtml'));
        $this->getLayout()->getBlock('content')->append($block);

        // TO avoid Background and to load our form in empty page
        $this->getLayout()->getBlock('root')->setTemplate('novalnet/payment/method/form/blank.phtml');

        //Now showing it with rendering of layout
        $this->renderLayout();
    }

}
