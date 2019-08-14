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
 * Part of the Paymentmodule of Novalnet AG
 * https://www.novalnet.de
 * If you have found this script usefull a small
 * recommendation as well as a comment on merchant form
 * would be greatly appreciated.
 *
 * @category  Novalnet
 * @package   Novalnet_Payment
 * @copyright Novalnet AG
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Novalnet_Payment_Block_Adminhtml_Notifications extends Mage_Adminhtml_Block_Notification_Window
{
    /**
     * Get header text
     *
     * @param  none
     * @return string
     */
    public function getHeaderText()
    {
        return 'NOVALNET';
    }

    /**
     * Show notifications
     *
     * @param  none
     * @return string
     */
    public function canShow()
    {
        $message = $this->getNoticeMessageText();
        $session = Mage::getSingleton('core/session');
        $result = (!$session->getNnVersionNotice() && !empty($message)) ? $message: '';
        $session->setNnVersionNotice(true);
        return $result;
    }

    /**
     * Get latest Novalnet version infomation
     *
     * @param  none
     * @return string
     */
    public function getNoticeMessageText()
    {
        $message = '';
        if (Mage::getStoreConfig('novalnet_global/novalnet/latest_updates_notify')) {
            // Get Novalnet version information from magento connect
            $package = 'Novalnet';
            $channelUri = 'connect20.magentocommerce.com/community';
            $protocol = $this->getProtocol();

            // Rest API
            $rest = new Mage_Connect_Rest();
            $rest->setChannel($channelUri);
            $rest->__construct($protocol);
            $releases = $rest->getReleases($package);

            // Get latest Novalnet version (Magento Connect)
            $novalnetVersion = $releases[0]['v'];
            $stability = $releases[0]['s'];

            // Get installed Novalnet version information
            $installedVersion = (string) Mage::getConfig()->getNode('modules/Novalnet_Payment/version');

            // Notification message
            if (version_compare($installedVersion, $novalnetVersion, '<')) {
                $message = $this->__(
                    'Novalnet version %s (%s) is now available for download and upgrade.', $novalnetVersion, $stability
                );
            }
        }
        return $message;
    }

    /**
     * Get notice message redirect url
     *
     * @param  none
     * @return string
     */
    public function getNoticeMessageUrl()
    {
        $protocol = $this->getProtocol();
        return $this->escapeUrl($protocol.'://www.magentocommerce.com/magento-connect/novalnet-payment-extension.html');
    }

    /**
     * Get protocol
     *
     * @param  none
     * @return string
     */
    public function getProtocol()
    {
        return Mage::app()->getStore()->isCurrentlySecure() ? 'https' : 'http';
    }

}
