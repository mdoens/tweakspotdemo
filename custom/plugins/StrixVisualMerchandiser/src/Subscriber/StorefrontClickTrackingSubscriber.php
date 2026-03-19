<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Subscriber;

use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StorefrontClickTrackingSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onStorefrontRender',
        ];
    }

    public function onStorefrontRender(StorefrontRenderEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Only inject tracking data on product listing pages (category/navigation)
        if ($route !== 'frontend.navigation.page') {
            return;
        }

        $navigationId = $request->attributes->get('navigationId');
        if ($navigationId === null) {
            return;
        }

        // Pass category ID to the storefront template context
        // The storefront main.js MerchClickTrackingPlugin reads data attributes
        $event->setParameter('merchCategoryId', $navigationId);
        $event->setParameter('merchTrackingEnabled', true);
    }
}
