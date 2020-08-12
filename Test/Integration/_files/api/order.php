<?php

require __DIR__ . '/../util.php';
require 'simple_products.php';

$addressData = include $fixtureBaseDir .'/Magento/Sales/_files/address_data.php';
include $fixtureBaseDir . '/Magento/Customer/_files/customer.php';


$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

$billingAddress = $objectManager->create('Magento\Sales\Model\Order\Address', ['data' => $addressData]);
$billingAddress->setAddressType('billing');

$shippingAddress = clone $billingAddress;
$shippingAddress->setId(null)->setAddressType('shipping');

$payment = $objectManager->create('Magento\Sales\Model\Order\Payment');
$payment->setMethod('checkmo');

$product = $productRepository->get('simple-1');
$product2 = $productRepository->get('simple-2');
$product3 = $productRepository->get('simple-4');


/** @var \Magento\Sales\Model\Order\Item $orderItem */
$orderItem = $objectManager->create('Magento\Sales\Model\Order\Item');
$orderItem->setProductId($product->getId())->setQtyOrdered(2);
$orderItem->setBasePrice($product->getPrice());
$orderItem->setPrice($product->getPrice());
$orderItem->setRowTotal($product->getPrice());
$orderItem->setProductType('simple');
$orderItem->setSku($product->getSku());

/** @var \Magento\Sales\Model\Order\Item $orderItem2 */
$orderItem2 = $objectManager->create('Magento\Sales\Model\Order\Item');
$orderItem2->setProductId($product2->getId())->setQtyOrdered(3);
$orderItem2->setBasePrice($product2->getPrice());
$orderItem2->setPrice($product2->getPrice());
$orderItem2->setRowTotal($product2->getPrice());
$orderItem2->setProductType('simple');
$orderItem2->setSku($product2->getSku());


/** @var \Magento\Sales\Model\Order\Item $orderItem3 */
$orderItem3 = $objectManager->create('Magento\Sales\Model\Order\Item');
$orderItem3->setProductId($product3->getId())->setQtyOrdered(5);
$orderItem3->setBasePrice($product3->getPrice());
$orderItem3->setPrice($product3->getPrice());
$orderItem3->setRowTotal($product3->getPrice());
$orderItem3->setProductType('simple');
$orderItem3->setSku($product3->getSku());

$orderItem4 = clone $orderItem3;
$orderItem5 = clone $orderItem2;


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
    $orderItem3
)->setPayment(
    $payment
);
$order->save();

$billingAddress2 = $objectManager->create('Magento\Sales\Model\Order\Address', ['data' => $addressData]);
$billingAddress2->setAddressType('billing');

$shippingAddress2 = clone $billingAddress2;
$shippingAddress2->setId(null)->setAddressType('shipping');

$payment2 = $objectManager->create('Magento\Sales\Model\Order\Payment');
$payment2->setMethod('checkmo');

$order2 = $objectManager->create('Magento\Sales\Model\Order');
$order2->setIncrementId(
    '100000002'
)->setState(
    \Magento\Sales\Model\Order::STATE_PROCESSING
)->setStatus(
    $order2->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
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
    $billingAddress2
)->setShippingAddress(
    $shippingAddress2
)->setStoreId(
    $objectManager->get('Magento\Store\Model\StoreManagerInterface')->getStore()->getId()
)->addItem(
    $orderItem2
)->addItem(
    $orderItem4
)->setPayment(
    $payment2
);
$order2->save();

$billingAddress3 = $objectManager->create('Magento\Sales\Model\Order\Address', ['data' => $addressData]);
$billingAddress3->setAddressType('billing');

$shippingAddress3 = clone $billingAddress3;
$shippingAddress3->setId(null)->setAddressType('shipping');

$payment3 = $objectManager->create('Magento\Sales\Model\Order\Payment');
$payment3->setMethod('checkmo');

$order3 = $objectManager->create('Magento\Sales\Model\Order');
$order3->setIncrementId(
    '000000002'
)->setState(
    \Magento\Sales\Model\Order::STATE_PROCESSING
)->setStatus(
    $order3->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
)->setSubtotal(
    100
)->setGrandTotal(
    100
)->setBaseSubtotal(
    100
)->setBaseGrandTotal(
    100
)->setCustomerIsGuest(
    false
)->setCustomerEmail(
    'customer@null.com'
)->setBillingAddress(
    $billingAddress3
)->setShippingAddress(
    $shippingAddress3
)->setStoreId(
    $objectManager->get('Magento\Store\Model\StoreManagerInterface')->getStore()->getId()
)->addItem(
    $orderItem5
)->setPayment(
    $payment3
);
$order3->setCustomerId($customer->getId());
$order3->save();
