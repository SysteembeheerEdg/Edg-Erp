<?php

namespace Edg\Erp\Observer\Frontend;


use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class PreventDuplicateSkuInQuote implements ObserverInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var CartInterface|Quote
     */
    protected $quote;

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function __construct(
        ScopeConfigInterface $configInterface,
        \Magento\Checkout\Model\Session $session
    ) {
        $this->quote = $session->getQuote();
        $this->scopeConfig = $configInterface;
    }

    /**
     * @throws CouldNotSaveException
     */
    public function execute(Observer $observer)
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
