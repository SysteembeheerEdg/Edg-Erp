<?php

namespace Edg\Erp\Cron\API;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Mail\Message;

class OrderStatusImport extends AbstractCron
{
    const TRACKING_TITLE = 'Track & Trace';
    const CARRIER_CODE = 'PostNL';

    protected $orderFactory;
    protected $shipmentFactory;
    protected $transaction;
    protected $shipmentMailer;

    public function __construct(
        \Edg\Erp\Helper\Data $helper,
        DirectoryList $directoryList,
        ConfigInterface $config,
        Message $message,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Order\Email\Sender\ShipmentSender $sender,
        array $settings = []
    ) {
        parent::__construct($helper, $directoryList, $config, $message, $storeManager, $settings);
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

    protected function prepareImport()
    {
        $date = date("Y_m_d");
        $this->addLogStreamToServiceLogger($this->_importDir . DIRECTORY_SEPARATOR . "log_{$date}.log");
        $this->serviceLog('Start import');

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
                $this->serviceLog('starting import for order with order number: #' . $order->getOrderNumber());
                $orderIncrementId = $this->formatIncrementId($order->getOrderNumber());
                try {
                    $magentoOrder = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);

                    if (!$magentoOrder->getId()) {
                        $this->serviceLog("Order " . $orderIncrementId . " can not be found\n");
                        $this->moduleLog('WARNING: Order #' . $orderIncrementId . ' cannot be found.');
                        continue;
                    }

                    if ($order->getOrderStatus() == \Edg\ErpService\DataModel\OrderStatus::STATUS_NOT_SHIPPED) {
                        $this->serviceLog("Order " . $orderIncrementId . " is not shipped, no need to update \n");
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

                    $this->serviceLog("Creating shipment for order #" . $magentoOrder->getIncrementId() . " with items " . print_r($itemsToShip,
                            true));

                    /** @var \Magento\Sales\Model\Order\Shipment $shipment */
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

                    $this->serviceLog("Order #" . $magentoOrder->getIncrementId() . " saved successfully with status " . $magentoOrder->getStatus());
                } catch (\Exception $e) {

                    if (stripos($e->getMessage(),
                            'Expectation failed') !== false
                    ) { //PHPUnit exception; must not be catched
                        throw $e;
                    }

                    $this->serviceLog("Order " . $orderIncrementId . " failed import", \Zend\Log\Logger::ERR);
                    $this->serviceLog($e->getMessage(), \Zend\Log\Logger::ERR);

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
            $this->serviceLog(sprintf('Transforming Order increment id from %s to %s', $id, $newId));
        }
        return $newId;
    }

    /**
     * @param \Magento\Sales\Model\Order $magentoOrder
     * @param \Edg\ErpService\DataModel\OrderStatus $importData
     * @return array
     */
    protected function getItemsToShip(
        \Magento\Sales\Model\Order $magentoOrder,
        \Edg\ErpService\DataModel\OrderStatus $importData
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
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return $this
     */
    protected function saveShipment($shipment)
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
