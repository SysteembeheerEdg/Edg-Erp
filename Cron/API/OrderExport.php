<?php
/**
 * OrderExport
 *
 * @copyright Copyright © 2017 Bold Commerce BV. All rights reserved.
 * @author    dev@boldcommerce.nl
 */

namespace Bold\PIM\Cron\API;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Mail\Message;

class OrderExport extends AbstractCron
{
    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    protected $orderConverter;

    protected $orderRepository;

    protected $articleTypeHelper;

    public function __construct(
        \Bold\PIM\Helper\Data $helper,
        DirectoryList $directoryList,
        ConfigInterface $config,
        Message $message,
        \Magento\Store\Model\StoreManager $storeManager,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Bold\PIM\Model\Convert\OrderToDataModel $orderConverter,
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Bold\PIM\Helper\ArticleType $articleTypeHelper,
        array $settings = []
    ) {
        $this->orderFactory = $orderFactory;
        $this->orderConverter = $orderConverter;
        $this->orderRepository = $orderRepository;
        $this->articleTypeHelper = $articleTypeHelper;
        parent::__construct($helper, $directoryList, $config, $message, $storeManager, $settings);
    }

    public function execute()
    {
        // TODO: Implement execute() method.
        if ($this->settings['force_order_upload'] === true) {
            $this->prepareExport(true);
            return;
        }

        if ($this->helper->isUploadOrdersEnabled()) {
            $this->prepareExport(false);
        } else {
            $this->moduleLog(__METHOD__ . '(); - isUploadOrdersEnabled setting disabled, skipping.', true);
        }
    }

    protected function prepareExport($force = false)
    {
        $date = date("Y_m_d");
        $this->addLogStreamToServiceLogger($this->_exportDir . DIRECTORY_SEPARATOR . "log_{$date}.log");

        if (!isset($this->settings['order_id'])) {
            $orders = [];

            $orderStatuses = $this->helper->getOrderStatusesToExport();

            $orderPaymentStatuses = $this->helper->getOrderStatusesAndPaymentCodeToExport();

            $this->serviceLog('Start Export Orders');

            $this->moduleLog('*** start order export (multiple orders)', true);
            
            $collection = $this->orderFactory->create()->getCollection()
                ->addFieldToFilter('status', ['in' => $orderStatuses])
                ->addFieldToFilter('pim_is_exported', 0);

            $collection->setPageSize(100);
            $pages = $collection->getLastPageNumber();
            $currentPage = 1;

            $this->moduleLog(sprintf("Detected %d order(s) possibly suitable for export based on order status: %s",
                $collection->getSize(), var_export($orderStatuses, true)), true);

            while ($currentPage <= $pages) {
                $collection->setCurPage($currentPage);

                /** @var \Magento\Sales\Model\Order $order */
                foreach ($collection as $order) {
                    try {
                        $paymentMethodCode = $order->getPayment()->getMethodInstance()->getCode();
                        $matchFound = false;
                        foreach ($orderPaymentStatuses as $status) {
                            if (isset($status['order_status']) && $status['order_status'] == $order->getStatus()) {

                                if ($status['payment_code'] == null || $status['payment_code'] == $paymentMethodCode) {
                                    $matchFound = true;
                                }
                            }
                        }
                        if ($matchFound) {
                            $this->exportOrder($order);
                        }
                    } catch (\Exception $e) {
                        $this->serviceLog('Error when exporting order #' . $order->getIncrementId() . ' - ' . $e->getMessage(),
                            \Zend\Log\Logger::ERR);

                        $this->moduleLog(__METHOD__ . ' Error exporting order #' . $order->getIncrementId() . ' ' . $e->getMessage());

                        $this->sendErrorMail(
                            $this->getErrorEmail(),
                            'Exception occured during order export to EDG',
                            "Error when exporting order #{$order->getIncrementId()}. " . $e->getMessage()
                        );

                        $this->serviceLog('Finished export with exception.', \Zend\Log\Logger::ERR);
                        return $this;
                    }
                }
                $currentPage++;
                $collection->clear();
            }

        } else {
            $orderId = $this->settings['order_id'];
            $this->serviceLog("Start Export Order #" . $orderId);
            $this->exportOrder($orderId, $force);
        }

        $this->serviceLog('Finished Export Orders');
        $this->moduleLog('Finished orderexport');

        return $this;
    }

    /**
     * @param String|\Magento\Sales\Model\Order $orderId
     * @return $this
     * @throws \Exception
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function exportOrder($orderId)
    {
        if ($orderId instanceof \Magento\Sales\Model\Order) {
            $order = $orderId;
        } elseif (is_string($orderId)) {
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);
            if (!$order->getId()) {
                throw new \Magento\Framework\Exception\NoSuchEntityException('Order Export: could not load order "' . $orderId . '"');
            }
        } else {
            throw new \Exception('order id param should be a string or an instance of magento\\sales\\model\\order');
        }

        $hasErrors = false;

        $client = $this->helper->getSoapClient();

        $orderData = $this->orderConverter->convert($order, $this->helper->getConfigSetting('export_order_type'),
            $this->helper->getEnvironmentTag());

        $responses = $client->pushNewOrder($orderData, [
            'export_type' => $this->helper->getConfigSetting('export_order_type'),
            'environment' => $this->helper->getEnvironmentTag()
        ]);

        foreach ($responses as $response) {
            if (!$response->isValid()) {
                $hasErrors = true;
                break;
            }
        }

        if ($hasErrors === false) {
            $order->setPimIsExported(1);
            $order->setPimExportedAt(date('Y-m-d H:i:s'));
            $order->addStatusHistoryComment(
                sprintf('Succesfully exported order #%s  with message "%s", status "%s"', $order->getIncrementId(),
                    $response->getMessage(), $response->getStatus())
                , false);
            $this->orderRepository->save($order);

            // Next, autoship non-shippable items
            $this->articleTypeHelper->autoshipNonShippableItemsByOrder($order);

        }

        return $this;
    }
}