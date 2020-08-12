<?php

namespace Edg\Erp\Model\Convert;

use Magento\Store\Model\ScopeInterface;

class OrderToDataModel
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $storeConfig;

    /**
     * @var \Magento\Customer\Model\GroupFactory
     */
    protected $groupFactory;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     *
     */
    protected $_exportCustomerFieldsMap = array(
        'email' => 'email',
        'customer_group' => 'group_id'
    );

    /**
     *
     */
    protected $_exportCustomerFieldsMapAwCa = array(
        'progress_id',
        'school_naam',
        'brinnummer',
        'functie_besteller',
        'school_bool'
    );

    /**
     * OrderToDataModel constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $configInterface
     * @param \Magento\Customer\Model\GroupFactory $groupFactory
     * @param \Magento\Customer\Model\CustomerFactory $customerFactory
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $configInterface,
        \Magento\Customer\Model\GroupFactory $groupFactory,
        \Magento\Customer\Model\CustomerFactory $customerFactory
    ) {
        $this->storeConfig = $configInterface;
        $this->groupFactory = $groupFactory;
        $this->customerFactory = $customerFactory;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return \Bold\PIMService\DataModel\Order
     */
    public function convert(\Magento\Sales\Model\Order $order, $exportOrderType, $environment = null)
    {

        $data = $this->orderToArray($order, $exportOrderType, $environment);

        $datamodel = new \Bold\PIMService\DataModel\Order($data);
        return $datamodel;
    }

    protected function orderToArray(\Magento\Sales\Model\Order $order, $exportOrderType, $environment = null)
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
     * @param \Magento\Sales\Model\Order $order
     * @param $data
     * @return $this
     */
    protected function processStatusComments(\Magento\Sales\Model\Order $order, &$data)
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

        /** @var \Magento\Sales\Model\Order\Status\History $_historyItem */
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
     * @param \Magento\Sales\Model\Order $order
     * @param $data
     * @return $this
     */
    protected function processCustomer(\Magento\Sales\Model\Order $order, &$data)
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
     * @param \Magento\Sales\Model\Order $order
     * @param $data
     * @return $this
     */
    protected function processAddresses(\Magento\Sales\Model\Order $order, &$data)
    {
        /** @var \Magento\Sales\Model\Order\Address $address */
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
        }

        return $this;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param $data
     * @return $this
     */
    protected function processOrderItems(\Magento\Sales\Model\Order $order, &$data)
    {
        $itemsCollection = $order->getItemsCollection();

        $data['total_items'] = $itemsCollection->count();
        foreach ($itemsCollection as $item) {
            /** @var \Magento\Sales\Model\Order\Item $item */
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
     * @param \Magento\Sales\Model\Order $order
     * @param $data
     * @return $this
     */
    protected function processGeneralData(\Magento\Sales\Model\Order $order, &$data)
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
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     */
    protected function getCcTypeFromOrder(\Magento\Sales\Model\Order $order)
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