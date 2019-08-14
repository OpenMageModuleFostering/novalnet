<?php
class Novalnet_Sofortueberweisung_Block_Sofortueberweisung extends Mage_Core_Block_Abstract
{
  protected function _toHtml()
  {
    $payment = $this->getOrder()->getPayment()->getMethodInstance();

    if($payment->getConfigData('password')) {
      $form = new Varien_Data_Form();
      $form->setAction($payment->getUrl())
          ->setId('sofortueberweisung')
          ->setName('sofortueberweisung')
          ->setMethod('POST')
          ->setUseContainer(true);

        foreach ($payment->getFormFields() as $field=>$value) {
            $form->addField($field, 'hidden', array('name'=>$field, 'value'=>$value));
        }
		// IE & Firefox will not submit form if the form is full of hidden fileds.
		$form->addField( 'continue', 'submit', array( 'name'=>'continue', 'value'=>$this->__('You will be redirected to Novalnet AG Instant Bank Transfer in a few seconds.') ) );
        $html = '<html><body>';
    //    $html.= $this->__('You will be redirected to Novalnet AG Instant Bank Transfer in a few seconds.');
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("sofortueberweisung").submit();</script>';
        $html.= '</body></html>';

        return $html;
  }else{
    $html = $this->__('Theres no password defined');
    return $html;
  }
  }
}