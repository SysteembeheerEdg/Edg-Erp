<?php

namespace Edg\Erp\Cron\API;

use Edg\Erp\Helper\Data;
use Edg\ErpService\DataModel\OrderStatus;
use Exception;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Logger\Monolog;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManager;
use Monolog\Logger;

class OrderStatusImport extends AbstractCron
{
    const TRACKING_TITLE = 'Track & Trace';
    const CARRIER_CODE = 'PostNL';

    /**
     * @var OrderFactory
     */
    protected OrderFactory $orderFactory;

    /**
     * @var ShipmentFactory
     */
    protected ShipmentFactory $shipmentFactory;

    /**
     * @var Transaction
     */
    protected Transaction $transaction;

    /**
     * @var ShipmentSender
     */
    protected ShipmentSender $shipmentMailer;

    /**
     * @var Monolog
     */
    protected Monolog $monolog;

    /**
     * @var TransportBuilder
     */
    protected TransportBuilder $transportBuilder;

    /**
     * @param Data $helper
     * @param DirectoryList $directoryList
     * @param Monolog $monolog
     * @param ConfigInterface $config
     * @param TransportBuilder $transportBuilder
     * @param StoreManager $storeManager
     * @param OrderFactory $orderFactory
     * @param ShipmentFactory $shipmentFactory
     * @param Transaction $transaction
     * @param ShipmentSender $sender
     * @param array $settings
     * @throws FileSystemException
     */
    public function __construct(
        Data $helper,
        DirectoryList $directoryList,
        Monolog $monolog,
        ConfigInterface $config,
        TransportBuilder $transportBuilder,
        StoreManager $storeManager,
        OrderFactory $orderFactory,
        ShipmentFactory $shipmentFactory,
        Transaction $transaction,
        ShipmentSender $sender,
        array $settings = []
    ) {
        parent::__construct($helper, $directoryList, $monolog, $config, $transportBuilder, $storeManager, $settings);
        $this->orderFactory = $orderFactory;
        $this->shipmentFactory = $shipmentFactory;
        $this->transaction = $transaction;
        $this->shipmentMailer = $sender;
    }

    public function execute()
    {
        if ($this->helper->isPullOrdersEnabled()) {
            $this->prepareImport();
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function prepareImport()
    {
        $date = date("Y_m_d");
        $stream = $this->addLogStreamToServiceLogger($this->_importDir . DIRECTORY_SEPARATOR . "log_{$date}.log");
        $this->serviceLog($stream, 'Start import');

        $shippedStatus = $this->helper->getOrderImportStatusAfterShipping();
        $environment = $this->helper->getEnvironmentTag();

        $client = $this->helper->getSoapClient();
        $client->setLogger($this->helper->getPimLogger(), true);
        $responses = $client->pullOrderUpdates($environment);

        foreach ($responses as $response) {
            if (!$response->hasOrders()) {
                continue;
            }
            $orders = $response->getOrders();
            foreach ($orders as $order) {
                $this->serviceLog($stream,'starting import for order with order number: #' . $order->getOrderNumber());
                $orderIncrementId = $this->formatIncrementId($order->getOrderNumber());
                try {
                    $magentoOrder = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);

                    if (!$magentoOrder->getId()) {
                        $this->serviceLog($stream, "Order " . $orderIncrementId . " can not be found\n");
                        $this->moduleLog('WARNING: Order #' . $orderIncrementId . ' cannot be found.');
                        continue;
                    }

                    if ($order->getOrderStatus() == OrderStatus::STATUS_NOT_SHIPPED) {
                        $this->serviceLog($stream, "Order " . $orderIncrementId . " is not shipped, no need to update \n");
                        continue;
                    }

                    $itemsToShip = $this->getItemsToShip($magentoOrder, $order);

                    $tracks = [];
                    foreach ($order->getBarcodes() as $barcode) {
                        $tracks[] = [
                            'number' => $barcode['code'],
                            'title' => self::TRACKING_TITLE,
                            'carrier_code' => self::CARRIER_CODE
                        ];
                    }

                    $this->serviceLog($stream, "Creating shipment for order #" . $magentoOrder->getIncrementId() . " with items " . print_r($itemsToShip,
                            true));

                    /** @var Shipment $shipment */
                    $shipment = $this->shipmentFactory->create($magentoOrder, $itemsToShip, $tracks);
                    $shipment->register();

                    $status = $order->getOrderStatus();
                    $magentoOrder->setData("status", $status == "shipped" ? $shippedStatus : "processing");
                    $magentoOrder->setData("state", $status == "shipped" ? $shippedStatus : "processing");
                    $magentoOrder->addStatusToHistory(false, 'order status imported from progress');

                    $this->saveShipment($shipment);

                    $this->moduleLog(sprintf('Created shipment #%s (id %s) for order #%s (id %s)',
                        $shipment->getIncrementId(),
                        $shipment->getId(),
                        $magentoOrder->getIncrementId(),
                        $magentoOrder->getId()
                    ));

                    if ($this->helper->getOrderImportSendEmailAfterShipping() == '1') {
                        $this->shipmentMailer->send($shipment);
                        $this->moduleLog('Adding shipment email notification for #' . $magentoOrder->getIncrementId());
                    } else {
                        $this->moduleLog('Bypassing shipment email notification for #' . $magentoOrder->getIncrementId());
                    }

                    $this->serviceLog($stream, "Order #" . $magentoOrder->getIncrementId() . " saved successfully with status " . $magentoOrder->getStatus());
                } catch (Exception $e) {

                    if (stripos($e->getMessage(),
                            'Expectation failed') !== false
                    ) { //PHPUnit exception; must not be catched
                        throw $e;
                    }

                    $this->serviceLog($stream, "Order " . $orderIncrementId . " failed import", Logger::ERROR);
                    $this->serviceLog($stream, $e->getMessage(), Logger::ERROR);

                    $this->moduleLog('WARNING: Order ' . $orderIncrementId . ' failed import');
                    $this->moduleLog($e->getMessage());
                    $this->sendErrorMail($this->getErrorEmail(),
                        'Exception occured during order import from EDG for order #' . $orderIncrementId,
                        $e->getMessage());

                }
            }
        }
    }

    /**
     * Formats the increment to the default format of Magento. This is important because the Webservice returns the
     * increment ID as int which removes leading zeros.
     *
     * @param $id
     * @return string
     * @throws Exception
     */
    protected function formatIncrementId($id)
    {
        $pattern = \Magento\SalesSequence\Model\Sequence::DEFAULT_PATTERN;
        $pos1 = strpos($pattern, '%');
        $pos2 = strpos($pattern, '%', $pos1 + 1);
        $pos3 = strpos($pattern, '%', $pos2 + 1);
        $newPattern = substr($pattern, $pos2, $pos3 - $pos2);

        $newId = sprintf($newPattern, $id);
        if (strlen($newId) != strlen($id)) {
            $this->serviceLog(null, sprintf('Transforming Order increment id from %s to %s', $id, $newId));
        }
        return $newId;
    }

    /**
     * @param Order $magentoOrder
     * @param OrderStatus $importData
     * @return array
     */
    protected function getItemsToShip(
        Order $magentoOrder,
        OrderStatus $importData
    ) {
        $items = [];

        foreach ($importData->getOrderRows() as $orderrow) {
            $sku = $orderrow['sku'];
            $ordered = empty($orderrow['ordered']) ? 0 : $orderrow['ordered'];
            $invoiced = empty($orderrow['invoiced']) ? 0 : $orderrow['invoiced'];
            $shipped = empty($orderrow['shipped']) ? 0 : $orderrow['shipped'];

            if (is_array($shipped)) {
                $shipped = 0;
            }

            $items[$sku] = [
                'ordered' => $ordered,
                'invoiced' => $invoiced,
                'shipped' => $shipped
            ];
        }

        $itemsToShip = [];

        $skuPrefix = $this->helper->getSkuPrefix();

        foreach ($magentoOrder->getAllItems() as $item) {
            $itemSku = $skuPrefix . $item->getSku();

            if ($item->getData("parent_item_id")) {
                $this->moduleLog(' - Parent item exists, skipping shipping on this one.');
                continue;
            }

            if (!isset($items[$itemSku])) {
                $this->moduleLog(' - SKU ' . $itemSku . ' does not exist in shipped item data, skipping.');
                continue;
            }

            if ($item->getQtyShipped() != $items[$itemSku]['shipped']) {
                $this->moduleLog('Need to update ' . $magentoOrder->getIncrementId() . ' item ' . $itemSku, true);
                $this->moduleLog($magentoOrder->getIncrementId() . ' ' . $item->getData("parent_item_id") . ' ' . $item->getQtyInvoiced() . ' ' . $itemSku,
                    true);
                $itemsToShip[$item->getId()] = $items[$itemSku]['shipped'] - $item->getQtyShipped();
            }
        }

        return $itemsToShip;

    }

    /**
     * Save shipment and order in one transaction
     *
     * @param Shipment $shipment
     * @return $this
     * @throws Exception
     */
    protected function saveShipment($shipment): OrderStatusImport
    {
        $shipment->getOrder()->setIsInProcess(true);
        $transaction = $this->transaction;
        $transaction->addObject(
            $shipment
        )->addObject(
            $shipment->getOrder()
        )->save();

        return $this;
    }

}
