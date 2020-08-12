<?php
/**
 * OrderExportTest
 *
 * @copyright Copyright © 2017 Bold Commerce BV. All rights reserved.
 * @author    dev@boldcommerce.nl
 */

namespace Bold\PIM\Test\Integration\Cron\Api;

use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class OrderExportTest
 * @package Bold\PIM\Test\Integration\Cron\Api
 *
 * @magentoDbIsolation enabled
 */
class OrderExportTest extends \PHPUnit_Framework_TestCase
{
    protected $objectManager;

    public static function loadFixtures()
    {
        require __DIR__ . '/../../_files/api/invoice.php';
    }

    /**
     * @magentoDataFixture loadFixtures
     * @magentoConfigFixture current_store bold_orderexim/settings/order_export_enabled 1
     * @magentoConfigFixture current_store bold_orderexim/settings/export_order_statuses processing
     * @magentoConfigFixture current_store bold_orderexim/settings/export_order_type magento_order
     * @magentoConfigFixture current_store bold_orderexim/settings/environment_tag test
     */
    public function testExport()
    {
        $this->objectManager = Bootstrap::getObjectManager();

        $soapMock = $this->getMockFromWsdl(BP . '/vendor/boldcommerce/pim-api-service-edg/tests/_files/edg.wsdl');
        $client = new \Bold\PIMService\Client();
        $client->setSoapClient($soapMock);

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


        $result = new \stdClass;
        $result->result = null;
        $result->v_STATUS = "OK";

        $soapMock->expects($this->atLeastOnce())
            ->method('uploadOrders')
            ->willReturn($result)
            ->with($this->callback(function ($input) {
                if(isset($input['v_XML'])){
                    $order = simplexml_load_string($input['v_XML']);
                    if((string) $order->incrementId === '000000002'){
                        $this->assertTrue(isset($order->customer));
                        $this->assertEquals(1, (string) $order->customer->id);
                    }else{
                        $this->assertFalse(isset($order->customer));
                    }
                }
                return true;
            }));

        /** @var \Bold\PIM\Cron\API\OrderExport $subject */
        $subject = $this->objectManager->create('\Bold\PIM\Cron\API\OrderExport',
            [
                'helper' => $helper
            ]
        );
        $subject->execute();

        $orderRepo = $this->objectManager->get(\Magento\Sales\Model\OrderFactory::class);

        $order1 = $orderRepo->create()->loadByIncrementId('100000002');
        $order2 = $orderRepo->create()->loadByIncrementId('100000001');

        $this->assertEquals(1, $order1->getPimIsExported());
        $this->assertEquals(1, $order2->getPimIsExported());

        /** @var \Magento\Sales\Model\Order $order3 */
        $order3 = $orderRepo->create()->loadByIncrementId('000000002');
        $this->assertEquals(1, $order3->getPimIsExported());
        $this->assertEquals('processing', $order3->getState());
        $this->assertEquals('processing', $order3->getStatus());
    }
}