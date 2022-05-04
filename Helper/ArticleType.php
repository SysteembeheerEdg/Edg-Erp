<?php

namespace Edg\Erp\Helper;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Sales\Model\Order;

class ArticleType extends AbstractHelper
{
    /**
     * Cache source model
     */
    protected $articleTypeSourceModel = null;

    protected $shipmentFactory;

    protected $transaction;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Edg\Erp\Model\SourceModel\Eav\ArticleType $articleType,
        \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory,
        \Magento\Framework\DB\Transaction $transaction
    ) {
        $this->articleTypeSourceModel = $articleType;
        $this->shipmentFactory = $shipmentFactory;
        $this->transaction = $transaction;
        parent::__construct($context);
    }

    /**
     * Create shipment for all "non-shippable" items
     * @param Order $order
     * @return bool|int
     */
    public function autoshipNonShippableItemsByOrder(Order $order)
    {
        $nonShippableItems = array();

        if (!$order->getId() || !$order->canShip()) {
            return false;
        }

        // Collect items that need to be shipped
        foreach ($order->getAllItems() as $item) {

            // Ignore items that belong to bundle / configurable / grouped etc
            if ($item->getData("parent_item_id")) {
                continue;
            }
            if ($this->isNonShippableProduct($item->getProduct())) {
                if ($item->getQtyToShip() > 0) {
                    $nonShippableItems[$item->getId()] = $item->getQtyToShip();
                }
            }
        }

        $shipCount = count($nonShippableItems);

        if ($shipCount > 0) {
            try {
                /** @var \Magento\Sales\Model\Order\Shipment $shipment */
                $shipment = $this->shipmentFactory->create($order, $nonShippableItems);

                $comment = 'EDG PIM integration: Auto-created Shipment for non-shippable items.';

                $shipment->addComment($comment, false, false);

                $order->addCommentToStatusHistory($comment);

                $shipment
                    //->sendEmail(false) //sending email to only bcc is a bit more complicated in M2 skip for now
                    ->setEmailSent(false);

                $shipment->register();

                $this->transaction
                    ->addObject($shipment)
                    ->addObject($order)
                    ->save();
            } catch (\Exception $e) {
                return false;
            }
        }


        return $shipCount;
    }

    /**
     * Return whether product is a non-shippable product
     * @param Product $product
     * @return bool
     */
    public function isNonShippableProduct(Product $product): bool
    {
        $nonShippableArticleTypes = $this->articleTypeSourceModel->getNonShippableArticleTypes();
        $articleType = (int)$product->getBoldPimArticletype();
        if (!$articleType) {
            $articleType = $product->getResource()->getAttributeRawValue($product->getId(), 'bold_pim_articletype', 0);
        }

        return in_array($articleType, $nonShippableArticleTypes);
    }


}
