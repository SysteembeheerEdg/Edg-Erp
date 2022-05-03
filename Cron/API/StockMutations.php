<?php

namespace Edg\Erp\Cron\API;

use Edg\Erp\Helper\Data;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Logger\Monolog;
use Monolog\Logger;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManager;

class StockMutations extends AbstractCron
{

    const XML_PATH_STOCKMUTATIONS_STRIP_PREFIX = 'stockmutations_import_strip_prefix';

    /**
     * @var StockRegistryInterface
     */
    protected StockRegistryInterface $stockregistry;

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
     * @param StockRegistryInterface $stockRegistry
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
        StockRegistryInterface $stockRegistry,
        array $settings = []
    ) {
        $this->stockregistry = $stockRegistry;
        parent::__construct($helper, $directoryList, $monolog, $config, $transportBuilder, $storeManager, $settings);
    }

    public function execute()
    {
        $this->moduleLog(__METHOD__ . '();', true);

        if ($this->helper->isStockMutationsEnabled()) {
            $this->processMutations();
        }

        return $this;
    }

    protected function processMutations()
    {
        $date = date("Y_m_d");
        $stream = $this->addLogStreamToServiceLogger($this->_stockmutationsDir . DIRECTORY_SEPARATOR . "log_{$date}.log");

        $client = $this->helper->getSoapClient();

        $stripPrefix = $this->helper->getConfigSetting(self::XML_PATH_STOCKMUTATIONS_STRIP_PREFIX);


        $results = $client->pullStockUpdates($this->helper->getEnvironmentTag());

        if (count($results) < 1) {
            $this->moduleLog(__METHOD__ . ': No mutations found', true);
        }


        foreach ($results as $result) {
            $mutations = $result->getMutations();
            foreach ($mutations as $stockMutation) {
                $sku = $stockMutation->getSku();
                $qty = $stockMutation->getStock();
                try {
                    $stockItem = $this->stockregistry->getStockItemBySku($sku);
                    $this->serviceLog($stream, sprintf('Setting product stock qty of sku %s with Magento ID %s to %s', $sku,
                        $stockItem->getProductId(), $qty));

                    $oldQty = $stockItem->getQty();
                    $stockItem->setQty($qty);
                    if (round($oldQty, 0) != round($qty, 0)) {
                        $id = $this->stockregistry->updateStockItemBySku($sku, $stockItem);

                        $this->serviceLog($stream, sprintf(
                            'Successfully updated stock for product %s with Magento ID %s and Magento Stock item ID %s from %s to %s',
                            $sku,
                            $stockItem->getProductId(),
                            $id,
                            round($oldQty, 0),
                            $qty
                        ));
                    } else {
                        $this->serviceLog($stream, sprintf(
                            'No stock update needed for product %s with Magento ID %s and Magento Stock item ID %s, qty was already set to %s',
                            $sku,
                            $stockItem->getProductId(),
                            $stockItem->getItemId(),
                            $qty
                        ));
                    }

                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    $this->serviceLog($stream, 'Product with SKU ' . $sku . ' was not found in the Magento catalog');
                } catch (\Exception $e) {
                    $this->serviceLog($stream, 'Error during setting stock for ' . $sku, Logger::ERROR);
                    $this->serviceLog($stream, $e->getMessage(), Logger::ERROR);
                    $this->sendErrorMail($this->getErrorEmail(),
                        'Exception occured during stock mutations from EDG for product ' . $sku, $e->getMessage());
                }
            }
        }
    }
}
