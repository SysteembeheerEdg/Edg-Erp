<?php

namespace Edg\Erp\Cron\API;

use Edg\Erp\Helper\Data;
use Magento\Framework\Mail\TransportInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\TierPriceManagement;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogRule\Model\Rule\Job;
use Magento\Framework\App\Cache;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Logger\Monolog;
use Laminas\Mail\Message;
use Magento\Store\Model\StoreManager;

class ArticleInfoUpdate extends ArticleInfo
{

    /**
     * @param Data $helper
     * @param DirectoryList $directoryList
     * @param Monolog $monolog
     * @param ConfigInterface $config
     * @param Message $message
     * @param TransportInterface $transportInterface
     * @param StoreManager $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param StockRegistryInterface $stockRegistryInterface
     * @param TierPriceManagement $tierPriceManagement
     * @param Job $catalogRuleJob
     * @param Cache $cache
     * @param $settings
     * @throws FileSystemException
     */
    public function __construct(
        Data $helper,
        DirectoryList $directoryList,
        Monolog $monolog,
        ConfigInterface $config,
        Message $message,
        TransportInterface $transportInterface,
        StoreManager $storeManager,
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRegistryInterface,
        TierPriceManagement $tierPriceManagement,
        Job $catalogRuleJob,
        Cache $cache,
        array $settings = []
    ) {
        parent::__construct($helper, $directoryList, $monolog, $config, $message, $transportInterface, $storeManager, $productRepository, $stockRegistryInterface, $tierPriceManagement, $catalogRuleJob, $cache, $settings);
    }

    /**
     * @return array|mixed
     */
    protected function getSkuArray()
    {
        return $this->helper->getUpdateSkusArray();
    }
}
