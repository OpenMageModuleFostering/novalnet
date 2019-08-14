<?php

class Novalnet_Sofortueberweisung_Model_Mysql4_Sofortueberweisung_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('sofortueberweisung/sofortueberweisung');
    }
}