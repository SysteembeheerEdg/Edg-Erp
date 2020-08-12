<?php
/**
 * SoapVersion
 *
 * @copyright Copyright © 2017 Bold Commerce BV. All rights reserved.
 * @author    dev@boldcommerce.nl
 */

namespace Bold\PIM\Model\SourceModel\System;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Option\ArrayInterface;

class SoapVersion implements OptionSourceInterface, ArrayInterface
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
        return $this->getAllOptions();
    }

    public function getAllOptions()
    {
        if (!$this->options) {
            $this->options = [
                ["value" => SOAP_1_1, "label" => "SOAP 1.1"],
                ["value" => SOAP_1_2, "label" => "SOAP 1.2"],
            ];
        }

        return $this->options;
    }

    public function getOptionText($value)
    {
        $options = $this->getAllOptions();
        foreach ($options as $option) {
            if ($value === $option["value"]) {
                return $option["label"];
            }
        }
        return null;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $options = $this->getAllOptions();
        $opt = [];
        foreach ($options as $option) {
            $opt[$option["value"]] = $option["label"];
        }
        return $opt;
    }
}