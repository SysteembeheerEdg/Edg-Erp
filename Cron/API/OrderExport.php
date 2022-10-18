<?php

namespace Edg\Erp\Cron\API;

use Edg\Erp\Helper\ArticleType;
use Edg\Erp\Helper\Data;
use Edg\Erp\Model\Convert\OrderToDataModel;
use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Logger\Monolog;
use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManager;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\FilterGroupBuilder;



class OrderExport extends AbstractCron
{
    /**
     * @var OrderFactory
     */
    protected OrderFactory $orderFactory;

    /**
     * @var OrderToDataModel
     */
    protected OrderToDataModel $orderConverter;

    /**
     * @var TransportBuilder
     */
    protected TransportBuilder $transportBuilder;

    /**
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $orderRepository;

    /**
     * @var ArticleType
     */
    protected ArticleType $articleTypeHelper;

    /**
     * @var SearchCriteriaBuilder
     */
    protected SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var SearchCriteriaInterface
     */
    protected SearchCriteriaInterface $searchCriteria;

    /**
     * @var FilterBuilder
     */
    protected FilterBuilder $filter;

    /**
     * @var FilterGroup
     */
    protected FilterGroup $filterGroup;

    /**
     * @var FilterGroupBuilder
     */
    protected FilterGroupBuilder $filterGroupBuilder;

    /**
     * @param Data $helper
     * @param DirectoryList $directoryList
     * @param Monolog $monolog
     * @param ConfigInterface $config
     * @param TransportBuilder $transportBuilder
     * @param StoreManager $storeManager
     * @param OrderFactory $orderFactory
     * @param OrderToDataModel $orderConverter
     * @param OrderRepositoryInterface $orderRepository
     * @param ArticleType $articleTypeHelper
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SearchCriteriaInterface $searchCriteria
     * @param FilterBuilder $filterBuilder
     * @param FilterGroup $filterGroup
     * @param FilterGroupBuilder $filterGroupBuilder
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
        OrderToDataModel $orderConverter,
        OrderRepositoryInterface $orderRepository,
        ArticleType $articleTypeHelper,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SearchCriteriaInterface $searchCriteria,
        FilterBuilder $filterBuilder,
        FilterGroup $filterGroup,
        FilterGroupBuilder $filterGroupBuilder,
        array $settings = []
    ) {
        $this->orderFactory = $orderFactory;
        $this->orderConverter = $orderConverter;
        $this->orderRepository = $orderRepository;
        $this->articleTypeHelper = $articleTypeHelper;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->searchCriteria = $searchCriteria;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroup = $filterGroup;
        $this->filterGroupBuilder = $filterGroupBuilder;
        parent::__construct($helper, $directoryList, $monolog, $config, $transportBuilder, $storeManager, $settings);
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
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

    /**
     * @param bool $force
     * @return $this
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    protected function prepareExport(bool $force = false): OrderExport
    {
        $date = date("Y_m_d");
        $stream = $this->addLogStreamToServiceLogger($this->_exportDir . DIRECTORY_SEPARATOR . "log_{$date}.log");

        if (!isset($this->settings['order_id'])) {

            $orderStatuses = $this->helper->getOrderStatusesToExport();
            $orderPaymentStatuses = $this->helper->getOrderStatusesAndPaymentCodeToExport();

            $this->serviceLog($stream, 'Start Export Orders');
            $this->moduleLog('*** start order export (multiple orders)', true);

            $searchCriteria = $this->getSearchCriteria($orderStatuses);
            $orderList = $this->orderRepository->getList($searchCriteria);

            if ($orderList->getTotalCount() > 0) {

                $this->moduleLog(sprintf("Detected %d order(s) possibly suitable for export based on order status: %s",
                    $orderList->getTotalCount(), var_export($orderStatuses, true)), true);

                $orders = $orderList->getItems();
                foreach ($orders as $order) {
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
                    } catch (Exception $e) {
                        $this->serviceLog($stream,'Error when exporting order #' . $order->getIncrementId() . ' - ' . $e->getMessage(),
                            \Monolog\Logger::ERROR);

                        $this->moduleLog(__METHOD__ . ' Error exporting order #' . $order->getIncrementId() . ' ' . $e->getMessage());

                        $this->sendErrorMail(
                            $this->getErrorEmail(),
                            'Exception occured during order export to EDG',
                            "Error when exporting order #{$order->getIncrementId()}."
                        );


                        $this->serviceLog($stream, 'Finished export with exception.', \Monolog\Logger::ERROR);
                        return $this;
                    }

                }
            }
        } else {
            $orderId = $this->settings['order_id'];
            $this->serviceLog($stream, "Start Export Order #" . $orderId);
            $this->exportOrder($orderId);
        }

        $this->serviceLog($stream, 'Finished Export Orders');
        $this->moduleLog('Finished orderexport');

        return $this;
    }

    /**
     * @param $orderId
     * @return $this
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws Exception
     */
    protected function exportOrder($orderId): OrderExport
    {
        if ($orderId instanceof Order) {
            $order = $orderId;
        } elseif (is_string($orderId)) {
            $order = $this->orderFactory->create()->loadByIncrementId($orderId);
            if (!$order->getId()) {
                throw new NoSuchEntityException(
                    new Phrase('Order Export: could not load order ' . $orderId . '"')
                );
            }
        } else {
            throw new Exception('order id param should be a string or an instance of magento\\sales\\model\\order');
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
            $order->addCommentToStatusHistory(
                sprintf('Succesfully exported order #%s  with message "%s", status "%s"', $order->getIncrementId(),
                    $response->getMessage(), $response->getStatus())
            );
            $this->orderRepository->save($order);

            // Next, autoship non-shippable items
            $this->articleTypeHelper->autoshipNonShippableItemsByOrder($order);

        }

        return $this;
    }

    /**
     * @param $orderStatuses
     * @return SearchCriteriaInterface
     */
    private function getSearchCriteria($orderStatuses): SearchCriteriaInterface
    {
        return $this->searchCriteriaBuilder
            ->addFilter('pim_is_exported', 0)
            ->addFilter('status', $orderStatuses, 'in')
            ->setPageSize(1000)
            ->create();
    }
}
