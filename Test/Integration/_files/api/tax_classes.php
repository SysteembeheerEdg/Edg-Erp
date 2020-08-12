<?php
$customerTaxId = $objectManager->get(
    'Magento\Customer\Api\GroupManagementInterface'
)->getNotLoggedInGroup()->getTaxClassId();

$productTaxClass1 = $objectManager->create(
    'Magento\Tax\Model\ClassModel'
)->setClassName(
    'Products BTW hoog'
)->setClassType(
    \Magento\Tax\Model\ClassModel::TAX_CLASS_TYPE_PRODUCT
)->save();

$productTaxClass2 = $objectManager->create(
    'Magento\Tax\Model\ClassModel'
)->setClassName(
    'Products BTW laag'
)->setClassType(
    \Magento\Tax\Model\ClassModel::TAX_CLASS_TYPE_PRODUCT
)->save();

$taxRate = [
    'tax_country_id' => 'NL',
    'tax_region_id' => '0',
    'tax_postcode' => '*',
    'code' => '21% BTW',
    'rate' => '21',
];

$taxRate2 = [
    'tax_country_id' => 'NL',
    'tax_region_id' => '0',
    'tax_postcode' => '*',
    'code' => '6% BTW',
    'rate' => '6',
];

$rate = $objectManager->create('Magento\Tax\Model\Calculation\Rate')->setData($taxRate)->save();
$rate2 = $objectManager->create('Magento\Tax\Model\Calculation\Rate')->setData($taxRate2)->save();

/* setup 21% btw */

/** @var Magento\Framework\Registry $registry */
$registry = $objectManager->get('Magento\Framework\Registry');
$registry->unregister('_fixture/Magento_Tax_Model_Calculation_Rate');
$registry->register('_fixture/Magento_Tax_Model_Calculation_Rate', $rate);

$ruleData = [
    'code' => 'NL - BTW 21',
    'priority' => '0',
    'position' => '0',
    'customer_tax_class_ids' => [$customerTaxId],
    'product_tax_class_ids' => [$productTaxClass1->getId()],
    'tax_rate_ids' => [$rate->getId()],
];

$taxRule = $objectManager->create('Magento\Tax\Model\Calculation\Rule')->setData($ruleData)->save();

$registry->unregister('_fixture/Magento_Tax_Model_Calculation_Rule');
$registry->register('_fixture/Magento_Tax_Model_Calculation_Rule', $taxRule);

$ruleData['code'] .= ' Duplicate';

$objectManager->create('Magento\Tax\Model\Calculation\Rule')->setData($ruleData)->save();

/* setup 6% BTW */

$registry->unregister('_fixture/Magento_Tax_Model_Calculation_Rate');
$registry->register('_fixture/Magento_Tax_Model_Calculation_Rate', $rate2);

$ruleData2 = [
    'code' => 'NL - BTW 6',
    'priority' => '0',
    'position' => '0',
    'customer_tax_class_ids' => [$customerTaxId],
    'product_tax_class_ids' => [$productTaxClass2->getId()],
    'tax_rate_ids' => [$rate2->getId()],
];

$taxRule2 = $objectManager->create('Magento\Tax\Model\Calculation\Rule')->setData($ruleData2)->save();

$registry->unregister('_fixture/Magento_Tax_Model_Calculation_Rule');
$registry->register('_fixture/Magento_Tax_Model_Calculation_Rule', $taxRule2);

$ruleData2['code'] .= ' Duplicate';

$objectManager->create('Magento\Tax\Model\Calculation\Rule')->setData($ruleData2)->save();

