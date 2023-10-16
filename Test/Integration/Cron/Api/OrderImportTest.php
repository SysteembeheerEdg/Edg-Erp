<?php

namespace Edg\Erp\Test\Integration\Cron\Api;

use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class OrderImportTest
 * @package Edg\Erp\Test\Integration\Cron\Api
 *
 * @magentoDbIsolation enabled
 */
class OrderImportTest extends \PHPUnit_Framework_TestCase
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
     * @var \Edg\Erp\Cron\API\OrderStatusImport
     */
    protected $subject;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $mailSender;

    public static function loadFixtures()
    {
        require __DIR__ . '/../../_files/api/invoice.php';
    }

    /**
     * @magentoDataFixture loadFixtures
     * @magentoConfigFixture current_store bold_orderexim/settings/order_import_enabled 1
     * @magentoConfigFixture current_store bold_orderexim/settings/environment_tag test
     */
    public function testImport()
    {

        $this->subject->execute();

        /** @var \Magento\Sales\Model\OrderFactory $orderRepo */
        $orderRepo = $this->objectManager->get(\Magento\Sales\Model\OrderFactory::class);

        $order1 = $orderRepo->create()->loadByIncrementId('100000002');
        $order2 = $orderRepo->create()->loadByIncrementId('100000001');
        $order3 = $orderRepo->create()->loadByIncrementId('999999999');
        $order4 = $orderRepo->create()->loadByIncrementId('000000002');

        $this->assertNull($order3->getId());
        $this->assertNotNull($order2->getId());
        $this->assertNotNull($order1->getId());

        $shipments1 = $order1->getShipmentsCollection();
        $shipments2 = $order2->getShipmentsCollection();

        $this->assertEquals(1, $shipments1->count());
        $this->assertEquals(1, $shipments2->count());
        /** @var \Magento\Sales\Model\Order\Shipment $shipment1 */
        $shipment1 = $shipments1->getFirstItem();
        /** @var \Magento\Sales\Model\Order\Shipment $shipment2 */
        $shipment2 = $shipments2->getFirstItem();

        /** @var \Magento\Sales\Model\Order\Shipment\Item[] $shipment1Items */
        $shipment1Items = $shipment1->getAllItems();
        /** @var \Magento\Sales\Model\Order\Shipment\Track[] $shipment1Tracks */
        $shipment1Tracks = $shipment1->getAllTracks();

        /** @var \Magento\Sales\Model\Order\Shipment\Item[] $shipment2Items */
        $shipment2Items = $shipment2->getAllItems();
        /** @var \Magento\Sales\Model\Order\Shipment\Track[] $shipment2Tracks */
        $shipment2Tracks = $shipment2->getAllTracks();

        $this->assertEquals(1, count($shipment1Items));
        $this->assertEquals(2, count($shipment2Items));
        $this->assertEquals(1, count($shipment1Tracks));
        $this->assertEquals(0, count($shipment2Tracks));

        $this->assertEquals('simple-1', $shipment2Items[0]->getSku());
        $this->assertEquals('simple-4', $shipment2Items[1]->getSku());
        $this->assertEquals(2, $shipment2Items[0]->getQty());
        $this->assertEquals(5, $shipment2Items[1]->getQty());

        /** @var \Magento\Sales\Model\Order\Item $orderItem */
        $orderItem = $order1->getItemsCollection()->getItemByColumnValue('sku', 'simple-2');

        $this->assertEquals(3, $orderItem->getQtyInvoiced());
        $this->assertEquals(2, $orderItem->getQtyShipped());

        $this->assertEquals('3SYSGZ195795201', $shipment1Tracks[0]->getTrackNumber());

        $this->assertEquals('processing', $order1->getStatus());
        $this->assertEquals('complete', $order2->getStatus());
        $this->assertEquals('complete', $order4->getStatus());
    }

    /**
     * @magentoDataFixture loadFixtures
     * @magentoConfigFixture current_store bold_orderexim/settings/order_import_enabled 1
     * @magentoConfigFixture current_store bold_orderexim/settings/order_import_send_email_after_shipping 1
     * @magentoConfigFixture current_store bold_orderexim/settings/environment_tag test
     */
    public function testEmailBarcode()
    {
        $this->mailSender->expects($this->atLeastOnce())
            ->method('send')
            ->willReturn(true)
            ->with($this->callback(function ($shipment) {

                if ($shipment->getOrder()->getIncrementId() == '100000002') {
                    $this->assertEquals(1, count($shipment->getAllTracks()),
                        'Expected 1 tracking code in shipment for order #100000002');
                } else {
                    $this->assertEquals(0, count($shipment->getAllTracks()),
                        'Expected 0 tracking codes in shipments for orders other than #100000002');
                }
                return true;
            }));
        $this->subject->execute();
    }

    protected function setUp()
    {
        $soapMock = $this->getMockFromWsdl(BP . '/vendor/edg/module-erp-service/tests/_files/edg.wsdl');
        $client = new \Edg\ErpService\Client();
        $client->setSoapClient($soapMock);

        $this->soap = $soapMock;
        $this->objectManager = Bootstrap::getObjectManager();

        $helper = $this->getMockBuilder('\Edg\Erp\Helper\Data')
            ->setMethods(['getSoapClient'])
            ->setConstructorArgs(
                [
                    'context' => $this->objectManager->get('\Magento\Framework\App\Helper\Context'),
                    'productRepository' => $this->objectManager->get('\Magento\Catalog\Api\ProductRepositoryInterface'),
                    'criteriaBuilder' => $this->objectManager->get('\Magento\Framework\Api\SearchCriteriaBuilder'),
                    'taxCalculation' => $this->objectManager->get('\Magento\Tax\Model\Calculation'),
                    'logger' => $this->objectManager->get('\Edg\Erp\Logger\PimLogger')
                ]
            )
            ->getMock();

        $helper->expects($this->any())
            ->method('getSoapClient')
            ->willReturn($client);

        $this->mailSender = $this->getMockBuilder(\Magento\Sales\Model\Order\Email\Sender\ShipmentSender::class)
            ->setMethods([])
            ->disableOriginalConstructor()
            ->getMock();


        /** @var \Edg\Erp\Cron\API\StockMutations $subject */
        $this->subject = $this->objectManager->create('\Edg\Erp\Cron\API\OrderStatusImport',
            [
                'helper' => $helper,
                'sender' => $this->mailSender
            ]
        );

        $result = new \stdClass;
        $result->result = null;
        $result->v_status = 'OK';
        $result->v_orders = file_get_contents(__DIR__ . '/../../_files/api/orderImportResponse.xml');

        $this->soap->expects($this->atLeastOnce())
            ->method('orderstatus2')
            ->willReturn($result);
    }
}