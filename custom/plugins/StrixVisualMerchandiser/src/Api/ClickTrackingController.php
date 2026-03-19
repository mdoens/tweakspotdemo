<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Api;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
class ClickTrackingController extends AbstractController
{
    private const ALLOWED_EVENT_TYPES = ['click', 'add_to_cart', 'purchase', 'filter_use', 'wishlist'];
    private const MAX_EVENTS_PER_REQUEST = 100;

    public function __construct(
        private readonly EntityRepository $clickEventRepository,
    ) {
    }

    #[Route(
        path: '/store-api/merch/click',
        name: 'store-api.merch.click',
        methods: ['POST'],
        defaults: ['_loginRequired' => false, '_rateLimiter' => 'merch_click'],
    )]
    public function track(Request $request, SalesChannelContext $context): JsonResponse
    {
        $events = $request->toArray()['events'] ?? [];

        if (empty($events)) {
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $events = \array_slice($events, 0, self::MAX_EVENTS_PER_REQUEST);

        $customerId = $context->getCustomerId();
        $salesChannelId = $context->getSalesChannelId();
        $sessionId = $request->hasSession() ? $request->getSession()->getId() : null;

        $records = [];
        foreach ($events as $event) {
            $categoryId = $event['categoryId'] ?? null;
            $productId = $event['productId'] ?? null;
            $eventType = $event['eventType'] ?? 'click';

            if ($categoryId === null || $productId === null) {
                continue;
            }

            if (!Uuid::isValid((string) $categoryId) || !Uuid::isValid((string) $productId)) {
                continue;
            }

            if (!\in_array($eventType, self::ALLOWED_EVENT_TYPES, true)) {
                continue;
            }

            $metadata = isset($event['metadata']) && \is_array($event['metadata']) ? $event['metadata'] : null;

            // Limit metadata size to prevent abuse (max 2KB)
            if ($metadata !== null && \strlen((string) json_encode($metadata)) > 2048) {
                $metadata = null;
            }

            $records[] = [
                'id' => Uuid::randomHex(),
                'categoryId' => $categoryId,
                'productId' => $productId,
                'eventType' => $eventType,
                'customerId' => $customerId,
                'sessionId' => $sessionId,
                'position' => isset($event['position']) ? (int) $event['position'] : null,
                'salesChannelId' => $salesChannelId,
                'metadata' => $metadata,
            ];
        }

        if (!empty($records)) {
            $this->clickEventRepository->create($records, $context->getContext());
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
