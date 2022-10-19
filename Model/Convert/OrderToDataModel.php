<?php

namespace Edg\Erp\Model\Convert;

use Exception;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\GroupFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Status\History;
use Magento\Store\Model\ScopeInterface;

class OrderToDataModel
{
    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $storeConfig;

    /**
     * @var GroupFactory
     */
    protected GroupFactory $groupFactory;

    /**
     * @var CustomerFactory
     */
    protected CustomerFactory $customerFactory;

    /**
     * @var string[]
     */
    protected array $_exportCustomerFieldsMap = array(
        'email' => 'email',
        'customer_group' => 'group_id'
    );

    /**
     * @var array|string[]
     */
    protected array $_exportCustomerFieldsMapAwCa = array(
        'progress_id',
        'school_naam',
        'brinnummer',
        'functie_besteller',
        'school_bool'
    );

    /**
     * OrderToDataModel constructor.
     *
     * @param ScopeConfigInterface $configInterface
     * @param GroupFactory $groupFactory
     * @param CustomerFactory $customerFactory
     */
    public function __construct(
        ScopeConfigInterface $configInterface,
        GroupFactory $groupFactory,
        CustomerFactory $customerFactory
    ) {
        $this->storeConfig = $configInterface;
        $this->groupFactory = $groupFactory;
        $this->customerFactory = $customerFactory;
    }

    /**
     * @param Order $order
     * @param $exportOrderType
     * @param $environment
     * @return \Edg\ErpService\DataModel\Order
     * @throws Exception
     */
    public function convert(Order $order, $exportOrderType, $environment = null): \Edg\ErpService\DataModel\Order
    {

        $data = $this->orderToArray($order, $exportOrderType, $environment);

        return new \Edg\ErpService\DataModel\Order($data);
    }

    protected function orderToArray(Order $order, $exportOrderType, $environment = null): array
    {
        $orderArray = $order->toArray(
            [
                'incrementId',
                'shipping_method',
                'shipping_amount',

                'shipping_incl_tax',

                'payment_method_title',
                'payment_method',
                'discount',
                'currency',
                'subtotal',

                'subtotal_incl_tax',

                'grandtotal',
                'total_paid',
                'total_refunded',
                'total_due',

                'coupon_code',
                'coupon_rule_name',
            ]
        );

        $data = [];

        $this
            ->processGeneralData($order, $data)
            ->processOrderItems($order, $data)
            ->processAddresses($order, $data)
            ->processCustomer($order, $data)
            ->processStatusComments($order, $data);


        $data['meta']['type'] = $exportOrderType;

        // Preset VAT
        $data['vat_number'] = $order->getBillingAddress()->getVatId();

        $data['environment_tag'] = $environment;

        $data = array_merge($orderArray, $data);
        return $data;
    }

    /**
     * @param Order $order
     * @param $data
     * @return $this
     */
    protected function processStatusComments(Order $order, &$data): OrderToDataModel
    {
        $_history = $order->getAllStatusHistory();
        $_buffer = [];
        $_filters = [
            'exported through Bold PIM',
            'Bold_OrderInvoicer',
            'Captured amount of',
            'Payment is pre authorised waiting for capture',
            'waiting for capture',
            'Adyen',
        ];

        $_buffer[] = $order->getBoldOaRemarks();

        /** @var History $_historyItem */
        foreach ($_history as $_historyItem) {
            $comment = $_historyItem->getComment();

            foreach ($_filters as $filter) {
                if (strpos($comment, $filter) !== false) {
                    continue 2;
                }
            }

            if (strlen($comment) > 0) {
                $_buffer[] = $_historyItem->getData('comment');
            }
        }

        $data['order_remarks'] = $_buffer;

        return $this;
    }

    /**
     * @param Order $order
     * @param $data
     * @return $this
     */
    protected function processCustomer(Order $order, &$data): OrderToDataModel
    {
        if ($customerId = $order->getCustomerId()) {
            $data['customer'] = [];

            $customer = $order->getCustomer();

            $data['customer']['id'] = $customerId;
            if (!$customer || !$customer->getId()) {
                $customer = $this->customerFactory->create()->load($customerId);
            }
            foreach ($this->_exportCustomerFieldsMap as $fieldName => $fieldNameMap) {
                $data['customer'][$fieldName] = $customer->getData($fieldNameMap);
            }
        }
        return $this;
    }

    /**
     * @param Order $order
     * @param $data
     * @return $this
     */
    protected function processAddresses(Order $order, &$data): OrderToDataModel
    {
        /** @var Address $address */
        foreach ($order->getAddressesCollection() as $address) {
            $type = $address->getAddressType();
            $data['addresses'][$type] = $address->toArray([
                "city",
                "company",
                "country_id",
                "email",
                "fax",
                "telephone",
                "firstname",
                "lastname",
                "middlename",
                "postcode",
                "prefix",
                "region",
                "street",
                "vat_id"
            ]);

            if ($type = Address::TYPE_BILLING && $order->getInvoiceEmailAddress()) {
                $data['addresses'][$type]['email'] = $order->getInvoiceEmailAddress();
            }
        }

        return $this;
    }

    /**
     * @param Order $order
     * @param $data
     * @return $this
     */
    protected function processOrderItems(Order $order, &$data): OrderToDataModel
    {
        $itemsCollection = $order->getItemsCollection();

        $data['total_items'] = $itemsCollection->count();
        foreach ($itemsCollection as $item) {
            /** @var Item $item */
            $_data = $item->toArray([
                'item_id',
                'name',
                'sku',
                'qty',
                'price',
                'price_incl_tax',
                'tax_percent',
                'discount_amount',
                'row_total',
                'original_price',
                'row_total_incl_tax',
                'product_type',
            ]);

            $_data['qty'] = $item->getQtyOrdered();
            if ($item->getParentItemId()) {
                $data['items'][$item->getParentItemId()]['configurables'][] = $_data;
            } else {
                $data['items'][$item->getId()] = $_data;
            }
        }

        return $this;
    }

    /**
     * @param Order $order
     * @param $data
     * @return $this
     * @throws LocalizedException
     * @throws Exception
     */
    protected function processGeneralData(Order $order, &$data): OrderToDataModel
    {
        $timezone = new \DateTimeZone($this->storeConfig->getValue("general/locale/timezone",
            ScopeInterface::SCOPE_STORE, $order->getStoreId()));
        $date = new \DateTime($order->getCreatedAt(), $timezone);

        if (!$paymentMethod = $this->getCcTypeFromOrder($order)) {
            $paymentMethod = $order->getPayment()->getMethod();
        }

        $groupName = $this->groupFactory->create()->load($order->getCustomerGroupId())->getCustomerGroupCode();

        $data = array(
            'incrementId' => $order->getIncrementId(),
            'meta' => [
                "store" => $order->getStoreName(),
                "datetime" => $date->format("dmY H:i:s"),
                "time_offset" => $date->format("P")
            ],
            'addresses' => array(),
            'payment_method_title' => $order->getPayment()->getMethodInstance()->getTitle(),
            'payment_method' => $paymentMethod,
            'payment_transactionid' => $order->getPayment()->getData('last_trans_id'),
            'discount' => $order->getDiscountAmount(),
            'currency' => $order->getBaseCurrencyCode(),
            'subtotal' => $order->getSubtotal(),
            'grandtotal' => $order->getGrandTotal(),
            'total_paid' => $order->getTotalPaid(),
            'total_refunded' => $order->getTotalRefunded(),
            'total_due' => $order->getTotalDue(),

            'total_items' => null,
            'items' => null,

            'order_customer_group_id' => $order->getCustomerGroupId(),
            'order_customer_group_name' => $groupName,

            'order_brinnummer' => $order->getBoldOaBrinnummer(),
            'order_school_naam' => $order->getBoldOaSchoolNaam(),
            'order_functie_besteller' => $order->getBoldOaFunctieBesteller(),
            'order_reference' => $order->getBoldOaReference(),
            'order_ip' => $order->getRemoteIp(),

        );


        return $this;
    }

    /**
     * M1 legacy method for retrieving payment method of adyen orders by Bart. Unsure if still needed in M2
     *
     * @param Order $order
     * @return false|string|null
     */
    protected function getCcTypeFromOrder(Order $order)
    {
        if (!$order->getId()) {
            return false;
        }
        if ($order->getPayment()) {
            return $order->getPayment()->getCcType();
        }

        return false;
    }
}
