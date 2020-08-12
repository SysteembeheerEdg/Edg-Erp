<?php
/**
 * ArticleInfoTest
 *
 * @copyright Copyright © 2017 Bold Commerce BV. All rights reserved.
 * @author    dev@boldcommerce.nl
 */

namespace Bold\PIM\Test\Integration\Cron\Api;

use Magento\TestFramework\Helper\Bootstrap;


/**
 * Class ArticleInfoTest
 * @package Bold\PIM\Test\Integration\Cron\Api
 *
 * @magentoDbIsolation enabled
 */
class ArticleInfoTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $soap;

    /**
     * @var \Bold\PIM\Cron\API\ArticleInfo
     */
    protected $subject;

    public static function loadFixtureProducts()
    {
        require __DIR__ . '/../../_files/api/simple_products.php';
    }

    /**
     *
     * @magentoDataFixture loadFixtureProducts
     * @magentoConfigFixture current_store tax/defaults/country NL
     * @magentoConfigFixture current_store bold_orderexim/settings/articleinfo_import_enabled 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_stock 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_product_status 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_pricetiers 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_tax_classes 0
     */
    public function testProductStatusSyncDisabled()
    {
        $result = new \stdClass;
        $result->result = null;
        $result->v_info = file_get_contents(__DIR__ . '/../../_files/api/articleinfoResponse.xml');

        $this->soap->expects($this->any())
            ->method('articleinfo')
            ->willReturn($result);

        $this->subject->execute();

        /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepo */
        $productRepo = $this->objectManager->create('\Magento\Catalog\Api\ProductRepositoryInterface');

        for ($i = 1; $i <= 4; $i++) {
            $var = 'product' . $i;
            $$var = $productRepo->get('simple-' . $i);
        }

        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED,
            $product1->getStatus());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
            $product2->getStatus());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED,
            $product3->getStatus());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
            $product4->getStatus());
    }

    /**
     *
     * @magentoDataFixture loadFixtureProducts
     * @magentoConfigFixture current_store tax/defaults/country NL
     * @magentoConfigFixture current_store bold_orderexim/settings/articleinfo_import_enabled 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_product_status 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_stock 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_pricetiers 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_tax_classes 0
     */
    public function testProductStatusSyncEnabled()
    {
        $result = new \stdClass;
        $result->result = null;
        $result->v_info = file_get_contents(__DIR__ . '/../../_files/api/articleinfoResponse.xml');

        $this->soap->expects($this->any())
            ->method('articleinfo')
            ->willReturn($result);

        $this->subject->execute();

        /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepo */
        $productRepo = $this->objectManager->get('\Magento\Catalog\Api\ProductRepositoryInterface');

        for ($i = 1; $i <= 4; $i++) {
            $var = 'product' . $i;
            $$var = $productRepo->get('simple-' . $i);
        }

        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
            $product1->getStatus());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
            $product2->getStatus());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
            $product3->getStatus());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED,
            $product4->getStatus());

    }

    /**
     *
     * @magentoDataFixture loadFixtureProducts
     * @magentoConfigFixture current_store tax/defaults/country NL
     * @magentoConfigFixture current_store bold_orderexim/settings/articleinfo_import_enabled 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_product_status 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_stock 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_pricetiers 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_tax_classes 0
     */
    public function testStockSyncDisabled()
    {
        $objectManager = $this->objectManager;

        $result = new \stdClass;
        $result->result = null;
        $result->v_info = file_get_contents(__DIR__ . '/../../_files/api/articleinfoResponse.xml');

        $this->soap->expects($this->any())
            ->method('articleinfo')
            ->willReturn($result);


        /** @var \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry */
        $stockRegistry = $objectManager->create('\Magento\CatalogInventory\Api\StockRegistryInterface');

        $this->subject->execute();

        for ($i = 1; $i <= 4; $i++) {
            $var = 'stockitem' . $i;
            $$var = $stockRegistry->getStockItemBySku('simple-' . $i);
        }

        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(100, $stockitem1->getQty());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(100, $stockitem2->getQty());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(100, $stockitem3->getQty());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(100, $stockitem4->getQty());
    }

    /**
     *
     * @magentoDataFixture loadFixtureProducts
     * @magentoConfigFixture current_store tax/defaults/country NL
     * @magentoConfigFixture current_store bold_orderexim/settings/articleinfo_import_enabled 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_product_status 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_stock 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_pricetiers 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_tax_classes 0
     */
    public function testStockSyncEnabled()
    {
        $objectManager = $this->objectManager;

        $result = new \stdClass;
        $result->result = null;
        $result->v_info = file_get_contents(__DIR__ . '/../../_files/api/articleinfoResponse.xml');

        $this->soap->expects($this->any())
            ->method('articleinfo')
            ->willReturn($result);


        /** @var \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry */
        $stockRegistry = $objectManager->create('\Magento\CatalogInventory\Api\StockRegistryInterface');

        $this->subject->execute();

        for ($i = 1; $i <= 4; $i++) {
            $var = 'stockitem' . $i;
            $$var = $stockRegistry->getStockItemBySku('simple-' . $i);
        }

        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(6, $stockitem1->getQty());
        $this->assertTrue($stockitem1->getIsInStock());

        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(-3, $stockitem2->getQty());
        $this->assertTrue($stockitem2->getIsInStock());

        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(-5, $stockitem3->getQty());
        $this->assertFalse($stockitem3->getIsInStock());

        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(1, $stockitem4->getQty());
        $this->assertTrue($stockitem4->getIsInStock());
    }

    /**
     *
     * @magentoDataFixture loadFixtureProducts
     * @magentoConfigFixture current_store tax/defaults/country NL
     * @magentoConfigFixture current_store bold_orderexim/settings/articleinfo_import_enabled 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_product_status 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_stock 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_pricetiers 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_tax_classes 0
     */
    public function testTierPriceSyncDisabled()
    {
        $result = new \stdClass;
        $result->result = null;
        $result->v_info = file_get_contents(__DIR__ . '/../../_files/api/articleinfoResponse.xml');

        $this->soap->expects($this->any())
            ->method('articleinfo')
            ->willReturn($result);

        $this->subject->execute();

        /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepo */
        $productRepo = $this->objectManager->get('\Magento\Catalog\Api\ProductRepositoryInterface');

        for ($i = 1; $i <= 4; $i++) {
            $var = 'product' . $i;
            $$var = $productRepo->get('simple-' . $i);
        }

        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEmpty($product1->getTierPrice());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEmpty($product2->getTierPrice());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEmpty($product3->getTierPrice());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(3, count($product4->getTierPrice()));
        $this->assertEquals(6, $product4->getTierPrice()[0]['price']);
        $this->assertEquals(2, $product4->getTierPrice()[0]['price_qty']);
        $this->assertEquals(4, $product4->getTierPrice()[1]['price']);
        $this->assertEquals(3, $product4->getTierPrice()[1]['price_qty']);
    }

    /**
     *
     * @magentoDataFixture loadFixtureProducts
     * @magentoConfigFixture current_store tax/defaults/country NL
     * @magentoConfigFixture current_store bold_orderexim/settings/articleinfo_import_enabled 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_product_status 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_stock 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_pricetiers 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_tax_classes 0
     */
    public function testTierPriceSyncEnabled()
    {
        $result = new \stdClass;
        $result->result = null;
        $result->v_info = file_get_contents(__DIR__ . '/../../_files/api/articleinfoResponse.xml');

        $this->soap->expects($this->any())
            ->method('articleinfo')
            ->willReturn($result);

        $this->subject->execute();

        /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepo */
        $productRepo = $this->objectManager->get('\Magento\Catalog\Api\ProductRepositoryInterface');

        for ($i = 1; $i <= 4; $i++) {
            $var = 'product' . $i;
            $$var = $productRepo->get('simple-' . $i, false, null, true);
        }

        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(3, count($product1->getTierPrice()), 'Expected 3 tierprices for simple-1');
        $this->assertEquals(7.99, $product1->getTierPrice()[0]['price'],
            'Expected the first tier price of simple-1 to cost 7.99');
        $this->assertEquals(1, $product1->getTierPrice()[0]['price_qty'],
            'Expected the first tier price of simple-1 to have qty 1');
        $this->assertEquals(7.499, $product1->getTierPrice()[1]['price'],
            'Expected the second tier price of simple-1 to cost 7.499');
        $this->assertEquals(5, $product1->getTierPrice()[1]['price_qty'],
            'Expected the second tier price of simple-1 to have qty 5');
        $this->assertEquals(6.499, $product1->getTierPrice()[2]['price'],
            'Expected the third tier price of simple-1 to cost 6.499');
        $this->assertEquals(12, $product1->getTierPrice()[2]['price_qty'],
            'Expected the third tier price of simple-1 to have qty 12');

        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(3, count($product2->getTierPrice()), 'Expected 3 tierprices for simple-2');
        $this->assertEquals(7.99, $product2->getTierPrice()[0]['price'],
            'Expected the first tier price of simple-2 to cost 7.99');
        $this->assertEquals(1, $product2->getTierPrice()[0]['price_qty'],
            'Expected the first tier price of simple-2 to have qty 1');
        $this->assertEquals(7.299, $product2->getTierPrice()[1]['price'],
            'Expected the second tier price of simple-2 to cost 7.299');
        $this->assertEquals(6, $product2->getTierPrice()[1]['price_qty'],
            'Expected the second tier price of simple-2 to have qty 6');
        $this->assertEquals(6.299, $product2->getTierPrice()[2]['price'],
            'Expected the third tier price of simple-2 to cost 6.299');
        $this->assertEquals(13, $product2->getTierPrice()[2]['price_qty'],
            'Expected the third tier price of simple-2 to have qty 13');

        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(3, count($product3->getTierPrice()), 'Expected 3 tierprices for simple-3');
        $this->assertEquals(7.99, $product3->getTierPrice()[0]['price'],
            'Expected the first tier price of simple-3 to cost 7.99');
        $this->assertEquals(1, $product3->getTierPrice()[0]['price_qty'],
            'Expected the first tier price of simple-3 to have qty 1');
        $this->assertEquals(7.599, $product3->getTierPrice()[1]['price'],
            'Expected the second tier price of simple-3 to cost 7.599');
        $this->assertEquals(4, $product3->getTierPrice()[1]['price_qty'],
            'Expected the second tier price of simple-3 to have qty 4');
        $this->assertEquals(5.899, $product3->getTierPrice()[2]['price'],
            'Expected the third tier price of simple-3 to cost 5.899');
        $this->assertEquals(14, $product3->getTierPrice()[2]['price_qty'],
            'Expected the third tier price of simple-3 to have qty 14');

        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(3, count($product4->getTierPrice()), 'Expected 3 tierprices for simple-4');
        $this->assertEquals(7.99, $product4->getTierPrice()[0]['price'],
            'Expected the first tier price of simple-4 to cost 7.99');
        $this->assertEquals(1, $product4->getTierPrice()[0]['price_qty'],
            'Expected the first tier price of simple-4 to have qty 1');
        $this->assertEquals(6.499, $product4->getTierPrice()[1]['price'],
            'Expected the second tier price of simple-4 to cost 6.499');
        $this->assertEquals(7, $product4->getTierPrice()[1]['price_qty'],
            'Expected the second tier price of simple-4 to have qty 7');
        $this->assertEquals(5.499, $product4->getTierPrice()[2]['price'],
            'Expected the third tier price of simple-4 to cost 5.499');
        $this->assertEquals(17, $product4->getTierPrice()[2]['price_qty'],
            'Expected the third tier price of simple-4 to have qty 17');
    }

    /**
     *
     * @magentoDataFixture loadFixtureProducts
     * @magentoConfigFixture current_store tax/defaults/country NL
     * @magentoConfigFixture current_store bold_orderexim/settings/articleinfo_import_enabled 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_product_status 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_stock 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_pricetiers 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_tax_classes 0
     */
    public function testTaxClassSyncDisabled()
    {
        $result = new \stdClass;
        $result->result = null;
        $result->v_info = file_get_contents(__DIR__ . '/../../_files/api/articleinfoResponse.xml');

        $this->soap->expects($this->any())
            ->method('articleinfo')
            ->willReturn($result);

        $this->subject->execute();

        /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepo */
        $productRepo = $this->objectManager->get('\Magento\Catalog\Api\ProductRepositoryInterface');

        for ($i = 1; $i <= 4; $i++) {
            $var = 'product' . $i;
            $$var = $productRepo->get('simple-' . $i, false, null, true);
        }

        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(0, $product1->getTaxClassId());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(0, $product2->getTaxClassId());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(0, $product3->getTaxClassId());
        /** @noinspection PhpUndefinedVariableInspection */
        $this->assertEquals(0, $product4->getTaxClassId());

    }

    /**
     *
     * @magentoDataFixture loadFixtureProducts
     * @magentoConfigFixture current_store tax/defaults/country NL
     * @magentoConfigFixture current_store bold_orderexim/settings/articleinfo_import_enabled 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_product_status 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_stock 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_pricetiers 0
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_tax_classes 1
     */
    public function testTaxClassSyncEnabled()
    {
        $result = new \stdClass;
        $result->result = null;
        $result->v_info = file_get_contents(__DIR__ . '/../../_files/api/articleinfoResponse.xml');

        $this->soap->expects($this->any())
            ->method('articleinfo')
            ->willReturn($result);

        $this->subject->execute();

        /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepo */
        $productRepo = $this->objectManager->get('\Magento\Catalog\Api\ProductRepositoryInterface');

        for ($i = 1; $i <= 4; $i++) {
            $var = 'product' . $i;
            $$var = $productRepo->get('simple-' . $i, false, null, true);
        }

        /** @var \Magento\Tax\Model\Calculation $tax */
        $tax = $this->objectManager->get(\Magento\Tax\Model\Calculation::class);
        $request = $tax->getRateRequest();

        /** @noinspection PhpUndefinedVariableInspection */
        $request->setProductClassId($product1->getTaxClassId());
        $this->assertEquals(21, $tax->getRate($request));

        /** @noinspection PhpUndefinedVariableInspection */
        $request->setProductClassId($product2->getTaxClassId());
        $this->assertEquals(21, $tax->getRate($request));

        /** @noinspection PhpUndefinedVariableInspection */
        $request->setProductClassId($product3->getTaxClassId());
        $this->assertEquals(6, $tax->getRate($request));

        /** @noinspection PhpUndefinedVariableInspection */
        $request->setProductClassId($product4->getTaxClassId());
        $this->assertEquals(21, $tax->getRate($request));
    }

    /**
     *
     * @magentoDataFixture loadFixtureProducts
     * @magentoConfigFixture current_store tax/defaults/country NL
     * @magentoConfigFixture current_store bold_orderexim/settings/articleinfo_import_enabled 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_product_status 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_stock 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_pricetiers 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_tax_classes 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_field_price 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_field_backorder_text 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_field_name 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_field_isbn 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_field_weight 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_field_sku 1
     * @magentoConfigFixture current_store bold_orderexim/articleinfo/sync_field_bold_pim_articletype 1
     */
    public function testAllSyncEnabled()
    {
        $result = new \stdClass;
        $result->result = null;
        $result->v_info = file_get_contents(__DIR__ . '/../../_files/api/articleinfoResponse.xml');

        $this->soap->expects($this->any())
            ->method('articleinfo')
            ->willReturn($result);

        $this->subject->execute();

        /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepo */
        $productRepo = $this->objectManager->get('\Magento\Catalog\Api\ProductRepositoryInterface');

        /** @var \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry */
        $stockRegistry = $this->objectManager->create('\Magento\CatalogInventory\Api\StockRegistryInterface');
        $stockItem2 = $stockRegistry->getStockItemBySku('simple-2');
        $stockItem4 = $stockRegistry->getStockItemBySku('simple-4');

        $product1 = $productRepo->get('simple-1', false, null, true);
        $product4 = $productRepo->get('simple-4', false, null, true);

        /** @var \Magento\Tax\Model\Calculation $tax */
        $tax = $this->objectManager->get(\Magento\Tax\Model\Calculation::class);
        $request = $tax->getRateRequest();

        /** @noinspection PhpUndefinedVariableInspection */
        $request->setProductClassId($product1->getTaxClassId());
        $this->assertEquals(21, $tax->getRate($request));

        $this->assertEquals(3, count($product4->getTierPrice()), 'Expected 3 tierprices for simple-4');
        $this->assertEquals(7.99, $product4->getTierPrice()[0]['price'],
            'Expected the first tier price of simple-4 to cost 7.99');
        $this->assertEquals(1, $product4->getTierPrice()[0]['price_qty'],
            'Expected the first tier price of simple-4 to have qty 1');
        $this->assertEquals(6.499, $product4->getTierPrice()[1]['price'],
            'Expected the second tier price of simple-4 to cost 6.499');
        $this->assertEquals(7, $product4->getTierPrice()[1]['price_qty'],
            'Expected the second tier price of simple-4 to have qty 7');
        $this->assertEquals(5.499, $product4->getTierPrice()[2]['price'],
            'Expected the third tier price of simple-4 to cost 5.499');
        $this->assertEquals(17, $product4->getTierPrice()[2]['price_qty'],
            'Expected the third tier price of simple-4 to have qty 17');

        $this->assertEquals(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
            $product1->getStatus());
        $this->assertEquals(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED,
            $product4->getStatus());

        $this->assertEquals(7.999, $product1->getPrice());
        $this->assertEquals(0, $product1->getWeight());

        $this->assertTrue($stockItem2->getIsInStock());
        $this->assertTrue($stockItem4->getIsInStock());

        $this->assertNotNull($product4->getImage(), 'simple-4 was expected to have an image set');
    }

    protected function setUp()
    {
        $soapMock = $this->getMockFromWsdl(BP . '/vendor/boldcommerce/pim-api-service-edg/tests/_files/edg.wsdl');
        $client = new \Bold\PIMService\Client();
        $client->setSoapClient($soapMock);

        $this->soap = $soapMock;
        $this->objectManager = Bootstrap::getObjectManager();

        $helper = $this->getMockBuilder('\Bold\PIM\Helper\Data')
            ->setMethods(['getSoapClient'])
            ->setConstructorArgs(
                [
                    'context' => $this->objectManager->get('\Magento\Framework\App\Helper\Context'),
                    'productRepository' => $this->objectManager->get('\Magento\Catalog\Api\ProductRepositoryInterface'),
                    'criteriaBuilder' => $this->objectManager->get('\Magento\Framework\Api\SearchCriteriaBuilder'),
                    'taxCalculation' => $this->objectManager->get('\Magento\Tax\Model\Calculation'),
                    'logger' => $this->objectManager->get('\Bold\PIM\Logger\PimLogger')
                ]
            )
            ->getMock();

        $helper->expects($this->any())
            ->method('getSoapClient')
            ->willReturn($client);


        /** @var \Bold\PIM\Cron\API\StockMutations $subject */
        $this->subject = $this->objectManager->create('\Bold\PIM\Cron\API\ArticleInfo',
            [
                'helper' => $helper
            ]
        );
    }
}
