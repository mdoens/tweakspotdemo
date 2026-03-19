<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Subscriber;

use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\Compound\FunctionScoreQuery;
use OpenSearchDSL\Query\TermLevel\IdsQuery;
use OpenSearchDSL\Sort\FieldSort;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntitySearcherSearchEvent;
use Strix\VisualMerchandiser\Core\Content\MerchPin\MerchPinCollection;
use Strix\VisualMerchandiser\Core\Content\MerchPin\MerchPinEntity;
use Strix\VisualMerchandiser\Core\Content\MerchRule\MerchRuleCollection;
use Strix\VisualMerchandiser\Core\Merchandising\PersonalizationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ElasticsearchQuerySubscriber implements EventSubscriberInterface
{
    /**
     * Fields that can be used directly as ES document fields.
     * 'price' is handled separately via dynamic cheapest_price mapping.
     */
    private const ALLOWED_FIELDS = [
        'stock', 'price', 'sales', 'ratingAverage', 'releaseDate',
        'createdAt', 'sales_30d', 'margin_pct', 'popularity',
    ];

    public function __construct(
        private readonly EntityRepository $merchRuleRepository,
        private readonly EntityRepository $merchPinRepository,
        private readonly PersonalizationService $personalizationService,
    ) {
    }

    /**
     * Resolves 'price' to the actual ES field name: cheapest_price_ruledefault_currency{id}_gross.
     * Other fields are returned as-is.
     */
    private function resolveFieldName(string $field, Context $context): string
    {
        if ($field !== 'price') {
            return $field;
        }

        // Get the default currency ID from context or system defaults
        $currencyId = $context->getCurrencyId();

        return 'cheapest_price_ruledefault_currency' . str_replace('-', '', $currencyId) . '_gross';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ElasticsearchEntitySearcherSearchEvent::class => ['onSearch', 500],
        ];
    }

    public function onSearch(ElasticsearchEntitySearcherSearchEvent $event): void
    {
        if ($event->getDefinition()->getEntityName() !== ProductDefinition::ENTITY_NAME) {
            return;
        }

        $categoryId = $this->extractCategoryId($event->getCriteria());
        if ($categoryId === null) {
            return;
        }

        $context = $event->getContext();
        $rules = $this->getActiveRules($categoryId, $context);
        $pins = $this->getActivePins($categoryId, $context);

        $hasCustomer = $event->getCriteria()->getExtension('merch_customer') !== null;

        if ($rules->count() === 0 && $pins->count() === 0 && !$hasCustomer) {
            return;
        }

        $search = $event->getSearch();

        // Collect existing queries
        $existingQueries = $search->getQueries();
        $baseQuery = new BoolQuery();
        foreach ($existingQueries as $query) {
            $baseQuery->add($query, BoolQuery::MUST);
        }

        $pinnedIds = [];
        if ($pins->count() > 0) {
            $pinnedIds = array_values($pins->map(fn (MerchPinEntity $pin) => $pin->getProductId()));
        }

        // Wrap in function_score
        $functionScoreQuery = new FunctionScoreQuery($baseQuery);
        $hasFunction = false;

        // Apply rule scoring
        foreach ($rules as $rule) {
            match ($rule->getType()) {
                'weighted_sort' => $hasFunction = $this->applyWeightedSort($functionScoreQuery, $rule->getConfig(), $context) || $hasFunction,
                'boost' => $hasFunction = $this->applyBoost($functionScoreQuery, $rule->getConfig()) || $hasFunction,
                'bury' => $hasFunction = $this->applyBury($functionScoreQuery, $rule->getConfig()) || $hasFunction,
                default => null,
            };
        }

        // Massive boost for pinned products so they always appear on page 1
        if (!empty($pinnedIds)) {
            $functionScoreQuery->addWeightFunction(10000.0, new IdsQuery($pinnedIds));
            $hasFunction = true;
        }

        // Apply personalization boosts for logged-in customers
        $customerExt = $event->getCriteria()->getExtension('merch_customer');
        if ($customerExt !== null) {
            $customerId = $customerExt->get('id');
            $salesChannelId = $customerExt->get('salesChannelId');

            if (\is_string($customerId) && \is_string($salesChannelId)) {
                $boosts = $this->personalizationService->getBoostsForCustomer(
                    $customerId,
                    $salesChannelId,
                    $context,
                );

                foreach ($boosts as $boost) {
                    $functionScoreQuery->addWeightFunction(
                        $boost['boostFactor'],
                        new \OpenSearchDSL\Query\TermLevel\TermQuery(
                            'customerSegmentIds',
                            $boost['segmentId'],
                        ),
                    );
                    $hasFunction = true;
                }
            }
        }

        if (!$hasFunction) {
            return;
        }

        $functionScoreQuery->addParameter('score_mode', 'sum');
        $functionScoreQuery->addParameter('boost_mode', 'replace');

        // Replace search query
        $search->destroyEndpoint('query');
        $search->addQuery($functionScoreQuery);

        // Sort by score (our function_score) instead of Shopware's default sort
        $search->destroyEndpoint('sort');
        $search->addSort(new FieldSort('_score', 'desc'));
    }

    private function extractCategoryId(Criteria $criteria): ?string
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsFilter && $filter->getField() === 'product.categoriesRo.id') {
                return (string) $filter->getValue();
            }
        }

        return null;
    }

    private function getActiveRules(string $categoryId, Context $context): MerchRuleCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('categories.id', $categoryId));
        $criteria->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING));
        $criteria->setLimit(50);

        /** @var MerchRuleCollection $rules */
        $rules = $this->merchRuleRepository->search($criteria, $context)->getEntities();

        return $rules;
    }

    private function getActivePins(string $categoryId, Context $context): MerchPinCollection
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('categoryId', $categoryId));
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('validFrom', null),
            new RangeFilter('validFrom', [RangeFilter::LTE => $now]),
        ]));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('validUntil', null),
            new RangeFilter('validUntil', [RangeFilter::GTE => $now]),
        ]));
        $criteria->setLimit(100);

        /** @var MerchPinCollection $pins */
        $pins = $this->merchPinRepository->search($criteria, $context)->getEntities();

        return $pins;
    }

    private function applyWeightedSort(FunctionScoreQuery $query, array $config, Context $context): bool
    {
        $factors = $config['factors'] ?? [];
        $added = false;

        foreach ($factors as $factor) {
            $field = $factor['field'] ?? '';
            if (!\in_array($field, self::ALLOWED_FIELDS, true)) {
                continue;
            }

            // Resolve dynamic field names (e.g. 'price' → 'cheapest_price_ruledefault_currency..._gross')
            $esField = $this->resolveFieldName($field, $context);

            $weight = (float) ($factor['weight'] ?? 0);
            if ($weight <= 0) {
                continue;
            }

            $direction = $factor['direction'] ?? 'desc';

            // OpenSearch script_score CANNOT produce negative values.
            // For desc: use the raw value (higher = better score)
            // For asc: use 1/(1+value) to invert (lower value = higher score)
            // OpenSearch script_score CANNOT produce negative values.
            // For desc: higher value = higher score (direct)
            // For asc: lower value = higher score (inverted via large_constant - value)
            // Using 10M as ceiling ensures all realistic values stay positive
            if ($direction === 'desc') {
                $script = \sprintf(
                    "doc['%s'].size() > 0 ? doc['%s'].value : 0",
                    $esField,
                    $esField,
                );
            } else {
                $script = \sprintf(
                    "doc['%s'].size() > 0 ? Math.max(0, 10000000 - doc['%s'].value) : 10000000",
                    $esField,
                    $esField,
                );
            }

            $query->addSimpleFunction([
                'script_score' => [
                    'script' => [
                        'lang' => 'painless',
                        'source' => $script,
                    ],
                ],
                'weight' => $weight / 100,
            ]);
            $added = true;
        }

        return $added;
    }

    private function applyBoost(FunctionScoreQuery $query, array $config): bool
    {
        $multiplier = (float) ($config['multiplier'] ?? 1.0);
        $filterQuery = $this->buildCriteriaFilter($config['criteria'] ?? []);

        if ($filterQuery === null) {
            return false;
        }

        $query->addWeightFunction($multiplier, $filterQuery);

        return true;
    }

    private function applyBury(FunctionScoreQuery $query, array $config): bool
    {
        $multiplier = (float) ($config['multiplier'] ?? 1.0);
        $filterQuery = $this->buildCriteriaFilter($config['criteria'] ?? []);

        if ($filterQuery === null) {
            return false;
        }

        $query->addWeightFunction($multiplier, $filterQuery);

        return true;
    }

    /**
     * Convert admin criteria (field + value) to the correct OpenSearch filter query.
     *
     * The admin stores fields as:
     * - "manufacturerId" → value is manufacturer UUID
     * - "tagIds" → value is tag UUID
     * - "propertyIds:{groupId}" → value is property option UUID
     *
     * All use TermQuery on the ES keyword field with UUID values.
     * This is the most reliable matching — no text search ambiguity.
     */
    private function buildCriteriaFilter(array $criteria): ?\OpenSearchDSL\BuilderInterface
    {
        $field = $criteria['field'] ?? null;
        $value = $criteria['value'] ?? null;

        if ($field === null || $value === null || $value === '') {
            return null;
        }

        // Property group: "propertyIds:{groupId}" → TermQuery on "propertyIds" with option UUID
        if (str_starts_with($field, 'propertyIds:')) {
            return new \OpenSearchDSL\Query\TermLevel\TermQuery('propertyIds', $value);
        }

        // Manufacturer: TermQuery on "manufacturerId" with manufacturer UUID
        if ($field === 'manufacturerId') {
            return new \OpenSearchDSL\Query\TermLevel\TermQuery('manufacturerId', $value);
        }

        // Tags: TermQuery on "tagIds" with tag UUID
        if ($field === 'tagIds') {
            return new \OpenSearchDSL\Query\TermLevel\TermQuery('tagIds', $value);
        }

        // Legacy support: old-format fields (manufacturer.name, tags.name, properties.*)
        if ($field === 'manufacturer.name') {
            return new \OpenSearchDSL\Query\FullText\MatchQuery('manufacturer.name', $value);
        }
        if ($field === 'tags.name') {
            return new \OpenSearchDSL\Query\FullText\MatchQuery('tags.name', $value);
        }

        // Default: direct TermQuery
        return new \OpenSearchDSL\Query\TermLevel\TermQuery($field, $value);
    }
}
