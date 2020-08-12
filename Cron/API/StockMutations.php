<?php

namespace Edg\Erp\Cron\API;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Mail\Message;
use Zend\Log\Logger;

class StockMutations extends AbstractCron
{

    const XML_PATH_STOCKMUTATIONS_STRIP_PREFIX = 'stockmutations_import_strip_prefix';

    protected $stockregistry;

    public function __construct(
        \Edg\Erp\Helper\Data $helper,
        DirectoryList $directoryList,
        ConfigInterface $config,
        Message $message,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        $settings = []
    ) {
        $this->stockregistry = $stockRegistry;
        parent::__construct($helper, $directoryList, $config, $message, $storeManager, $settings);
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
        $this->addLogStreamToServiceLogger($this->_stockmutationsDir . DIRECTORY_SEPARATOR . "log_{$date}.log");

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
                    $this->serviceLog(sprintf('Setting product stock qty of sku %s with Magento ID %s to %s', $sku,
                        $stockItem->getProductId(), $qty));

                    $oldQty = $stockItem->getQty();
                    $stockItem->setQty($qty);
                    if (round($oldQty, 0) != round($qty, 0)) {
                        $id = $this->stockregistry->updateStockItemBySku($sku, $stockItem);

                        $this->serviceLog(sprintf(
                            'Successfully updated stock for product %s with Magento ID %s and Magento Stock item ID %s from %s to %s',
                            $sku,
                            $stockItem->getProductId(),
                            $id,
                            round($oldQty, 0),
                            $qty
                        ));
                    } else {
                        $this->serviceLog(sprintf(
                            'No stock update needed for product %s with Magento ID %s and Magento Stock item ID %s, qty was already set to %s',
                            $sku,
                            $stockItem->getProductId(),
                            $stockItem->getItemId(),
                            $qty
                        ));
                    }

                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    $this->serviceLog('Product with SKU ' . $sku . ' was not found in the Magento catalog');
                } catch (\Exception $e) {
                    $this->serviceLog('Error during setting stock for ' . $sku, Logger::ERR);
                    $this->serviceLog($e->getMessage(), Logger::ERR);
                    $this->sendErrorMail($this->getErrorEmail(),
                        'Exception occured during stock mutations from EDG for product ' . $sku, $e->getMessage());
                }
            }
        }
    }
}