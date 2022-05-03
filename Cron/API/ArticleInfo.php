<?php

namespace Edg\Erp\Cron\API;

use Edg\Erp\Helper\Data;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\TierPriceManagement;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogRule\Model\Rule\Job;
use Magento\Framework\App\Cache;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Logger\Monolog;
use Magento\Store\Model\StoreManager;

class ArticleInfo extends AbstractCron
{
    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;

    /**
     * @var StockRegistryInterface
     */
    protected StockRegistryInterface $stockRegistry;

    /**
     * @var TierPriceManagement
     */
    protected TierPriceManagement $tierpriceManagement;

    /**
     * @var TransportBuilder
     */
    protected TransportBuilder $transportBuilder;

    /**
     * @var Job
     */
    protected Job $catalogRuleJob;

    /**
     * @var Cache
     */
    protected Cache $cache;

    /**
     * @var string[]
     */
    protected array $fieldsToSkip = [
        'sku'
    ];
    /**
     * Array of skus to call on
     */
    protected array $_skuList = [];

    /**
     * @var array
     */
    protected array $apiMessages = [];

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
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistryInterface;
        $this->tierpriceManagement = $tierPriceManagement;
        $this->catalogRuleJob = $catalogRuleJob;
        $this->cache = $cache;
        parent::__construct($helper, $directoryList, $monolog, $config, $transportBuilder, $storeManager, $settings);
    }

    public function execute()
    {
        if ($this->helper->isArticleImportEnabled()) {
            $this->moduleLog(__METHOD__ . '(); - Article Import setting enabled, starting.', true);
            $response = $this->processProductUpdates();
            $this->moduleLog(__METHOD__ . '(); - Article Import setting enabled, applying price rules.', true);

            try {
                $this->catalogRuleJob->applyAll();
                $this->cache->remove('catalog_rules_dirty');
                $this->moduleLog(__METHOD__ . '(); - ' . __('The rules have been applied.'), true);
            } catch (\Exception $exception) {
                $this->moduleLog(__METHOD__ . '(); - ' . __('Unable to apply rules.'), true);
                $this->moduleLog(__METHOD__ . '(); - ' . $exception->getMessage(), true);
            }
        } else {
            $this->moduleLog(__METHOD__ . '(); - Article Import setting disabled, done.', true);
            $response = [['type'=>'error', 'message'=> 'Article Import setting disabled.']];
        }

        $this->apiMessages = $response;
    }

    protected function processProductUpdates(): array
    {
        $messages = [];

        $this->moduleLog(__METHOD__ . '() - Begin');

        $client = $this->helper->getSoapClient();

        // Uncomment and put skus in the array for quick testing purposes
//        $this->_skuList = ['1310-191797'];

        // If no specific list is set, use all
        if (sizeof($this->_skuList) == 0) {
            $this->_skuList = $this->getSkuArray();
        }

        if ($this->_skuList) {
            $results = $client->pullArticleInfo($this->_skuList);

            foreach ($results as $result) {
                $articles = $result->getArticles();
                foreach ($articles as $article) {
                    if (!$article->isArticleExist()) {
                        $msg = 'No match for product with sku "' . $article->getArticleNumber() . '", product does not exist in PIM.';
                        $this->moduleLog(__METHOD__ . '() ' . $msg, true);
                        $messages[] = ['type' => 'error', 'message' => $msg];
                        continue;
                    }

                    $this->processProduct($article);
                    $messages[] = [
                        'type' => 'success',
                        'message' => 'Successfully synchronized sku "' . $article->getArticleNumber() . '".'
                    ];
                }
            }
        }
        return $messages;
    }

    protected function processProduct(\Edg\ErpService\DataModel\ArticleInfo $article)
    {
        $mapping = $this->helper->getPimFieldMapping();

        $productdata = [];

        foreach ($mapping as $mapPim => $mapMage) {
            $productdata[$mapMage] = (string)$article->getData($mapPim);
        }

        $prefix = $this->helper->getSkuPrefix();

        // Truncate prefix
        $sku = substr($productdata['sku'], strlen($prefix));

        try {
            $product = $this->productRepository->get($sku, true);

            // Set current store and store id on product to 0 to prevent a product save on store level
            $this->storeManager->setCurrentStore(0);
            $product->setStoreId(0);

            if ($this->helper->getArticleInfoSetting('sync_product_status') == '1') {
                $this->updateProductStatus($product, $article);
            }

            if ($this->helper->getArticleInfoSetting('sync_stock') == '1') {
                $this->updateProductStockSettings($product, $article);
            }

            if ($this->helper->getArticleInfoSetting('sync_pricetiers') == '1') {
                $this->updateProductPriceTiers($product, $article);
            }

            if ($this->helper->getArticleInfoSetting('sync_tax_classes') == '1') {
                $this->updateProductTaxClass($product, $article);
            }

            foreach ($productdata as $mapMage => $data) {
                if (in_array($mapMage, $this->fieldsToSkip)) {
                    continue;
                }

                $setting = 'sync_field_' . $mapMage;

                if ($this->helper->getArticleInfoSetting($setting)) {
                    $this->moduleLog(__METHOD__ . sprintf(
                        ' Product #%s: parameter "%s" from "%s" to "%s".',
                        $sku,
                        $mapMage,
                        $product->getData($mapMage),
                        $data
                    ), true);
                    $product->setData($mapMage, $data);
                } else {
                    $this->moduleLog(__METHOD__ . sprintf(
                        ' Product #%s: skipping parameter "%s", based on disabled config "%s"',
                        $sku,
                        $mapMage,
                        $setting
                    ), true);
                }
            }

            $product->unsetData('media_gallery');

            // uncomment to save product on global scope
            $product->setStoreId(0);
            $this->storeManager->setCurrentStore(0);

            try {
                $this->productRepository->save($product);
            } catch (\Exception $e) {
                $this->moduleLog(__METHOD__ . '() - WARNING: Errors attemping to update product with sku "' . $productdata['sku'] . '": ' . $e->getMessage());
            }
        } catch (NoSuchEntityException $e) {
            $this->moduleLog(__METHOD__ . '() - WARNING: Product "' . $sku . '" failed to load.');
        } catch (\Exception $e) {
            $this->moduleLog(__METHOD__ . '() - WARNING: Errors attemping to update product with sku "' . $productdata['sku'] . '": ' . $e->getMessage());
            $this->moduleLog($e->getTraceAsString(), true);
        }
    }

    /**
     * Updates Product status (enabled, disabled)
     *
     * @param ProductInterface $product
     * @param \Edg\ErpService\DataModel\ArticleInfo $article
     * @return $this
     */
    protected function updateProductStatus(ProductInterface $product, \Edg\ErpService\DataModel\ArticleInfo $article): ArticleInfo
    {
        $orderable = $article->getOrderable();

        $oldStatus = $product->getStatus();

        if ($orderable == 'true' && $product->getStatus() == Status::STATUS_DISABLED) {
            $this->moduleLog(
                __METHOD__ . ': Product #' . $product->getSku() . ': Setting product status to enabled.',
                true
            );
            $product->setStatus(Status::STATUS_ENABLED);
        } elseif ($orderable != 'true' && $product->getStatus() == Status::STATUS_ENABLED) {
            $this->moduleLog(
                __METHOD__ . ': Product #' . $product->getSku() . ': Updated product to status Disabled.',
                true
            );
            $product->setStatus(Status::STATUS_DISABLED);
        } else {
            $this->moduleLog(__METHOD__ . ': Product #' . $product->getSku() . ': No product status changes.', true);
        }

        $this->moduleLog(__METHOD__ . sprintf(
            ' Product #%s: Orderable="%s", Status "%s" => "%s"',
            $product->getSku(),
            $orderable,
            $oldStatus,
            $product->getStatus()
        ), true);

        return $this;
    }

    /**
     * Update stock settings for product based on articleResponse info xml
     *
     * @param ProductInterface $product
     * @param \Edg\ErpService\DataModel\ArticleInfo $article
     * @return $this
     */
    protected function updateProductStockSettings(
        ProductInterface $product,
        \Edg\ErpService\DataModel\ArticleInfo $article
    ) {
        $stock = $this->stockRegistry->getStockItem($product->getId());
        if ($stock->getItemId()) {
            $sku = $product->getSku();
            $newQty = $article->getAvailable();
            $newBackorders = ($article->getBackorder() == \Edg\ErpService\DataModel\ArticleInfo::BACKORDER_TRUE) ? 1 : 0;
            $oldBackorders = $stock->getBackorders();

            $inStock = ($newQty > 0 || $newBackorders == 1) ? 1 : 0;

            $productQtyAndStock = $product->getQuantityAndStockStatus();

            if ($stock->getUseConfigBackorders() != 0) {
                $stock->setUseConfigBackorders(false);
            }

            if ($stock->getQty() != $newQty) {
                $stock->setQty($newQty);
                $productQtyAndStock['qty'] = $newQty;
            }

            if ($stock->getIsInStock() != $inStock) {
                $stock->setIsInStock($inStock);
                $productQtyAndStock['is_in_stock'] = $inStock;
            }

            $this->moduleLog(__METHOD__ . sprintf(
                ' Setting stock data for product "%s": qty="%s", is_in_stock="%s", backorder="%s"',
                $sku,
                $newQty,
                $inStock,
                $newBackorders
            ), true);

            if ($newBackorders != $oldBackorders) {
                $stock->setBackorders($newBackorders);
                $this->moduleLog(
                    __METHOD__ . ': Product #' . $sku . ': Backorder status "' . $oldBackorders . '" updated to "' . $newBackorders . '".',
                    true
                );
            } else {
                $this->moduleLog(__METHOD__ . ': Product #' . $sku . ': No need for backorder update.', true);
            }

            $this->stockRegistry->updateStockItemBySku($sku, $stock);
            $product->setQuantityAndStockStatus($productQtyAndStock);
        } else {
            $this->moduleLog(__METHOD__ . '() - WARNING: could not load stock model for product "' . $product->getSku() . '"');
        }
        return $this;
    }

    /**
     * @param ProductInterface $product
     * @param \Edg\ErpService\DataModel\ArticleInfo $article
     * @return $this|bool
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    protected function updateProductPriceTiers(
        ProductInterface $product,
        \Edg\ErpService\DataModel\ArticleInfo $article
    ) {
        if ($product->getTypeId() == 'bundle') {
            $this->moduleLog(__METHOD__ . sprintf(
                ' Ignoring price tiers for bundle product #%s based on product type "%s"',
                $product->getSku(),
                $product->getTypeId()
            ), true);
            return false;
        }

        $this->moduleLog(__METHOD__ . sprintf(
            ' Applying price tiers for product #%s based on product type "%s"',
            $product->getSku(),
            $product->getTypeId()
        ), true);

        $oldTiers = $product->getTierPrices();
        foreach ($oldTiers as $oldTier) {
            $found = false;
            if ($oldTier->getCustomerGroupId() != \Magento\Customer\Api\Data\GroupInterface::CUST_GROUP_ALL) {
                $this->tierpriceManagement->remove(
                    $product->getSku(),
                    $oldTier->getCustomerGroupId(),
                    $oldTier->getQty()
                );
                continue;
            }
            foreach ($article->getPriceTiers() as $newTier) {
                if ($newTier['amount'] == $oldTier->getQty() && ((float)str_replace(
                    ',',
                    '.',
                    (string)$newTier['price']
                )) == $oldTier->getValue()
                ) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $this->tierpriceManagement->remove($product->getSku(), 'all', $oldTier->getQty());
            }
        }

        foreach ($article->getPriceTiers() as $tier) {
            $price = (float)str_replace(',', '.', (string)$tier['price']);
            $qty = (int)$tier['amount'];

            $this->moduleLog(
                __METHOD__ . ': Product #' . $product->getSku() . ': Adding tier price "' . $price . '" for qty "' . $qty . '"',
                true
            );

            try {
                $this->tierpriceManagement->add(
                    $product->getSku(),
                    'all',//\Magento\Customer\Api\Data\GroupInterface::CUST_GROUP_ALL,
                    $price,
                    $qty
                );
            } catch (\Exception $e) {
                $this->moduleLog(__METHOD__ . '() - ERROR: could not save price tier for product #' . $product->getSku());
                $this->moduleLog($e->getMessage());
            }
        }

        if (count($article->getPriceTiers()) > 0) {
            $product->setTierPrices($this->tierpriceManagement->getList($product->getSku(), 'all'));
        }

        return $this;
    }

    protected function updateProductTaxClass(ProductInterface $product, \Edg\ErpService\DataModel\ArticleInfo $article)
    {
        $map = $this->helper->getTaxClassMapping();
        $rate = (string)(float)str_replace(',', '.', $article->getBtw());

        if (array_key_exists($rate, $map)) {
            $this->moduleLog(__METHOD__ . sprintf(
                ' Product #%s: Found matching tax class id "%s" for rate "%s"',
                $product->getSku(),
                $map[$rate],
                $rate
            ), true);
            $product->setTaxClassId($map[$rate]);
        } else {
            $this->moduleLog(__METHOD__ . ': Product #' . $product->getSku() . ': WARNING: No matching tax_class_id for rate "' . $rate . '",');
        }

        return $this;
    }

    /**
     * @param String $sku
     * @return $this
     */
    public function addSkuToSync($sku)
    {
        $this->_skuList[] = $sku;
        return $this;
    }

    /**
     * @return array
     */
    public function getApiMessages()
    {
        return $this->apiMessages;
    }

    /**
     * @return array|mixed
     */
    protected function getSkuArray()
    {
        return $this->helper->getFullSkusArray();
    }
}
