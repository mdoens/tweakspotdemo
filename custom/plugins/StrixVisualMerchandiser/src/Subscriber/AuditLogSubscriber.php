<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Subscriber;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AuditLogSubscriber implements EventSubscriberInterface
{
    /**
     * Entities we track in the audit log.
     */
    private const TRACKED_ENTITIES = [
        'strix_merch_rule',
        'strix_merch_pin',
        'strix_merch_filter_template',
        'strix_merch_sorting_template',
        'strix_merch_customer_segment',
    ];

    public function __construct(
        private readonly EntityRepository $auditLogRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        $events = [];
        foreach (self::TRACKED_ENTITIES as $entity) {
            $events[$entity . '.written'] = 'onEntityWritten';
            $events[$entity . '.deleted'] = 'onEntityDeleted';
        }

        return $events;
    }

    public function onEntityWritten(EntityWrittenEvent $event): void
    {
        $entityName = $event->getEntityName();
        $context = $event->getContext();

        foreach ($event->getWriteResults() as $result) {
            $operation = $result->getOperation();
            $action = $operation === 'insert' ? 'create' : 'update';
            $entityId = $result->getPrimaryKey();
            $payload = $result->getPayload();

            if (\is_array($entityId)) {
                $entityId = implode('-', $entityId);
            }

            $categoryId = $this->extractCategoryId($entityName, $payload);
            $this->writeLog($entityName, (string) $entityId, $action, $payload, $categoryId, $context);
        }
    }

    public function onEntityDeleted(EntityDeletedEvent $event): void
    {
        $entityName = $event->getEntityName();
        $context = $event->getContext();

        foreach ($event->getWriteResults() as $result) {
            $entityId = $result->getPrimaryKey();
            $payload = $result->getPayload();

            if (\is_array($entityId)) {
                $entityId = implode('-', $entityId);
            }

            $categoryId = $this->extractCategoryId($entityName, $payload);
            $this->writeLog($entityName, (string) $entityId, 'delete', null, $categoryId, $context);
        }
    }

    /**
     * Extract categoryId from the entity payload.
     * Pins have categoryId directly. Rules need junction table lookup.
     */
    private function extractCategoryId(string $entityName, ?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        // Direct categoryId on pins
        if (isset($payload['categoryId'])) {
            return (string) $payload['categoryId'];
        }

        // Direct categoryId on filter/sorting template category links
        if (isset($payload['category_id'])) {
            return (string) $payload['category_id'];
        }

        return null;
    }

    private function writeLog(string $entityType, string $entityId, string $action, ?array $changes, ?string $categoryId, Context $context): void
    {
        try {
            $this->auditLogRepository->create([
                [
                    'id' => Uuid::randomHex(),
                    'entityType' => $entityType,
                    'entityId' => $entityId,
                    'action' => $action,
                    'changes' => $changes,
                    'categoryId' => $categoryId,
                ],
            ], $context);
        } catch (\Throwable) {
            // Audit logging should never crash the main operation
        }
    }
}
