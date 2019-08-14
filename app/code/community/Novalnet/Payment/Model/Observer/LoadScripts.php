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
 * @category  Novalnet
 * @package   Novalnet_Payment
 * @copyright Copyright (c) Novalnet AG. (https://www.novalnet.de)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Novalnet_Payment_Model_Observer_LoadScripts
{
    /**
     * Name library directory.
     */
    const NAME_DIR_JS = 'novalnet/';

    /**
     * Name library directory.
     */
    const NAME_DIR_CSS = 'css/novalnet/';

    /**
     * Novalnet script files
     *
     * @return array
     */
    protected $_files = array(
        'jquery-1.11.3.min.js',
        'cc.js',
        'sepa.js'
    );

    /**
     * Get script file path
     *
     * @param  string $file
     * @param  string $type
     * @return string
     */
    public function getScriptPath($file, $type = 'Js')
    {
        return $type == 'Js' ? self::NAME_DIR_JS . $file : self::NAME_DIR_CSS . $file;
    }

    /**
     * Load novalnet script files while preparing layout
     *
     * @param  varien_object $observer
     * @return Novalnet_Payment_Model_Observer
     */
    public function loadScriptFiles(Varien_Event_Observer $observer)
    {
        if (!Mage::app()->getStore()->isAdmin()) {
            // Novalnet payment helper
            $helper = Mage::helper('novalnet_payment');

            // Set affiliate id if exist
            $affiliateId = Mage::app()->getRequest()->getParam('nn_aff_id');
            if ($affiliateId) {
                $helper->getCoreSession()->setAffiliateId(trim($affiliateId));
            }

            /* $block Mage_Page_Block_Html_Head */
            $baseUrl = $helper->getBaseUrl(); // Get shop base url
            $currentUrl = Mage::helper('core/url')->getCurrentUrl(); // Get shop current url
            $block = $observer->getEvent()->getBlock(); // Get block

            // Includes necessary Novalnet script files
            if ("head" == $block->getNameInLayout() && $currentUrl != $baseUrl) {
                $block->addCss($this->getScriptPath('novalnet.css', 'Css'));

                foreach ($this->_files as $file) {
                    $block->addJs($this->getScriptPath($file));
                }
            }
        }

        return $this;
    }

}
