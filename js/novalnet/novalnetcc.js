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
 * http://www.novalnet.de 
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
        novalnetPaymentWrap();		
    }
    // For Creating Admin Order
    if (typeof order != 'undefined' && typeof order.submit != 'undefined') {
        novalnetAdminOrder();		
        novalnetbuildParams();
    }
});

function enableElements(elements) 
{
    for (var i=0; i<elements.length; i++) {
        elements[i].disabled = false;
    }
}

function novalnetPaymentWrap() 
{
    payment.save = payment.save.wrap(function(origSaveMethod){
        if ($('p_method_novalnetCc') && $('p_method_novalnetCc').checked) {
            if (checkout.loadWaiting!=false) return;
            var elements = $('fieldset_novalnetCc').select('input[type="hidden"]');
            enableElements(elements);
            //if (this.validate() && $('payment_form_novalnetCc').contentWindow.validate()) {
            checkout.setLoadWaiting('payment');
            if($('ifm_payment_form_novalnetCc')) {
                $('ifm_payment_form_novalnetCc').contentWindow.updateHiddenElements(elements);
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
        //}
        } else {
            origSaveMethod();
        }
    });
}

function novalnetAdminOrder() 
{
    order.submit = order.submit.wrap(function(origSaveMethod){
        if ($('p_method_novalnetCc') && $('p_method_novalnetCc').checked) {
            //if(editForm.validator.validate() && $('payment_form_novalnetCc').contentWindow.validate()){		
            var elements = $('fieldset_novalnetCc').select('input[type="hidden"]');
            enableElements(elements);
            if($('ifm_payment_form_novalnetCc')) {
                $('ifm_payment_form_novalnetCc').contentWindow.updateHiddenElements(elements);    
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
        //}
        } else {
            origSaveMethod();
        }
    });
}

function novalnetbuildParams() 
{
    order.prepareParams = order.prepareParams.wrap(function(origMethod, origParams){
        if ($('p_method_novalnetCc') && $('p_method_novalnetCc').checked) {
            var params = origMethod(origParams);
            params['novalnet_cc_owner'] = '';
            params['novalnet_cc_type'] = '';
            params['novalnet_cc_pan_hash'] = '';
            params['novalnet_cc_unique_id'] = '';
            params['novalnet_cc_exp_month'] = '';
            params['novalnet_cc_exp_year'] = '';
            params['novalnet_cc_cid'] = '';
            return params;
        } else {
            return origMethod(origParams);
        }
    });
}

function novalnet_cc_iframe(iframe)
{
    document.getElementById('loading').style.display = 'none';
    var frameObj =(iframe.contentWindow || iframe.contentDocument);
    if (frameObj.document) frameObj=frameObj.document;
    var card_type = frameObj.getElementById("novalnetCc_cc_type");
    card_type.onchange = function ()
    {
        document.getElementById('novalnet_cc_type').value = card_type.value;
        document.getElementById('novalnet_cc_type').disabled = false;
        novalnet_cc_process(iframe);
    }
    var card_owner = frameObj.getElementById("novalnetCc_cc_owner");
    card_owner.onkeyup = function ()
    {
        document.getElementById('novalnet_cc_owner').value = card_owner.value;
        document.getElementById('novalnet_cc_owner').disabled = false;
        novalnet_cc_process(iframe);
    }
    var card_exp_month = frameObj.getElementById("novalnetCc_expiration");
    card_exp_month.onchange = function ()
    {
        document.getElementById('novalnet_cc_exp_month').value = card_exp_month.value;
        document.getElementById('novalnet_cc_exp_month').disabled = false;
        novalnet_cc_process(iframe);
    }
    var card_exp_year = frameObj.getElementById("novalnetCc_expiration_yr");
    card_exp_year.onchange = function ()
    {
        document.getElementById('novalnet_cc_exp_year').value = card_exp_year.value;
        document.getElementById('novalnet_cc_exp_year').disabled = false;
        novalnet_cc_process(iframe);
    }
    var card_cid = frameObj.getElementById("novalnetCc_cc_cid");
    card_cid.onkeyup = function ()
    {
        document.getElementById('novalnet_cc_cid').value = card_cid.value;
        document.getElementById('novalnet_cc_cid').disabled = false;
        novalnet_cc_process(iframe);
    }	
}

function novalnet_cc_process(iframe)
{
    if(iframe) {
        var frameObj =(iframe.contentWindow || iframe.contentDocument);
        if (frameObj.document) frameObj=frameObj.document;
	
        var nncc_cardno_id = frameObj.getElementById("nncc_cardno_id");
        var nncc_unique_id = frameObj.getElementById("nncc_unique_id");
		
        if(nncc_cardno_id)
        {
            document.getElementById('novalnet_cc_pan_hash').value = nncc_cardno_id.value;
            document.getElementById('novalnet_cc_pan_hash').disabled = false;
        }
        if(nncc_unique_id)
        {
            document.getElementById('novalnet_cc_unique_id').value = nncc_unique_id.value;
            document.getElementById('novalnet_cc_unique_id').disabled = false;
        }
    }
}
