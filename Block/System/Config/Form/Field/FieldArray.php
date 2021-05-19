<?php
/**
 * @author   : Daan van den Bergh
 * @url      : https://daan.dev
 * @package  : Dan0sz/ResourceHints
 * @copyright: (c) 2019 Daan van den Bergh
 */

namespace Edg\Erp\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class FieldArray extends AbstractFieldArray
{
    /**
     * @var array $_columns
     */
    protected $_columns = [];

    /**
     * @var bool $_addAfter
     */
    protected $_addAfter = true;

    /**
     * @var $_addButtonLabel
     */
    protected $_addButtonLabel;

    /**
     * @var $magentoTaxClassRenderer
     */
    private $magentoTaxClassRenderer;

    /**
     * FieldArray Constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_addButtonLabel = __('Add tax rate mapping');
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'pim_tax_rate',
            [
                'label' => __('PIM tax rate')
            ]
        );
        $this->addColumn(
            'magento_tax_class',
            [
                'label'    => __('Magento tax class'),
                'renderer' => $this->getMagentoTaxClasses()
            ]
        );
        $this->_addAfter       = false;
    }

    /**
     * @param \Magento\Framework\DataObject $row
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row)
    {
        $magentoTaxClassId = $row->getData('magento_tax_class');
        $options = [];

        if ($magentoTaxClassId) {
            $options['option_' . $this->getMagentoTaxClasses()->calcOptionHash($magentoTaxClassId)] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * @param string $columnName
     *
     * @return string
     * @throws \Exception
     */
    public function renderCellTemplate($columnName)
    {

        if ($columnName == 'pim_tax_rate') {
            $this->_columns[$columnName]['class'] = 'input-text required-entry';
            $this->_columns[$columnName]['style'] = 'width: 200px';
        }

        if ($columnName == "magento_tax_class") {
            $this->_columns[$columnName]['class'] = 'input-select required-entry';
        }

        return parent::renderCellTemplate($columnName);
    }

    /**
     * @return \Magento\Framework\View\Element\BlockInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getMagentoTaxClasses()
    {
        if (!$this->magentoTaxClassRenderer) {
            $this->magentoTaxClassRenderer = $this->getLayout()->createBlock(

                '\Edg\Erp\Block\Adminhtml\Form\Field\TaxClass',
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }

        return $this->magentoTaxClassRenderer;
    }
}
