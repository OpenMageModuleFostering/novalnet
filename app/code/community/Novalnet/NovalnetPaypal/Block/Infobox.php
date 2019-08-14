<?php
class Novalnet_novalnetpaypal_Block_Infobox extends Mage_Core_Block_Template
{
     protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('novalnetpaypal/infobox.phtml');
    }
}