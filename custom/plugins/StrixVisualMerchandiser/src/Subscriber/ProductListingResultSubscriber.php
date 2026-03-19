<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Subscriber;

use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Strix\VisualMerchandiser\Core\Merchandising\FilterOverrideService;
use Strix\VisualMerchandiser\Core\Merchandising\PinResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductListingResultSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PinResolver $pinResolver,
        private readonly FilterOverrideService $filterOverrideService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingResultEvent::class => ['onProductListingResult', 500],
        ];
    }

    public function onProductListingResult(ProductListingResultEvent $event): void
    {
        $categoryId = $event->getResult()->getCurrentFilter('navigationId');
        if (!\is_string($categoryId)) {
            return;
        }

        $result = $event->getResult();

        $this->pinResolver->resolve(
            $categoryId,
            $result,
            $event->getContext(),
            $event->getSalesChannelContext()->getSalesChannelId(),
        );

        // Attach pin label data as extensions on product entities for storefront rendering
        $pins = $this->pinResolver->getActivePins($categoryId, $event->getContext());
        foreach ($pins as $pin) {
            $product = $result->get($pin->getProductId());
            if ($product) {
                $product->addExtension('merch_pin', new ArrayStruct([
                    'label' => $pin->getLabel(),
                    'customLabel' => $pin->getCustomLabel(),
                    'position' => $pin->getPosition(),
                ]));
            }
        }

        $this->filterOverrideService->apply(
            $categoryId,
            $result,
            $event->getSalesChannelContext(),
        );
    }
}
