<?php

namespace Edg\Erp\Observer\Frontend;


use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;

class PreventDuplicateSkuInCheckout implements ObserverInterface
{
    protected $scopeConfig;
    protected $quote;
    protected $responseFactory;
    protected $urlInterface;
    protected $actionFlag;
    protected $messageManager;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $configInterface,
        \Magento\Checkout\Model\Session $session,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\UrlInterface $url,
        \Magento\Framework\App\ActionFlag $actionFlag,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->quote = $session->getQuote();
        $this->messageManager = $messageManager;
        $this->scopeConfig = $configInterface;
        $this->responseFactory = $responseFactory;
        $this->urlInterface = $url;
        $this->actionFlag = $actionFlag;
    }

    /**
     * @param Observer $observer
     * @return false|void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer)
    {
        if (!$this->scopeConfig->isSetFlag('bold_orderexim/settings/block_duplicate_sku',
            ScopeInterface::SCOPE_STORE)
        ) {
            return false;
        }

        $quoteItems = $this->quote->getAllVisibleItems();

        $totals = [];

        foreach ($quoteItems as $item) {
            if ($item->getProductType() === 'simple') {
                continue;
            }
            $key = $item->getProductId();
            if (array_key_exists($key, $totals)) {
                $this->messageManager->addErrorMessage($this->scopeConfig->getValue('bold_orderexim/settings/duplicate_sku_error_checkout',
                    ScopeInterface::SCOPE_STORE));

                $this->responseFactory->create()->setRedirect($this->urlInterface->getUrl('checkout/cart'));
                $this->actionFlag->set('', \Magento\Framework\App\ActionInterface::FLAG_NO_DISPATCH, true);
                return;
            } else {
                $totals[$key] = 1;
            }
        }
    }
}
