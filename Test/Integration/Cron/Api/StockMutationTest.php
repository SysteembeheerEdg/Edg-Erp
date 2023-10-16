<?php

namespace Edg\Erp\Test\Integration\Cron\Api;


use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class StockMutationTest
 * @package Edg\Erp\Test\Integration\Cron\Api
 *
 * @magentoDbIsolation enabled
 */
class StockMutationTest extends \PHPUnit_Framework_TestCase
{
    public static function loadFixtureProducts()
    {
        require __DIR__ . '/../../_files/multiple_products.php';
    }

    /**
     * @magentoDataFixture loadFixtureProducts
     * @magentoConfigFixture default/bold_orderexim/settings/stockmutations_import_enabled 1
     */
    public function testStockmutations()
    {
        $objectManager = Bootstrap::getObjectManager();

        $soapMock = $this->getMockFromWsdl(BP . '/vendor/edg/module-erp-service/tests/_files/edg.wsdl');
        $client = new \Edg\ErpService\Client();
        $client->setSoapClient($soapMock);

        $result = new \stdClass;
        $result->v_status = 'OK';
        $result->result = null;
        $result->v_stockmutations = "<articles><environment>dummy</environment><article><sku>simple-1</sku><stock>5</stock></article></articles>";

        $result2 = new \stdClass;
        $result2->v_status = 'OK';
        $result2->result = null;
        $result2->v_stockmutations = "<articles><environment>dummy</environment><article><sku>simple-2</sku><stock>2</stock></article><article><sku>simple-4</sku><stock>4</stock></article></articles>";

        $result3 = new \stdClass;
        $result3->v_status = \Edg\ErpService\Sync\Pull\StockMutations::NO_MUTATIONS;
        $result3->result = null;
        $result3->v_stockmutations = "";

        $soapMock->expects($this->any())
            ->method('stockmutations2')
            ->willReturnOnConsecutiveCalls($result, $result2, $result3);


        $helper = $this->getMockBuilder('\Edg\Erp\Helper\Data')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $helper->expects($this->any())
            ->method('getSoapClient')
            ->willReturn($client);

        $helper->expects($this->any())
            ->method('isStockMutationsEnabled')
            ->willReturn(true);

        $helper->expects($this->any())
            ->method('getEnvironmentTag')
            ->willReturn('dummy');

        /** @var \Edg\Erp\Cron\API\StockMutations $subject */
        $subject = $objectManager->create('\Edg\Erp\Cron\API\StockMutations',
            [
                'helper' => $helper
            ]
        );

        /** @var \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry */
        $stockRegistry = $objectManager->create('\Magento\CatalogInventory\Api\StockRegistryInterface');

        $subject->execute();

        $stockitem = $stockRegistry->getStockItemBySku('simple-1');
        $this->assertEquals(5, $stockitem->getQty());

        $stockitem = $stockRegistry->getStockItemBySku('simple-2');
        $this->assertEquals(2, $stockitem->getQty());

        $stockitem = $stockRegistry->getStockItemBySku('simple-4');
        $this->assertEquals(4, $stockitem->getQty());
    }
}