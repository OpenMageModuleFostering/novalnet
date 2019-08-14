<?php
class Novalnet_Sofortueberweisung_Block_Adminhtml_Sofortueberweisung extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
    $this->_controller = 'adminhtml_sofortueberweisung';
    $this->_blockGroup = 'sofortueberweisung';
    $this->_headerText = Mage::helper('sofortueberweisung')->__('Item Manager');
    $this->_addButtonLabel = Mage::helper('sofortueberweisung')->__('Add Item');
    parent::__construct();
  }
}