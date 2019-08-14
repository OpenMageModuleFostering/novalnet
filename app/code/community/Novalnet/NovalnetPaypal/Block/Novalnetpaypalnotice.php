<?php
class NovalnetPaypal_NovalnetPaypal_Block_NovalnetPaypalnotice extends Mage_Core_Block_Template
{
     protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('novalnetpaypal/notice.phtml');
    }
}