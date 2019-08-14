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
 * @package    Novalnet_Payment
 * @copyright  Novalnet AG
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

function novalnet_cc_iframe(iframe) {
    document.getElementById('nncc_loading').style.display = 'none';
    var frameObj = (iframe.contentWindow || iframe.contentDocument);
    if (frameObj.document)
        frameObj = frameObj.document;

    var card_type = frameObj.getElementById("novalnetCc_cc_type");
    var card_owner = frameObj.getElementById("novalnetCc_cc_owner");
    var card_exp_month = frameObj.getElementById("novalnetCc_expiration");
    var card_exp_year = frameObj.getElementById("novalnetCc_expiration_yr");
    var card_cid = frameObj.getElementById("novalnetCc_cc_cid");
    var card_no = frameObj.getElementById("novalnetCc_cc_number");
    var nncc_unique_id = frameObj.getElementById("nncc_unique_id");
    var nncc_hash_id = frameObj.getElementById("nncc_cardno_id");

    card_type.onchange = function() {
        setFormFieldsValue(card_type, card_owner, card_exp_month, card_exp_year, card_cid, card_no, nncc_unique_id, nncc_hash_id);
    }
    card_owner.onkeyup = function() {
        setFormFieldsValue(card_type, card_owner, card_exp_month, card_exp_year, card_cid, card_no, nncc_unique_id, nncc_hash_id);
    }
    card_no.onblur = function() {
        setFormFieldsValue(card_type, card_owner, card_exp_month, card_exp_year, card_cid, card_no, nncc_unique_id, nncc_hash_id);
    }
    card_exp_month.onchange = function() {
        setFormFieldsValue(card_type, card_owner, card_exp_month, card_exp_year, card_cid, card_no, nncc_unique_id, nncc_hash_id);
    }
    card_exp_year.onchange = function() {
        setFormFieldsValue(card_type, card_owner, card_exp_month, card_exp_year, card_cid, card_no, nncc_unique_id, nncc_hash_id);
    }
    card_cid.onkeyup = function() {
        setFormFieldsValue(card_type, card_owner, card_exp_month, card_exp_year, card_cid, card_no, nncc_unique_id, nncc_hash_id);
    }
}

function setFormFieldsValue(card_type, card_owner, card_exp_month, card_exp_year, card_cid, card_no, nncc_unique_id, nncc_hash_id) {
    document.getElementById('novalnet_cc_type').value = card_type.value;
    document.getElementById('novalnet_cc_type').disabled = false;

    document.getElementById('novalnet_cc_owner').value = card_owner.value;
    document.getElementById('novalnet_cc_owner').disabled = false;

    document.getElementById('novalnet_cc_exp_month').value = card_exp_month.value;
    document.getElementById('novalnet_cc_exp_month').disabled = false;

    document.getElementById('novalnet_cc_exp_year').value = card_exp_year.value;
    document.getElementById('novalnet_cc_exp_year').disabled = false;

    document.getElementById('novalnet_cc_cid').value = card_cid.value;
    document.getElementById('novalnet_cc_cid').disabled = false;

    if (card_type.value != '' && card_owner.value != '' && card_exp_month.value != '' && card_exp_year.value != ''
        && card_cid.value != '' && card_no.value != '') {
        document.getElementById('novalnet_cc_pan_hash').value = nncc_hash_id.value;
        document.getElementById('novalnet_cc_pan_hash').disabled = false;
        document.getElementById('novalnet_cc_unique_id').value = nncc_unique_id.value;
        document.getElementById('novalnet_cc_unique_id').disabled = false;
    } else {
        document.getElementById('novalnet_cc_pan_hash').value = '';
        document.getElementById('novalnet_cc_pan_hash').disabled = false;
        document.getElementById('novalnet_cc_unique_id').value = '';
        document.getElementById('novalnet_cc_unique_id').disabled = false;
    }

    var iframe = document.getElementById("ifm_payment_form_novalnetCc");
    var ccIframe = (iframe.contentWindow || iframe.contentDocument);
    if (ccIframe.document)
        ccIframe = ccIframe.document;

    if (typeof nncc_hash_id != 'undefined' && nncc_hash_id.value != '') {

        var cc_type = 0;
        var cc_owner = 0;
        var cc_no = 0;
        var cc_hash = 0;
        var cc_month = 0;
        var cc_year = 0;
        var cc_cid = 0;

        if (card_type.value != '')
            cc_type = 1;
        if (card_owner.value != '')
            cc_owner = 1;
        if (card_no.value != '')
            cc_no = 1;
        if (card_exp_month.value != '')
            cc_month = 1;
        if (card_exp_year.value != '')
            cc_year = 1;
        if (card_cid.value != '')
            cc_cid = 1;

        document.getElementById('novalnet_cc_field_validator').value = cc_type + ',' + cc_owner + ',' + cc_no + ',' + cc_month + ',' + cc_year + ',' + cc_cid;
        document.getElementById('novalnet_cc_field_validator').disabled = false;
    }
}
