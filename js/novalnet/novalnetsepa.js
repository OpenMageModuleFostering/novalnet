/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * Part of the Paymentmodul of Novalnet AG
 * https://www.novalnet.de
 * If you have found this script usefull a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   js
 * @package    Mage
 * @copyright  Copyright (c) Novalnet AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

Event.observe(window, 'load', function(){
      // For Frontend Payment
      if (typeof payment != 'undefined' && typeof payment.save != 'undefined') {
         novalnetSepaPaymentWrap();
      }
      // For Creating Admin Order
      if (typeof order != 'undefined' && typeof order.submit != 'undefined') {
         novalnetSepaAdminOrder();
         novalnetSepabuildParams();
      }
});

function enableElements(elements)
{
   for (var i=0; i<elements.length; i++) {
      elements[i].disabled = false;
   }
}

function novalnetSepaPaymentWrap()
{
   payment.save = payment.save.wrap(function(origSaveMethod){
      if ($('p_method_novalnetSepa') && $('p_method_novalnetSepa').checked) {
         if (checkout.loadWaiting!=false) return;
         var elements = $('fieldset_novalnetSepa').select('input[type="hidden"]');
         enableElements(elements);
            checkout.setLoadWaiting('payment');
            if($('ifm_payment_form_novalnetSepa')) {
				$('ifm_payment_form_novalnetSepa').contentWindow.updateHiddenElements(elements);
			}
            var request = new Ajax.Request(
            payment.saveUrl,
            {
               method:'post',
               onComplete: payment.onComplete,
               onSuccess: payment.onSave,
               onFailure: checkout.ajaxFailure.bind(checkout),
               parameters: Form.serialize(payment.form)
            }
            );
      } else {
         origSaveMethod();
      }
   });
}

function novalnetSepaAdminOrder()
{
   order.submit = order.submit.wrap(function(origSaveMethod){
      if ($('p_method_novalnetSepa') && $('p_method_novalnetSepa').checked) {
            var elements = $('fieldset_novalnetSepa').select('input[type="hidden"]');
            enableElements(elements);
            if($('ifm_payment_form_novalnetSepa')) {
				$('ifm_payment_form_novalnetSepa').contentWindow.updateHiddenElements(elements);
			}
            if(order.orderItemChanged){
               if(confirm('You have item changes')){
                  editForm.submit();
               }
               else{
                  order.itemsUpdate();
               }
            }
            else{
               editForm.submit();
            }
      } else {
         origSaveMethod();
      }
   });
}

function novalnetSepabuildParams()
{
   order.prepareParams = order.prepareParams.wrap(function(origMethod, origParams){
      if ($('p_method_novalnetSepa') && $('p_method_novalnetSepa').checked) {
         var params = origMethod(origParams);
         params['novalnet_sepa_owner'] = '';
         params['novalnet_sepa_pan_hash'] = '';
         params['novalnet_sepa_unique_id'] = '';
         params['novalnet_sepa_iban_confirmed'] = '';
         return params;
      } else {
         return origMethod(origParams);
      }
   });
}
