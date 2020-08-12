<?php
require 'util.php';
require 'multiple_products.php';

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
$productRepository = $objectManager->get(Magento\Catalog\Api\ProductRepositoryInterface::class);

$product = $productRepository->get('bundle-product');

/** @var $typeInstance \Magento\Bundle\Model\Product\Type */
$typeInstance = $product->getTypeInstance();
$typeInstance->setStoreFilter($product->getStoreId(), $product);
$optionCollection = $typeInstance->getOptionsCollection($product);

$bundleOptions = [];
$bundleOptionsQty = [];
foreach ($optionCollection as $option) {
    /** @var $option \Magento\Bundle\Model\Option */
    $selectionsCollection = $typeInstance->getSelectionsCollection([$option->getId()], $product);
    if ($option->isMultiSelection()) {
        $bundleOptions[$option->getId()] = array_column($selectionsCollection->toArray(), 'selection_id');
    } else {
        $bundleOptions[$option->getId()] = $selectionsCollection->getFirstItem()->getSelectionId();
    }
    $bundleOptionsQty[$option->getId()] = 1;
}

$requestInfo = [
    'product' => $product->getId(),
    'bundle_option' => $bundleOptions,
    'bundle_option_qty' => $bundleOptionsQty,
    'qty' => 1,
];

/** @var \Magento\Sales\Model\Order\Item $orderItem */
$orderItem = $objectManager->create('Magento\Sales\Model\Order\Item');
$orderItem->setProductId($product->getId());
$orderItem->setQtyOrdered(1);
$orderItem->setBasePrice($product->getPrice());
$orderItem->setPrice($product->getPrice());
$orderItem->setRowTotal($product->getPrice());
$orderItem->setProductType($product->getTypeId());
$orderItem->setProductOptions(['info_buyRequest' => $requestInfo]);

$product = $productRepository->get('simple-1');
/** @var \Magento\Sales\Model\Order\Item $orderItem2 */
$orderItem2 = $objectManager->create('Magento\Sales\Model\Order\Item');
$orderItem2->setProductId($product->getId())->setQtyOrdered(2);
$orderItem2->setBasePrice($product->getPrice());
$orderItem2->setPrice($product->getPrice());
$orderItem2->setRowTotal($product->getPrice());
$orderItem2->setProductType('simple');


$product = $productRepository->get('simple-2');
/** @var \Magento\Sales\Model\Order\Item $orderItem3 */
$orderItem3 = $objectManager->create('Magento\Sales\Model\Order\Item');
$orderItem3->setProductId($product->getId())->setQtyOrdered(2);
$orderItem3->setBasePrice($product->getPrice());
$orderItem3->setPrice($product->getPrice());
$orderItem3->setRowTotal($product->getPrice());
$orderItem3->setProductType('simple');


$addressData = include $fixtureBaseDir .'/Magento/Sales/_files/address_data.php';

$billingAddress = $objectManager->create('Magento\Sales\Model\Order\Address', ['data' => $addressData]);
$billingAddress->setAddressType('billing');

$shippingAddress = clone $billingAddress;
$shippingAddress->setId(null)->setAddressType('shipping');

$payment = $objectManager->create('Magento\Sales\Model\Order\Payment');
$payment->setMethod('checkmo');

/** @var \Magento\Sales\Model\Order $order */
$order = $objectManager->create('Magento\Sales\Model\Order');
$order->setIncrementId(
    '100000001'
)->setState(
    \Magento\Sales\Model\Order::STATE_PROCESSING
)->setStatus(
    $order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
)->setSubtotal(
    100
)->setGrandTotal(
    100
)->setBaseSubtotal(
    100
)->setBaseGrandTotal(
    100
)->setCustomerIsGuest(
    true
)->setCustomerEmail(
    'customer@null.com'
)->setBillingAddress(
    $billingAddress
)->setShippingAddress(
    $shippingAddress
)->setStoreId(
    $objectManager->get('Magento\Store\Model\StoreManagerInterface')->getStore()->getId()
)->addItem(
    $orderItem
)->addItem(
    $orderItem2
)->addItem(
    $orderItem3
)->setPayment(
    $payment
);
$order->save();
