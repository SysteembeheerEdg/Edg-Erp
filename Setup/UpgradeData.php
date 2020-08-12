<?php
/**
 * UpgradeData
 *
 * @copyright Copyright © 2017 Bold Commerce BV. All rights reserved.
 * @author    dev@boldcommerce.nl
 */

namespace Bold\PIM\Setup;


use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

/**
 * @codeCoverageIgnore
 */
class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var \Magento\Sales\Setup\SalesSetupFactory
     */
    protected $salesSetup;

    /**
     * @var ModuleDataSetupInterface
     */
    protected $setup;

    public function __construct(
        \Magento\Sales\Setup\SalesSetupFactory $salesSetupFactory
    ) {
        $this->salesSetup = $salesSetupFactory;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $this->setup = $setup;

        if (version_compare($context->getVersion(), '0.0.2', '<')) {
            $this->installExportedAt();
        }

        $setup->endSetup();
    }

    /**
     * Upgrade script for version 0.0.2
     *
     * @return void
     */
    protected function installExportedAt()
    {
        $salesInstaller = $this->salesSetup->create(['resourceName' => 'sales_setup', 'setup' => $this->setup]);

        $salesInstaller->addAttribute(
            'order',
            'pim_exported_at',
            ['type' => \Magento\Framework\DB\Ddl\Table::TYPE_DATETIME, 'nullable' => true, 'grid' => true]
        );

        $salesInstaller->addAttribute(
            'order',
            'pim_is_exported',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                'nullable' => false,
                'grid' => true,
                'default' => '0'
            ]
        );
    }

}