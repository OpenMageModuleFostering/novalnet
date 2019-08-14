<?php

class Novalnet_Sofortueberweisung_Model_Mysql4_Sofortueberweisung extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {    
        // Note that the sofortueberweisung_id refers to the key field in your database table.
        $this->_init('sofortueberweisung/sofortueberweisung', 'sofortueberweisung_id');
    }
}