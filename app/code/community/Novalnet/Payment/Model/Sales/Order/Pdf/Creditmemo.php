<?php
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
 * Part of the payment module of Novalnet AG
 * https://www.novalnet.de
 * If you have found this script useful a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category   Novalnet
 * @package    Novalnet_Payment
 * @copyright  Copyright (c) Novalnet AG. (https://www.novalnet.de)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Model_Sales_Order_Pdf_Creditmemo extends Mage_Sales_Model_Order_Pdf_Creditmemo
{
    /**
     * Return PDF document
     *
     * @param  array $creditmemos
     * @return Zend_Pdf
     */
    public function getPdf($creditmemos = array())
    {
        $this->_beforeGetPdf();
        $this->_initRenderer('creditmemo');

        $pdf = new Zend_Pdf();
        $this->_setPdf($pdf);
        $style = new Zend_Pdf_Style();
        $this->_setFontBold($style, 10);

        foreach ($creditmemos as $creditmemo) {
            if ($creditmemo->getStoreId()) {
                Mage::app()->getLocale()->emulate($creditmemo->getStoreId());
                Mage::app()->setCurrentStore($creditmemo->getStoreId());
            }
            $page  = $this->newPage();
            $order = $creditmemo->getOrder();
            /* Add image */
            $this->insertLogo($page, $creditmemo->getStore());
            /* Add address */
            $this->insertAddress($page, $creditmemo->getStore());
            /* Add head */
            $this->insertOrder(
                $page,
                $order,
                Mage::getStoreConfigFlag(self::XML_PATH_SALES_PDF_CREDITMEMO_PUT_ORDER_ID, $order->getStoreId())
            );
            /* Add document text and number */
            $this->insertDocumentNumber(
                $page,
                Mage::helper('sales')->__('Credit Memo # ') . $creditmemo->getIncrementId()
            );
            /* Add table head */
            $this->_drawHeader($page);
            /* Add body */
            foreach ($creditmemo->getAllItems() as $item){
                if ($item->getOrderItem()->getParentItem()) {
                    continue;
                }
                /* Draw item */
                $this->_drawItem($item, $page, $order);
                $page = end($pdf->pages);
            }
            /* Add totals */
            $this->insertTotals($page, $creditmemo);
            $helper = Mage::helper('novalnet_payment');
             $amountchangedvalue = $helper->getAmountCollection($order->getId(), 1, NULL);
            if ($amountchangedvalue) {
                $lineBlock = $this->amountRefundUpdateDetails($order,$amountchangedvalue,$creditmemo);
                $this->y -= 1;
                $page = $this->drawLineBlocks($page, array($lineBlock));
            }
        }
        $this->_afterGetPdf();
        if ($creditmemo->getStoreId()) {
            Mage::app()->getLocale()->revert();
        }
        return $pdf;
    }

     /**
     * amount refund details append in pdf
     *
     * @param  varien_object $order
     * @param  int $amountchangedvalue
     * @param  varien_object $creditmemo
     * @return Zend_Pdf
     */
    private function amountRefundUpdateDetails($order,$amountchangedvalue,$creditmemo)
    {
        $currency = $order->getOrderCurrency();
        $currencyCode = $currency->getCurrencyCode();
        $currencySymbol = Mage::app()->getLocale()->currency($currencyCode)->getSymbol();
        $lineBlock['lines'][] = array(
                    array(
                        'text'      => Mage::helper('sales')->__('Novalnet refunded Amount').':',
                        'feed'      => 475,
                        'align'     => 'right',
                        'font_size' => 10,
                        'font'      => 'bold'
                    ),
                    array(
                        'text'      => $currencySymbol.number_format($creditmemo->getGrandTotal(),2),
                        'feed'      => 565,
                        'align'     => 'right',
                        'font_size' => 10,
                        'font'      => 'bold'
                    ),
                );
        $lineBlock['lines'][] = array(
                    array(
                        'text'      => Mage::helper('sales')->__('Novalnet Transaction Amount').':',
                        'feed'      => 475,
                        'align'     => 'right',
                        'font_size' => 10,
                        'font'      => 'bold'
                    ),
                    array(
                        'text'      => $currencySymbol.number_format($amountchangedvalue,2),
                        'feed'      => 565,
                        'align'     => 'right',
                        'font_size' => 10,
                        'font'      => 'bold'
                    ),
                );
        return $lineBlock;
    }

}
