<?php

namespace Edg\Erp\Observer\Frontend;


use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;

class PreventDuplicateSkuInQuote implements ObserverInterface
{
    protected $scopeConfig;
    protected $quote;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $configInterface,
        \Magento\Checkout\Model\Session $session
    ) {
        $this->quote = $session->getQuote();
        $this->scopeConfig = $configInterface;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (!$this->scopeConfig->isSetFlag('bold_orderexim/settings/block_duplicate_sku',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
        ) {
            return false;
        }

        $quoteItems = $this->quote->getAllVisibleItems();
        $items = $observer->getEvent()->getItems();

        foreach ($items as $newItem) {
            if ($newItem->getProductType() === 'simple') {
                continue;
            }
            $totals = array();
            foreach ($quoteItems as $item) {
                if ($newItem->getProductId() === $item->getProductId()) {
                    $key = $item->getProductId();
                    if (array_key_exists($key, $totals)) {
                        throw new CouldNotSaveException($this->scopeConfig->getValue('bold_orderexim/settings/duplicate_sku_error'));
                    } else {
                        $totals[$key] = 1;
                    }
                }
            }
        }
    }
}