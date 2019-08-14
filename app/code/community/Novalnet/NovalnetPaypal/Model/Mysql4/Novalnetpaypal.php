<?php

class Novalnet_NovalnetPaypal_Model_Mysql4_NovalnetPaypal extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {    
        // Note that the novalnetpaypal_id refers to the key field in your database table.
        $this->_init('novalnetpaypal/novalnetpaypal', 'novalnetpaypal_id');
    }
}