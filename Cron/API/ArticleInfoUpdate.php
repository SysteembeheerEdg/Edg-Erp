<?php

namespace Edg\Erp\Cron\API;

use Edg\Erp\Helper\Data;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\TierPriceManagement;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogRule\Model\Rule\Job;
use Magento\Framework\App\Cache;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Logger\Monolog;
use Magento\Store\Model\StoreManager;

class ArticleInfoUpdate extends ArticleInfo
{
    /**
     * @var TransportBuilder
     */
    protected TransportBuilder $transportBuilder;

    /**
     * @param Data $helper
     * @param DirectoryList $directoryList
     * @param Monolog $monolog
     * @param ConfigInterface $config
     * @param TransportBuilder $transportBuilder
     * @param StoreManager $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param StockRegistryInterface $stockRegistryInterface
     * @param TierPriceManagement $tierPriceManagement
     * @param Job $catalogRuleJob
     * @param Cache $cache
     * @param array $settings
     * @throws FileSystemException
     */
    public function __construct(
        Data $helper,
        DirectoryList $directoryList,
        Monolog $monolog,
        ConfigInterface $config,
        TransportBuilder $transportBuilder,
        StoreManager $storeManager,
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRegistryInterface,
        TierPriceManagement $tierPriceManagement,
        Job $catalogRuleJob,
        Cache $cache,
        array $settings = []
    ) {
        parent::__construct($helper, $directoryList, $monolog, $config, $transportBuilder, $storeManager, $productRepository, $stockRegistryInterface, $tierPriceManagement, $catalogRuleJob, $cache, $settings);
    }

    /**
     * @return array|mixed
     */
    protected function getSkuArray()
    {
        return $this->helper->getUpdateSkusArray();
    }
}
