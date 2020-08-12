<?php
/**
 * Bold OrderExIm Model Orderstatus class
 *
 * @category    Bold
 * @package     Bold OrderExIm
 * @author      Dyan de Rochemont <dyan@boldcommerce.nl>
 * @author      Realvine BV, EcomDev BV
 * @copyright    2012-2013 Bold Commerce BV, Amsterdam
 * @link        http://www.boldcommerce.nl/
 */
namespace Bold\PIM\Model\SourceModel\System;

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
