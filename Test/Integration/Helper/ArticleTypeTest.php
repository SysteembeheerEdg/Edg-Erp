<?php
namespace Edg\Erp\Test\Integration\Helper;

use Magento\TestFramework\Helper\Bootstrap;

/**
 * Class ArticleType
 * @package Edg\Erp\Test\Integration\Helper
 *
 * @magentoDbIsolation enabled
 *
 */
class ArticleTypeTest extends \PHPUnit_Framework_TestCase
{
    public static function loadFixtureNoNonShippables()
    {
        require __DIR__ . '/../_files/order_without_nonshippables.php';
    }

    public static function loadFixturePartialNonShippable()
    {
        require __DIR__ . '/../_files/order_multiple_products.php';
    }

    public static function loadFixtureAllNonShippable()
    {
        require __DIR__ . '/../_files/order.php';
    }

    /**
     * @magentoDataFixture loadFixtureAllNonShippable
     */
    public function testAutoshipNonShippableItemsByOrder()
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $objectManager->create('\Magento\Sales\Model\Order');
        $order->loadByIncrementId('100000001');

        /** @var \Edg\Erp\Helper\ArticleType $testobject */
        $testobject = $objectManager->create('\Edg\Erp\Helper\ArticleType');

        $result = $testobject->autoshipNonShippableItemsByOrder($order);

        self::assertEquals(1, $result);

        self::assertEquals(1, $order->getShipmentsCollection()->count());
    }

    /**
     * @magentoDataFixture loadFixtureNoNonShippables
     */
    public function testAutoshipNonShippableItemsByOrderUsingShippables()
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $objectManager->create('\Magento\Sales\Model\Order');
        $order->loadByIncrementId('100000001');

        /** @var \Edg\Erp\Helper\ArticleType $testobject */
        $testobject = $objectManager->create('\Edg\Erp\Helper\ArticleType');

        $result = $testobject->autoshipNonShippableItemsByOrder($order);

        self::assertEquals(0, $result);

        self::assertEquals(0, $order->getShipmentsCollection()->count());
    }

    /**
     * @magentoDataFixture loadFixturePartialNonShippable
     */
    public function testAutoshipNonShippableItemsByOrderHavingBoth()
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $objectManager->create('\Magento\Sales\Model\Order');
        $order->loadByIncrementId('100000001');

        /** @var \Edg\Erp\Helper\ArticleType $testobject */
        $testobject = $objectManager->create('\Edg\Erp\Helper\ArticleType');

        $result = $testobject->autoshipNonShippableItemsByOrder($order);

        self::assertEquals(2, $result);

        self::assertEquals(1, $order->getShipmentsCollection()->count());
    }
}