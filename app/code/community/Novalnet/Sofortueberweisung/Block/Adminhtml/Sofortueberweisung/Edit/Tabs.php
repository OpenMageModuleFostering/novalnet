<?php
class Novalnet_Sofortueberweisung_Block_Adminhtml_Sofortueberweisung_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{

  public function __construct()
  {
      parent::__construct();
      $this->setId('sofortueberweisung_tabs');
      $this->setDestElementId('edit_form');
      $this->setTitle(Mage::helper('sofortueberweisung')->__('Item Information'));
  }

  protected function _beforeToHtml()
  {
      $this->addTab('form_section', array(
          'label'     => Mage::helper('sofortueberweisung')->__('Item Information'),
          'title'     => Mage::helper('sofortueberweisung')->__('Item Information'),
          'content'   => $this->getLayout()->createBlock('sofortueberweisung/adminhtml_sofortueberweisung_edit_tab_form')->toHtml(),
      ));
     
      return parent::_beforeToHtml();
  }
}