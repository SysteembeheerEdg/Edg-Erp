<?php

require 'order.php';

$orderService = $objectManager->create(
    'Magento\Sales\Api\InvoiceManagementInterface'
);
$invoice = $orderService->prepareInvoice($order);
$invoice->register();
$order = $invoice->getOrder();
$order->setIsInProcess(true);
$transactionSave = $objectManager->create('Magento\Framework\DB\Transaction');
$transactionSave->addObject($invoice)->addObject($order)->save();


$invoice2 = $orderService->prepareInvoice($order2);
$invoice2->register();
$order2 = $invoice2->getOrder();
$order2->setIsInProcess(true);
$transactionSave = $objectManager->create('Magento\Framework\DB\Transaction');
$transactionSave->addObject($invoice2)->addObject($order2)->save();


$invoice3 = $orderService->prepareInvoice($order3);
$invoice3->register();
$order3 = $invoice3->getOrder();
$order3->setIsInProcess(true);
$transactionSave = $objectManager->create('Magento\Framework\DB\Transaction');
$transactionSave->addObject($invoice3)->addObject($order3)->save();
