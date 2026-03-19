<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Merchandising;

use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Strix\VisualMerchandiser\Core\Content\MerchFilterTemplate\MerchFilterTemplateEntity;
use Strix\VisualMerchandiser\Core\Content\MerchFilterTemplateCategory\MerchFilterTemplateCategoryEntity;

class FilterOverrideService
{
    private const MAX_INHERITANCE_DEPTH = 20;

    /**
     * Maps built-in filter type identifiers to their Shopware aggregation name prefixes.
     * Property group filters use the pattern "properties-{groupId}".
     */
    private const BUILTIN_FILTER_AGGREGATION_MAP = [
        'manufacturer' => 'manufacturer',
        'price' => 'price',
        'rating' => 'rating',
        'shipping-free' => 'shipping-free',
    ];

    public function __construct(
        private readonly EntityRepository $filterTemplateCategoryRepository,
        private readonly EntityRepository $filterTemplateRepository,
        private readonly EntityRepository $categoryRepository,
    ) {
    }

    public function apply(string $categoryId, ProductListingResult $result, SalesChannelContext $context): void
    {
        $template = $this->resolveTemplate($categoryId, $context);
        if ($template === null) {
            return;
        }

        try {
            $this->overrideFilters($template, $result);
        } catch (\Throwable $e) {
            // Filter override should never crash the storefront — fail gracefully
            // and leave Shopware's default filters intact
        }
    }

    private function resolveTemplate(string $categoryId, SalesChannelContext $context): ?MerchFilterTemplateEntity
    {
        $assignment = $this->findAssignment($categoryId, $context);
        if ($assignment === null) {
            // No assignment at all — inherit from parent (same as 'inherit' mode)
            return $this->resolveInherited($categoryId, $context);
        }

        return match ($assignment->getOverrideMode()) {
            'active' => $this->loadTemplate($assignment->getFilterTemplateId(), $context),
            'disabled' => null,
            'inherit' => $this->resolveInherited($categoryId, $context),
            default => null,
        };
    }

    /**
     * Walk up the category tree using the `path` field (single query) to find
     * the first parent with an active or disabled assignment.
     */
    private function resolveInherited(string $categoryId, SalesChannelContext $context): ?MerchFilterTemplateEntity
    {
        // Load the category to get its `path` — contains all ancestor IDs in order
        $criteria = new Criteria([$categoryId]);
        $category = $this->categoryRepository->search($criteria, $context->getContext())->first();

        if ($category === null) {
            return null;
        }

        $path = $category->getPath();
        if ($path === null || $path === '') {
            return null;
        }

        // Path format: "|rootId|parentId|grandparentId|" — split and reverse (nearest parent first)
        $ancestorIds = array_filter(explode('|', $path));
        $ancestorIds = array_reverse($ancestorIds);

        if (empty($ancestorIds)) {
            return null;
        }

        // Batch-load all assignments for ancestor categories in a single query
        $assignmentCriteria = new Criteria();
        $assignmentCriteria->addFilter(new EqualsAnyFilter('categoryId', $ancestorIds));
        $assignments = $this->filterTemplateCategoryRepository->search($assignmentCriteria, $context->getContext());

        // Index by categoryId for fast lookup
        $assignmentMap = [];
        foreach ($assignments as $assignment) {
            $assignmentMap[$assignment->getCategoryId()] = $assignment;
        }

        // Walk ancestors from nearest to root
        foreach ($ancestorIds as $ancestorId) {
            $assignment = $assignmentMap[$ancestorId] ?? null;
            if ($assignment === null) {
                continue;
            }

            $mode = $assignment->getOverrideMode();

            if ($mode === 'active') {
                return $this->loadTemplate($assignment->getFilterTemplateId(), $context);
            }

            if ($mode === 'disabled') {
                return null;
            }

            // 'inherit' → continue to next ancestor
        }

        return null;
    }

    private function findAssignment(string $categoryId, SalesChannelContext $context): ?MerchFilterTemplateCategoryEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('categoryId', $categoryId));
        $criteria->setLimit(1);

        return $this->filterTemplateCategoryRepository
            ->search($criteria, $context->getContext())
            ->first();
    }

    private function loadTemplate(string $templateId, SalesChannelContext $context): ?MerchFilterTemplateEntity
    {
        $criteria = new Criteria([$templateId]);
        $criteria->addFilter(new EqualsFilter('active', true));

        return $this->filterTemplateRepository
            ->search($criteria, $context->getContext())
            ->first();
    }

    private function overrideFilters(MerchFilterTemplateEntity $template, ProductListingResult $result): void
    {
        $filterConfig = $template->getFilters();

        if (empty($filterConfig)) {
            return;
        }

        // Build lookup maps: aggregation name → filter config
        // Keyed by the aggregation name Shopware uses for each filter type
        $configByAggregationName = $this->buildAggregationConfigMap($filterConfig);

        // Step 1: Remove aggregations not in the template + remove inactive/invisible filters
        $namesToRemove = [];
        foreach ($result->getAggregations() as $aggregation) {
            $name = $aggregation->getName();

            if (!$this->isFilterAggregation($name)) {
                // Not a filter aggregation (e.g. internal Shopware aggregation) — leave untouched
                continue;
            }

            $config = $configByAggregationName[$name] ?? null;

            if ($config === null) {
                // Aggregation not in template config — remove
                $namesToRemove[] = $name;
                continue;
            }

            // Remove if explicitly inactive or invisible
            $active = $config['active'] ?? true;
            $visible = $config['visible'] ?? true;

            if (!$active || !$visible) {
                $namesToRemove[] = $name;
            }
        }

        foreach ($namesToRemove as $name) {
            $result->getAggregations()->remove($name);
        }

        // Step 2: Apply per-filter config extensions and maxValues
        foreach ($result->getAggregations() as $aggregation) {
            $name = $aggregation->getName();
            $config = $configByAggregationName[$name] ?? null;

            if ($config === null) {
                continue;
            }

            // Add extension with the full filter config for storefront templates
            $aggregation->addExtension('merch_filter_config', new ArrayStruct([
                'displayType' => $config['displayType'] ?? 'checkbox',
                'collapsed' => $config['collapsed'] ?? false,
                'customLabel' => $config['customLabel'] ?? null,
                'helpText' => $config['helpText'] ?? null,
                'maxValues' => $config['maxValues'] ?? null,
                'position' => $config['position'] ?? 0,
            ]));

            // Apply maxValues: slice bucket results to the configured limit
            $maxValues = $config['maxValues'] ?? null;
            if ($maxValues !== null && $maxValues > 0 && $aggregation instanceof TermsResult) {
                $buckets = $aggregation->getBuckets();
                if (\count($buckets) > $maxValues) {
                    $aggregation->assign([
                        'buckets' => \array_slice($buckets, 0, $maxValues),
                    ]);
                }
            }
        }

        // Step 3: Reorder aggregations by template position
        $this->reorderAggregations($result, $configByAggregationName);
    }

    /**
     * Builds a map from Shopware aggregation name to the filter config array.
     *
     * Property group filters map to "properties-{propertyGroupId}".
     * Built-in filters (manufacturer, price, rating, shipping-free) map to their Shopware aggregation name.
     *
     * @param array<int, array<string, mixed>> $filterConfig
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildAggregationConfigMap(array $filterConfig): array
    {
        $map = [];

        foreach ($filterConfig as $filter) {
            $type = $filter['type'] ?? null;
            $propertyGroupId = $filter['propertyGroupId'] ?? null;

            if ($type !== null && isset(self::BUILTIN_FILTER_AGGREGATION_MAP[$type])) {
                // Built-in filter type: manufacturer, price, rating, shipping-free
                $aggregationName = self::BUILTIN_FILTER_AGGREGATION_MAP[$type];
                $map[$aggregationName] = $filter;
            } elseif ($propertyGroupId !== null) {
                // Property group filter
                $aggregationName = 'properties-' . $propertyGroupId;
                $map[$aggregationName] = $filter;
            }
        }

        return $map;
    }

    /**
     * Determines if an aggregation name belongs to a storefront filter.
     */
    private function isFilterAggregation(string $name): bool
    {
        if (str_starts_with($name, 'properties-')) {
            return true;
        }

        return isset(self::BUILTIN_FILTER_AGGREGATION_MAP[$name]);
    }

    /**
     * Reorders the aggregation collection to match the template's position values.
     * Aggregations without a configured position retain their relative order at the end.
     *
     * @param array<string, array<string, mixed>> $configByAggregationName
     */
    private function reorderAggregations(ProductListingResult $result, array $configByAggregationName): void
    {
        $aggregations = $result->getAggregations();

        // Separate filter aggregations (that have a position) from non-filter aggregations
        $filterAggregations = [];
        $otherAggregations = [];

        foreach ($aggregations as $aggregation) {
            $name = $aggregation->getName();
            $config = $configByAggregationName[$name] ?? null;

            if ($config !== null) {
                $filterAggregations[] = [
                    'aggregation' => $aggregation,
                    'position' => $config['position'] ?? \PHP_INT_MAX,
                ];
            } else {
                $otherAggregations[] = $aggregation;
            }
        }

        // Sort filter aggregations by position
        usort($filterAggregations, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        // Rebuild the aggregation collection: positioned filters first, then other aggregations
        $sorted = new AggregationResultCollection();

        foreach ($filterAggregations as $item) {
            /** @var AggregationResult $agg */
            $agg = $item['aggregation'];
            $sorted->add($agg);
        }

        foreach ($otherAggregations as $agg) {
            $sorted->add($agg);
        }

        $result->assign(['aggregations' => $sorted]);
    }
}
