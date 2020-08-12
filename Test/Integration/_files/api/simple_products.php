<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

\Magento\TestFramework\Helper\Bootstrap::getInstance()->reinitialize();

/** @var \Magento\TestFramework\ObjectManager $objectManager */
$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

require 'tax_classes.php';

$productRepository = $objectManager->get(Magento\Catalog\Api\ProductRepositoryInterface::class);

/** @var \Magento\Catalog\Api\CategoryLinkManagementInterface $categoryLinkManagement */
$categoryLinkManagement = $objectManager->create('Magento\Catalog\Api\CategoryLinkManagementInterface');

$statusses = [
    \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
    \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED
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
        ->setTaxClassId(0)
        ->setDescription('Description with <b>html tag</b>')
        ->setMetaTitle('meta title')
        ->setMetaKeyword('meta keyword')
        ->setMetaDescription('meta description')
        ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
        ->setStatus($statusses[$i % 2])
        ->setStockData(
            [
                'use_config_manage_stock'   => 1,
                'qty'                       => 100,
                'is_qty_decimal'            => 0,
                'is_in_stock'               => 1,
            ]
        );

    if($i == 4){
        $product->setTierPrice(
            [
                [
                    'website_id' => 0,
                    'cust_group' => \Magento\Customer\Model\Group::CUST_GROUP_ALL,
                    'price_qty'  => 2,
                    'price'      => 6,
                ],
                [
                    'website_id' => 0,
                    'cust_group' => \Magento\Customer\Model\Group::CUST_GROUP_ALL,
                    'price_qty'  => 5,
                    'price'      => 4,
                ],
                [
                    'website_id' => 0,
                    'cust_group' => \Magento\Customer\Model\Group::NOT_LOGGED_IN_ID,
                    'price_qty'  => 3,
                    'price'      => 4,
                ],
            ]
        );
        $product->setImage('/m/a/magento_image.jpg')
            ->setSmallImage('/m/a/magento_image.jpg')
            ->setThumbnail('/m/a/magento_image.jpg')
            ->setData('media_gallery', ['images' => [
                [
                    'file' => '/m/a/magento_image.jpg',
                    'position' => 1,
                    'label' => 'Image Alt Text',
                    'disabled' => 0,
                    'media_type' => 'image',
                    'types' => ['image', 'small_image', 'thumbnail'],
                    'content' => [
                        'data' => [
                            'name' => 'image',
                            'type' => 'image/jpeg',
                            'base64_encoded_data' => base64_encode(file_get_contents(__DIR__ . '/magento_image.jpg'))
                        ]
                    ]
                ],
            ]]);
        $product->setStoreId(0);
    }
    /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepository */
    $productRepository->save($product);

    $categoryLinkManagement->assignProductToCategories(
        $product->getSku(),
        [2]
    );
}
