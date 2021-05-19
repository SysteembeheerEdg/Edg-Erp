<?php
namespace Edg\Erp\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;
use Magento\Tax\Model\TaxClass\Source\Product as ProductTaxClassSource;

/**
 * Class TaxClass
 * @package Edg\Erp\Block\Adminhtml\Form\Field
 */
class TaxClass extends Select
{
    /**
     * @var ProductTaxClassSource
     */
    protected $taxClassResource;

    /**
     * TaxClass constructor.
     * @param Context $context
     * @param ProductTaxClassSource $taxClassResource
     */
    public function __construct(
        Context $context,
        ProductTaxClassSource $taxClassResource
    ) {
        parent::__construct($context);
        $this->taxClassResource = $taxClassResource;
    }

    /**
     * @return string
     */
    public function _toHtml()
    {
        if (!$this->getOptions()) {
            foreach ($this->taxClassResource->getAllOptions() as $taxClass) {
                $this->addOption($taxClass['value'], $taxClass['label']);
            }
        }

        $this->setClass('input-select required-entry');
        $this->setExtraParams('style="width: 125px;"');

        return parent::_toHtml();
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }
}
