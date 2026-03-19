<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Strix\VisualMerchandiser\Core\Content\MerchFilterTemplate\MerchFilterTemplateDefinition;
use Strix\VisualMerchandiser\Core\Content\MerchPin\MerchPinDefinition;
use Strix\VisualMerchandiser\Core\Content\MerchRule\MerchRuleDefinition;
use Strix\VisualMerchandiser\Core\Content\MerchSortingTemplate\MerchSortingTemplateDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CacheInvalidationSubscriber implements EventSubscriberInterface
{
    private const TRACKED_ENTITIES = [
        MerchRuleDefinition::ENTITY_NAME,
        MerchPinDefinition::ENTITY_NAME,
        MerchFilterTemplateDefinition::ENTITY_NAME,
        MerchSortingTemplateDefinition::ENTITY_NAME,
    ];

    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        $events = [];
        foreach (self::TRACKED_ENTITIES as $entity) {
            $events[$entity . '.written'] = 'onEntityWritten';
        }

        return $events;
    }

    public function onEntityWritten(EntityWrittenEvent $event): void
    {
        $entityName = $event->getEntityName();
        $ids = $event->getIds();

        if (empty($ids)) {
            return;
        }

        $categoryIds = match ($entityName) {
            MerchRuleDefinition::ENTITY_NAME => $this->resolveCategoriesForRules($ids),
            MerchPinDefinition::ENTITY_NAME => $this->resolveCategoriesForPins($ids, $event),
            MerchFilterTemplateDefinition::ENTITY_NAME => $this->resolveCategoriesForFilterTemplates($ids),
            MerchSortingTemplateDefinition::ENTITY_NAME => $this->resolveCategoriesForSortingTemplates($ids),
            default => [],
        };

        if (empty($categoryIds)) {
            // Fallback: invalidate generic tag if no specific categories resolved
            $this->cacheInvalidator->invalidate(['strix-merch']);

            return;
        }

        $this->invalidateCategoryCaches($categoryIds);
    }

    /**
     * For MerchRule: look up strix_merch_rule_category junction table.
     *
     * @param array<string> $ruleIds
     *
     * @return array<string>
     */
    private function resolveCategoriesForRules(array $ruleIds): array
    {
        if (empty($ruleIds)) {
            return [];
        }

        $binaryIds = array_map(
            static fn (string $id): string => Uuid::fromHexToBytes($id),
            $ruleIds,
        );

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(category_id)) AS category_id
             FROM strix_merch_rule_category
             WHERE merch_rule_id IN (:ids)',
            ['ids' => $binaryIds],
            ['ids' => ArrayParameterType::STRING],
        );

        return array_column($rows, 'category_id');
    }

    /**
     * For MerchPin: the categoryId is directly on the entity payload.
     *
     * @param array<string> $pinIds
     *
     * @return array<string>
     */
    private function resolveCategoriesForPins(array $pinIds, EntityWrittenEvent $event): array
    {
        $categoryIds = [];

        // Try to extract categoryId from the write payloads first
        foreach ($event->getPayloads() as $payload) {
            if (isset($payload['categoryId'])) {
                $categoryIds[] = $payload['categoryId'];
            }
        }

        if (!empty($categoryIds)) {
            return array_unique($categoryIds);
        }

        // Fallback: query the database for existing pins
        $binaryIds = array_map(
            static fn (string $id): string => Uuid::fromHexToBytes($id),
            $pinIds,
        );

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(category_id)) AS category_id
             FROM strix_merch_pin
             WHERE id IN (:ids)',
            ['ids' => $binaryIds],
            ['ids' => ArrayParameterType::STRING],
        );

        return array_column($rows, 'category_id');
    }

    /**
     * For MerchFilterTemplate: look up strix_merch_filter_template_category junction table.
     *
     * @param array<string> $templateIds
     *
     * @return array<string>
     */
    private function resolveCategoriesForFilterTemplates(array $templateIds): array
    {
        if (empty($templateIds)) {
            return [];
        }

        $binaryIds = array_map(
            static fn (string $id): string => Uuid::fromHexToBytes($id),
            $templateIds,
        );

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(category_id)) AS category_id
             FROM strix_merch_filter_template_category
             WHERE filter_template_id IN (:ids)',
            ['ids' => $binaryIds],
            ['ids' => ArrayParameterType::STRING],
        );

        return array_column($rows, 'category_id');
    }

    /**
     * For MerchSortingTemplate: look up strix_merch_sorting_template_category junction table.
     *
     * @param array<string> $templateIds
     *
     * @return array<string>
     */
    private function resolveCategoriesForSortingTemplates(array $templateIds): array
    {
        if (empty($templateIds)) {
            return [];
        }

        $binaryIds = array_map(
            static fn (string $id): string => Uuid::fromHexToBytes($id),
            $templateIds,
        );

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(category_id)) AS category_id
             FROM strix_merch_sorting_template_category
             WHERE sorting_template_id IN (:ids)',
            ['ids' => $binaryIds],
            ['ids' => ArrayParameterType::STRING],
        );

        return array_column($rows, 'category_id');
    }

    /**
     * Invalidate HTTP cache for the given category IDs.
     * Uses the cache key pattern: strix_merch_{categoryId}_{salesChannelId}
     *
     * Since we do not know which sales channels are affected, we invalidate
     * all sales channel variants for each category.
     *
     * @param array<string> $categoryIds
     */
    private function invalidateCategoryCaches(array $categoryIds): void
    {
        $categoryIds = array_unique(array_filter($categoryIds));

        if (empty($categoryIds)) {
            return;
        }

        $tags = [];
        foreach ($categoryIds as $categoryId) {
            // Invalidate Shopware's native product listing route cache for this category
            // CachedProductListingRoute tags responses with this pattern
            $tags[] = 'product-listing-route-' . $categoryId;

            // Also invalidate the category navigation cache
            $tags[] = 'category-route-' . $categoryId;

            // Our own tag for any custom cache consumers
            $tags[] = 'strix_merch_' . $categoryId;
        }

        // Generic fallback tag
        $tags[] = 'strix-merch';

        $this->cacheInvalidator->invalidate($tags);
    }
}
