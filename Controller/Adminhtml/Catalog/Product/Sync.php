<?php

declare(strict_types = 1);

/**
 * Sync
 *
 * @copyright Copyright © 2018 Bold Commerce BV. All rights reserved.
 * @author    dev@boldcommerce.nl
 */

namespace Bold\PIM\Controller\Adminhtml\Catalog\Product;

use Magento\Backend\App\Action;
use Bold\PIM\Cron\API\ArticleInfo;
use Magento\Catalog\Model\ProductFactory;

class Sync extends Action
{
    const ADMIN_RESOURCE = 'Magento_Catalog::products';

    private $articleInfo;

    private $productFactory;

    public function __construct(Action\Context $context, ArticleInfo $articleInfo, ProductFactory $productFactory)
    {
        parent::__construct($context);
        $this->articleInfo = $articleInfo;
        $this->productFactory = $productFactory;
    }


    public function execute()
    {
        $productId = $this->getRequest()->getParam('id', false);
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($productId) {
            $product = $this->productFactory->create()->load($productId);
            if($product->getId()) {
                $this->articleInfo->addSkuToSync($product->getSku());
                $this->articleInfo->execute();
                $messages = $this->articleInfo->getApiMessages();
                foreach($messages as $message) {
                    switch ($message['type']) {
                        case 'error':
                            $this->messageManager->addErrorMessage(__($message['message']));
                            break;
                        default:
                            $this->messageManager->addSuccessMessage(__($message['message']));
                    }
                }
            } else {
                $this->messageManager->addErrorMessage(__('Could not find product with id %1', $product->getId()));
            }
            return $resultRedirect->setPath(
                'catalog/product/edit',
                ['id' => $productId, '_current' => true]
            );
        }

        $this->messageManager->addErrorMessage(__('Invalid request'));
        $resultRedirect->setPath('catalog/product/');


    }
}
