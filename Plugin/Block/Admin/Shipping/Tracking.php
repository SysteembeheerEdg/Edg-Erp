<?php

namespace Edg\Erp\Plugin\Block\Admin\Shipping;


class Tracking
{
    /**
     * @param \Magento\Shipping\Block\Adminhtml\Order\Tracking $subject
     * @param $result
     * @return array|mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetCarriers(
        \Magento\Shipping\Block\Adminhtml\Order\Tracking $subject,
        $result
    )
    {
        if (!is_array($result)) {
            return $result;
        }

        if (array_key_exists('PostNL', $result)) {
            return $result;
        }

        $result['PostNL'] = __('PostNL');
        return $result;
    }
}
