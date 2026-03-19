<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Subscriber;

use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Strix\VisualMerchandiser\Core\Merchandising\PinResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductListingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PinResolver $pinResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingCriteriaEvent::class => ['onProductListingCriteria', 500],
        ];
    }

    public function onProductListingCriteria(ProductListingCriteriaEvent $event): void
    {
        $criteria = $event->getCriteria();

        // Store customer ID and sales channel ID for the ES query subscriber (personalization)
        $customerId = $event->getSalesChannelContext()->getCustomerId();
        if ($customerId !== null) {
            $criteria->addExtension('merch_customer', new ArrayStruct([
                'id' => $customerId,
                'salesChannelId' => $event->getSalesChannelContext()->getSalesChannelId(),
            ]));
        }

        $categoryId = $this->extractCategoryId($event);
        if ($categoryId === null) {
            return;
        }

        $pinnedIds = $this->pinResolver->getPinnedProductIds($categoryId, $event->getContext());
        if (empty($pinnedIds)) {
            return;
        }

        // Store pinned IDs as extension so ProductListingResultSubscriber can reposition them
        $criteria->addExtension('merch_pinned_ids', new ArrayStruct(['ids' => $pinnedIds]));
    }

    private function extractCategoryId(ProductListingCriteriaEvent $event): ?string
    {
        foreach ($event->getCriteria()->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === 'product.categoriesRo.id') {
                return (string) $filter->getValue();
            }
        }

        return null;
    }
}
