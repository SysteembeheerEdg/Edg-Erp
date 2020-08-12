<?php

namespace Edg\Erp\Model\SourceModel\System;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Option\ArrayInterface;

class OrderStatus implements OptionSourceInterface, ArrayInterface
{
    /**
     * @var array $options
     */
    protected $options = [];

    /**
     * Get Options
     *
     * @return array
     */
    public function toOptionArray()
    {
        if (!$this->options) {
            $this->options = [
                ['value' => 'complete', 'label' => __('Complete')],
                ['value' => 'processing', 'label' => __('Processing')],
            ];
        }

        return $this->options;
    }
}
