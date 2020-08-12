<?php

declare(strict_types = 1);

/**
 * PimSync
 *
 * @copyright Copyright © 2018 Bold Commerce BV. All rights reserved.
 * @author    dev@boldcommerce.nl
 */

namespace Bold\PIM\Block\Adminhtml\Product\Edit\Button;

use Magento\Catalog\Block\Adminhtml\Product\Edit\Button\Generic;

class PimSync extends Generic
{
    /**
     * @return array
     */
    public function getButtonData()
    {
        return [
            'label' => __('Sync with EDG Progress PIM'),
            'class' => 'action-secondary',
            'on_click' => 'pimSyncConfirm(\'' . $this->getSyncUrl() . '\')',
            'sort_order' => 50
        ];
    }

    /**
     * @param array $args
     * @return string
     */
    public function getSyncUrl(array $args = [])
    {
        $params = array_merge($this->getDefaultUrlParams(), $args);
        return $this->getUrl('bold_pim/catalog_product/sync', $params);
    }

    /**
     * @return array
     */
    protected function getDefaultUrlParams()
    {
        return ['_current' => true, '_query' => ['isAjax' => null]];
    }
}
