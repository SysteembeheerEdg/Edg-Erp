<?php

namespace Edg\Erp\Helper;

class Tracktrace extends \Magento\Framework\App\Helper\AbstractHelper
{
    protected $shipment;
    protected $cachedShipments = [];

    public function __construct(\Magento\Framework\App\Helper\Context $context, \Magento\Sales\Model\Order\Shipment $shipment)
    {
        parent::__construct($context);

        $this->shipment = $shipment;
    }

    public function isPostNL($track)
    {
        $target = \Edg\Erp\Cron\API\OrderStatusImport::CARRIER_CODE;
        $target2 = \Edg\Erp\Cron\API\OrderStatusImport::TRACKING_TITLE;
        if ($track instanceof \Magento\Sales\Model\Order\Shipment\Track) {
            return $track->getTitle() == $target || ($track->getCarrierCode() == $target && $track->getTitle() == $target2);
        } elseif (is_array($track) && isset($track['title'])) {
            return $track['title'] == $target || $track['title'] == $target2;
        }
        return false;
    }

    public function getPostNLUrl($trackCode, $postCode)
    {
        return sprintf('https://mijnpakket.postnl.nl/Inbox/Search?B=%s&p=%s', $trackCode, $postCode);
    }

    public function getPostCodeByShipmentIncrement($increment)
    {
        if (!isset($this->cachedShipments[$increment])) {
            $newShipment = clone $this->shipment;

            $newShipment->loadByIncrementId($increment);

            $this->cachedShipments[$increment] = $newShipment;
        }

        $shipment = $this->cachedShipments[$increment];

        return $shipment->getShippingAddress()->getPostcode();

    }
}
