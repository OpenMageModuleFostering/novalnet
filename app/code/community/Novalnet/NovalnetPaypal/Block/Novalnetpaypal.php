<?php
class Novalnet_NovalnetPaypal_Block_NovalnetPaypal extends Mage_Core_Block_Abstract
{
  protected function _toHtml()
  {
    $payment = $this->getOrder()->getPayment()->getMethodInstance();

    if($payment->getConfigData('password')) {
      $form = new Varien_Data_Form();
      $form->setAction($payment->getUrl())
          ->setId('novalnetpaypal')
          ->setName('novalnetpaypal')
          ->setMethod('POST')
          ->setUseContainer(true);	
        foreach ($payment->getFormFields() as $field=>$value) {
            $form->addField($field, 'hidden', array('name'=>$field, 'value'=>$value));
        }
		// IE & Firefox will not submit form if the form is full of hidden fileds.
		$form->addField( 'continue', 'submit', array( 'name'=>'continue', 'value'=>$this->__('You will be redirected to Novalnet AG website when you place an order.') ) );
        $html = '<html><body>';
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("novalnetpaypal").submit();</script>';
        $html.= '</body></html>';

        return $html;
  }else{
    $html = $this->__('Parameter missing');
    return $html;
  }
  }
}