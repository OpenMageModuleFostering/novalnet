<?php
class Novalnet_Sofortueberweisung_Block_Sofortueberweisungerrornotice extends Mage_Core_Block_Template
{
     protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('sofortueberweisung/errornotice.phtml');
    }
}