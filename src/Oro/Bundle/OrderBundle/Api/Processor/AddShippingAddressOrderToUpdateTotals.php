<?php

namespace Oro\Bundle\OrderBundle\Api\Processor;

use Oro\Bundle\ApiBundle\Processor\CustomizeFormData\CustomizeFormDataContext;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Adds an order associated with an order shipping address entity to the list of orders
 * for which totals need to be updated.
 */
class AddShippingAddressOrderToUpdateTotals implements ProcessorInterface
{
    /** @var DoctrineHelper */
    private $doctrineHelper;

    /**
     * @param DoctrineHelper $doctrineHelper
     */
    public function __construct(DoctrineHelper $doctrineHelper)
    {
        $this->doctrineHelper = $doctrineHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var CustomizeFormDataContext $context */

        if (!$context->getForm()->isValid()) {
            return;
        }

        $order = $this->getOrder($context->getData());
        if (null !== $order) {
            UpdateOrderTotals::addOrderToUpdateTotals($context->getSharedData(), $order, $context->getForm());
        }
    }

    /**
     * @param OrderAddress $orderAddress
     *
     * @return Order|null
     */
    private function getOrder(OrderAddress $orderAddress): ?Order
    {
        return $this->doctrineHelper->createQueryBuilder(Order::class, 'o')
            ->where('o.shippingAddress = :shippingAddress')
            ->setParameter('shippingAddress', $orderAddress)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
