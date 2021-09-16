<?php

namespace Edg\Erp\Helper;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Model\Calculation;

class Data extends AbstractHelper
{
    const XML_CONFIG_REMOTE = 'bold_orderexim/remote';

    const XML_CONFIG_SETTINGS = 'bold_orderexim/settings';

    const XML_CONFIG_LOGGING = 'bold_orderexim/logging';

    const XML_CONFIG_PATH_SETTING_DEBUG_ENABLED = 'bold_orderexim/logging/logging_debug_enabled';

    protected $productRepository;

    protected $criteriaBuilder;

    protected $taxCalculation;

    protected $moduleLog;

    protected $_cache = [];

    public function __construct(
        Context $context,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $criteriaBuilder,
        Calculation $taxCalculation,
        \Edg\Erp\Logger\PimLogger $logger
    ) {
        parent::__construct($context);
        $this->productRepository = $productRepository;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->taxCalculation = $taxCalculation;
        $this->moduleLog = $logger;
    }


    /**
     * Retrieve Pim to Magento field mapping
     */
    public function getPimFieldMapping()
    {
        return [
            'articlenumber' => 'sku',
            'description' => 'name',
            'isbn' => 'isbn',
            'weight' => 'weight',
            'backorder-text' => 'backorder_text',
            'price' => 'price',
            'articletype' => 'bold_pim_articletype',
        ];
    }

    public function getSystemConfigSetting($path, $scope = ScopeInterface::SCOPE_STORE)
    {
        return $this->scopeConfig->getValue($path, $scope);
    }

    /**
     * Retrieve array of order statuses that will be exported to pim
     */
    public function getOrderStatusesAndPaymentCodeToExport()
    {
        $statuses = $this->getConfigSetting('export_order_statuses');

        // Format: processing_ogone:ideal,processing_ogone:ordermo
        $statuses = explode(',', $statuses);

        // Strip payment status
        foreach ($statuses as $iter => $status) {
            $_status = explode(':', $status);

            if (!isset($_status[1])) {
                $_status[1] = null;
            }

            $statuses[$iter] = array(
                'order_status' => $_status[0],
                'payment_code' => $_status[1]
            );
        }

        return $statuses;
    }

    /**
     * Retrieve PIM settings
     */
    public function getConfigSetting($setting = null)
    {
        $settings = $this->scopeConfig->getValue(self::XML_CONFIG_SETTINGS, ScopeInterface::SCOPE_STORE);

        if (is_array($settings)) {
            if ($setting !== null) {
                return (array_key_exists($setting, $settings)) ? $settings[$setting] : false;
            } else {
                return $settings;
            }
        } else {
            return false;
        }
    }

    /**
     * Retrieve array of order statuses that will be exported to pim
     */
    public function getOrderStatusesToExport()
    {
        $statuses = $this->getConfigSetting('export_order_statuses');

        // Format: processing_ogone:ideal,processing_ogone:ordermo
        $statuses = explode(',', $statuses);

        // Strip payment status
        foreach ($statuses as $iter => $status) {
            $stat = explode(':', $status);
            $statuses[$iter] = trim(reset($stat));
        }

        return $statuses;
    }

    /**
     * Retrieve list of SKUs in Magento catalog
     */
    public function getUpdateSkusArray()
    {
        if (!array_key_exists('product_sku_array', $this->_cache)) {
            $this->log(__METHOD__ . '() - Generating comma-separated list of SKUs', true);

            $sku = array();
            $prefix = $this->getSkuPrefix();
            $client = $this->getSoapClient();

            // Get updated/changed Products
            $mutationsFull = $client->pullStockUpdates($this->getEnvironmentTag());
            // Add mutated Products to Sky array
            foreach($mutationsFull as $mutationsList) {
                $mutations = $mutationsList->getMutations();
                foreach($mutations as $mutatedProduct){
                    $sku[] = $prefix . $mutatedProduct->getSku();
                }
            }

            $this->log(__METHOD__ . '() - List contains ' . sizeof($sku) . ' skus.');
            $this->_cache['product_sku_array'] = $sku;
        }

        return $this->_cache['product_sku_array'];
    }

    /**
     * Retrieve list of SKUs in Magento catalog
     */
    public function getFullSkusArray()
    {
        if (!array_key_exists('product_sku_array', $this->_cache)) {
            $this->log(__METHOD__ . '() - Generating comma-separated list of SKUs', true);

            $products = $this->productRepository->getList($this->criteriaBuilder->create());
            $_productCollection = $products->getItems();
            $sku = array();
            $prefix = $this->getSkuPrefix();

            foreach ($_productCollection as $product) {
                $sku[] = $prefix . $product->getSku();
            }

            $this->log(__METHOD__ . '() - List contains ' . sizeof($sku) . ' skus.');

            $this->_cache['product_sku_array'] = $sku;
        }

        return $this->_cache['product_sku_array'];
    }

    /**
     * Log facility
     */
    public function log($msg, $debug = false)
    {
        if ($debug == true) {
            if ($this->scopeConfig->isSetFlag(self::XML_CONFIG_PATH_SETTING_DEBUG_ENABLED,
                ScopeInterface::SCOPE_STORE)
            ) {
                $this->moduleLog->addDebug($msg);
            }
        } else {
            $this->moduleLog->addInfo($msg);
        }
    }

    public function getSkuPrefix()
    {
        return $this->getConfigSetting('sku_prefix');
    }

    /**
     * Get tax class mapping based on mapping configured in bold_orderexim/articleinfo/tax_class_mapping
     * @return array
     */
    public function getTaxClassMapping()
    {
        $taxClassMappingConfig = $this->scopeConfig->getValue('bold_orderexim/articleinfo/tax_class_mapping', ScopeInterface::SCOPE_STORE);
        $taxClassMapping = [];

        if ($taxClassMappingConfig && is_string($taxClassMappingConfig)) {
            foreach (json_decode($taxClassMappingConfig) as $taxMappingRow) {
                $taxClassMapping[$taxMappingRow->pim_tax_rate] = $taxMappingRow->magento_tax_class;
            }
        }

        return $taxClassMapping;
    }

    public function getArticleInfoSettings()
    {
        return $this->scopeConfig->getValue('bold_orderexim/articleinfo', ScopeInterface::SCOPE_STORE);
    }

    public function getArticleInfoSetting($entry)
    {
        return $this->scopeConfig->getValue('bold_orderexim/articleinfo/' . $entry, ScopeInterface::SCOPE_STORE);
    }

    public function getOrderImportSendEmailAfterShipping()
    {
        return $this->getConfigSetting('order_import_send_email_after_shipping');
    }

    public function getOrderImportStatusAfterShipping()
    {
        return $this->getConfigSetting('order_import_status_after_shipping');
    }

    /**
     * Config setting wrapper
     */
    public function isArticleImportEnabled()
    {
        return ($this->getConfigSetting('articleinfo_import_enabled') === '1');
    }

    /**
     * Config setting wrapper
     */
    public function isPullOrdersEnabled()
    {
        return ($this->getConfigSetting('order_import_enabled') === '1');
    }

    /**
     * Config setting wrapper
     */
    public function isUploadOrdersEnabled()
    {
        return ($this->getConfigSetting('order_export_enabled') === '1');
    }

    /**
     * Config setting wrapper
     */
    public function isStockMutationsEnabled()
    {
        return ($this->getConfigSetting('stockmutations_import_enabled') === '1');
    }

    /**
     * Config backorder text
     */
    public function getBackorderText()
    {
        return $this->getConfigSetting('backorder_text');
    }

    /**
     * Config backorder text in order confirmation
     */
    public function getBackorderTextConfirmationEnabled()
    {
        return $this->getConfigSetting('backorder_confirmation');
    }

    /**
     * Retrieve Pim Environmental tag
     */
    public function getEnvironmentTag()
    {
        return $this->getConfigSetting('environment_tag');
    }

    /**
     * Retrieve PIM Log settings
     */
    public function getLogSetting($setting = null)
    {
        $settings = $this->scopeConfig->getValue(self::XML_CONFIG_LOGGING, ScopeInterface::SCOPE_STORE);

        if (is_array($settings)) {
            if ($setting !== null) {
                return (array_key_exists($setting, $settings)) ? $settings[$setting] : false;
            } else {
                return $settings;
            }
        } else {
            return false;
        }
    }

    /**
     * Retrieve new PIM soap client instance
     *
     * @return \Edg\ErpService\Client
     */
    public function getSoapClient()
    {
        $settings = array(
            'trace' => 1,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,

            'login' => $this->getWsUserName(),
            'password' => $this->getWsPassword(),

            'connection_timeout' => 5,

            'soap_version' => $this->getSoapVersion(),
        );

        if ($location = $this->getSoapLocationEndpoint()) {
            $settings['location'] = $location;
        }

        $client = new \Edg\ErpService\Client($this->getWsAddress(), $settings);

        if ($this->getLogLibraryEnabled()) {
            $debug = $this->getLogLibraryDebugMode() ? true : false;
            $client->setLogger($this->getPimLogger(), $debug);
        }

        return $client;
    }

    /**
     * Retrieve PIM username
     */
    public function getWsUserName()
    {
        return $this->scopeConfig->getValue('bold_orderexim/remote/login', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Retrieve PIM password
     */
    public function getWsPassword()
    {
        return $this->scopeConfig->getValue('bold_orderexim/remote/password', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Retrieve Soap version
     * @return int  Soap version
     */
    public function getSoapVersion()
    {
        $version = $this->scopeConfig->getValue('bold_orderexim/remote/soap_version', ScopeInterface::SCOPE_STORE);

        if (!$version) {
            $version = SOAP_1_1;
        }

        return $version;
    }

    /**
     * retrieve endpoint URL for SOAP requests
     *
     * @return mixed
     */
    public function getSoapLocationEndpoint()
    {
        return $this->scopeConfig->getValue('bold_orderexim/remote/location', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Retrieve PIM service uri
     */
    public function getWsAddress()
    {
        return $this->scopeConfig->getValue('bold_orderexim/remote/uri', ScopeInterface::SCOPE_STORE);
    }

    public function getLogLibraryEnabled()
    {
        return $this->getLoggingSetting('library_log_enabled');
    }

    public function getLoggingSetting($entry)
    {
        return $this->scopeConfig->getValue('bold_orderexim/logging/' . $entry, ScopeInterface::SCOPE_STORE);
    }

    public function getLogLibraryDebugMode()
    {
        return $this->getLoggingSetting('library_debug_enabled');
    }

    /**
     * retrieve Edg Erp logger
     *
     * @return \Edg\Erp\Logger\PimLogger
     */
    public function getPimLogger()
    {
        return $this->moduleLog;
    }
}
