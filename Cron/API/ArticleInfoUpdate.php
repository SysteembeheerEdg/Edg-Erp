<?php

namespace Edg\Erp\Cron\API;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Mail\Message;

class ArticleInfoUpdate extends ArticleInfo
{

    public function __construct(
        \Edg\Erp\Helper\Data $helper,
        DirectoryList $directoryList,
        ConfigInterface $config,
        Message $message,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistryInterface,
        \Magento\Catalog\Model\Product\TierPriceManagement $tierPriceManagement,
        \Magento\CatalogRule\Model\Rule\Job $catalogRuleJob,
        \Magento\Framework\App\Cache $cache,
        $settings = []
    ) {
        parent::__construct($helper, $directoryList, $config, $message, $storeManager, $productRepository,$stockRegistryInterface, $tierPriceManagement, $catalogRuleJob, $cache,$settings);
    }

    /**
     * @return array|mixed
     */
    protected function getSkuArray()
    {
        return $this->helper->getUpdateSkusArray();
    }
}
