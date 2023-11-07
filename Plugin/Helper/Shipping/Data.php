<?php

namespace Edg\Erp\Plugin\Helper\Shipping;

use Magento\Framework\Exception\LocalizedException;

class Data
{
    protected $helper;

    public function __construct(
        \Edg\Erp\Helper\Tracktrace $helper
    )
    {
        $this->helper = $helper;
    }

    /**
     * @param \Magento\Shipping\Helper\Data $subject
     * @param \Closure $proceed
     * @param $model
     * @return mixed|string
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundGetTrackingPopupUrlBySalesModel(
        \Magento\Shipping\Helper\Data $subject,
        \Closure $proceed,
        $model
    ) {
        if ($model instanceof \Magento\Sales\Model\Order\Shipment\Track && $this->helper->isPostNL($model)) {
            $trackCode = $model->getNumber();
            $postCode = $model->getShipment()->getShippingAddress()->getPostcode();
            return $this->helper->getPostNLUrl($trackCode, $postCode);
        }

        return $proceed($model);
    }
}
