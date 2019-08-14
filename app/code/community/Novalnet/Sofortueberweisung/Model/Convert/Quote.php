<?php
class Novalnet_Sofortueberweisung_Model_Convert_Quote extends Mage_Sales_Model_Convert_Quote
{

    /**
     * Convert quote payment to order payment
     *
     * @param   Mage_Sales_Model_Quote_Payment $payment
     * @return  Mage_Sales_Model_Quote_Payment
     */
    public function paymentToOrderPayment(Mage_Sales_Model_Quote_Payment $payment)
    {
        $orderPayment = parent::paymentToOrderPayment($payment);
        $orderPayment->setSuAccountNumber($payment->getSuAccountNumber())
						->setSuBankCode($payment->getSuBankCode())
						->setSuNlBankCode($payment->getSuNlBankCode())
						->setSuPaycode($payment->getSuPaycode())
						->setSuSecurity($payment->getSuSecurity())
						->setSuIban($payment->getSuIban())
						->setSuBic($payment->getSuBic())
						->setSuHolder($payment->getSuHolder());    
        
        return $orderPayment;
    }

}
