<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

use Bold\PIM\Model\SourceModel\Eav\ArticleType;

\Magento\TestFramework\Helper\Bootstrap::getInstance()->reinitialize();

/** @var \Magento\TestFramework\ObjectManager $objectManager */
$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();


$productRepository = $objectManager->get(Magento\Catalog\Api\ProductRepositoryInterface::class);

/** @var \Magento\Catalog\Api\CategoryLinkManagementInterface $categoryLinkManagement */
$categoryLinkManagement = $objectManager->create('Magento\Catalog\Api\CategoryLinkManagementInterface');

$articletypes = [
    ArticleType::TYPE_NO_STOCK,
    ArticleType::TYPE_HOOFDARTIKEL,
    ArticleType::TYPE_ONDERDEEL,
    ArticleType::TYPE_ONDERDEEL
];
for($i = 1; $i <= 4; $i++){
    /** @var $product \Magento\Catalog\Model\Product */
    $product = $objectManager->create('Magento\Catalog\Model\Product');
    $product->isObjectNew(true);
    $product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
        ->setId($i)
        ->setAttributeSetId(4)
        ->setWebsiteIds([1])
        ->setName('Simple Product ' . $i)
        ->setSku('simple-' . $i)
        ->setPrice(10)
        ->setWeight(1)
        ->setShortDescription("Short description")
        ->setBoldPimArticletype($articletypes[$i-1])
        ->setTaxClassId(0)
        ->setDescription('Description with <b>html tag</b>')
        ->setMetaTitle('meta title')
        ->setMetaKeyword('meta keyword')
        ->setMetaDescription('meta description')
        ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
        ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
        ->setStockData(
            [
                'use_config_manage_stock'   => 1,
                'qty'                       => 100,
                'is_qty_decimal'            => 0,
                'is_in_stock'               => 1,
            ]
        );

    /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepositoryFactory */
    $productRepositoryFactory = $objectManager->create('Magento\Catalog\Api\ProductRepositoryInterface');
    $productRepositoryFactory->save($product);

    $categoryLinkManagement->assignProductToCategories(
        $product->getSku(),
        [2]
    );
}

$product = $objectManager->create('Magento\Catalog\Model\Product');
$product
    ->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_BUNDLE)
    ->setAttributeSetId(4)
    ->setWebsiteIds([1])
    ->setName('Bundle Product')
    ->setSku('bundle-product')
    ->setDescription('Description with <b>html tag</b>')
    ->setBoldPimArticletype(ArticleType::TYPE_GROEPARTIKEL)
    ->setShortDescription('Bundle')
    ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
    ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
    ->setStockData(
        [
            'use_config_manage_stock' => 0,
            'manage_stock' => 0,
            'use_config_enable_qty_increments' => 1,
            'use_config_qty_increments' => 1,
            'is_in_stock' => 0,
        ]
    )
    ->setBundleOptionsData(
        [
            [
                'title' => 'Bundle Product Items',
                'default_title' => 'Bundle Product Items',
                'type' => 'checkbox',
                'required' => 1,
                'delete' => '',
                'position' => 0,
                'option_id' => '',
            ],
        ]
    )
    ->setBundleSelectionsData(
        [
            [
                [
                    'product_id' => 3,
                    'selection_qty' => 1,
                    'selection_can_change_qty' => 1,
                    'delete' => '',
                    'position' => 0,
                    'selection_price_type' => 0,
                    'selection_price_value' => 0.0,
                    'option_id' => '',
                    'selection_id' => '',
                    'is_default' => 1,
                ],
                [
                    'product_id' => 4,
                    'selection_qty' => 1,
                    'selection_can_change_qty' => 1,
                    'delete' => '',
                    'position' => 0,
                    'selection_price_type' => 0,
                    'selection_price_value' => 0.0,
                    'option_id' => '',
                    'selection_id' => '',
                    'is_default' => 1,
                ]
            ],
        ]
    )->setCustomAttributes([
        "price_type" => [
            'attribute_code' => 'price_type',
            'value' => \Magento\Bundle\Model\Product\Price::PRICE_TYPE_DYNAMIC
        ],
        "price_view" => [
            "attribute_code" => "price_view",
            "value" => "1",
        ],
    ])
    ->setCanSaveBundleSelections(true)
    ->setHasOptions(false)
    ->setAffectBundleProductSelections(true);
if ($product->getBundleOptionsData()) {
    $options = [];
    foreach ($product->getBundleOptionsData() as $key => $optionData) {
        if (!(bool)$optionData['delete']) {
            $option = $objectManager->create('Magento\Bundle\Api\Data\OptionInterfaceFactory')
                ->create(['data' => $optionData]);
            $option->setSku($product->getSku());
            $option->setOptionId(null);

            $links = [];
            $bundleLinks = $product->getBundleSelectionsData();
            if (!empty($bundleLinks[$key])) {
                foreach ($bundleLinks[$key] as $linkData) {
                    if (!(bool)$linkData['delete']) {
                        /** @var \Magento\Bundle\Api\Data\LinkInterface$link */
                        $link = $objectManager->create('Magento\Bundle\Api\Data\LinkInterfaceFactory')
                            ->create(['data' => $linkData]);
                        $linkProduct = $productRepository->getById($linkData['product_id']);
                        $link->setSku($linkProduct->getSku());
                        $link->setQty($linkData['selection_qty']);
                        if (isset($linkData['selection_can_change_qty'])) {
                            $link->setCanChangeQuantity($linkData['selection_can_change_qty']);
                        }
                        $links[] = $link;
                    }
                }
                $option->setProductLinks($links);
                $options[] = $option;
            }
        }
    }
    $extension = $product->getExtensionAttributes();
    $extension->setBundleProductOptions($options);
    $product->setExtensionAttributes($extension);
}
$productRepository->save($product);

