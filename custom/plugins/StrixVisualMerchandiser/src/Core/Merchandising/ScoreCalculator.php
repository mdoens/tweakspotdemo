<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Merchandising;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\Compound\FunctionScoreQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Strix\VisualMerchandiser\Core\Content\MerchRule\MerchRuleCollection;

class ScoreCalculator
{
    private const ALLOWED_FIELDS = [
        'stock', 'price', 'sales', 'ratingAverage', 'releaseDate',
        'createdAt', 'sales_30d', 'margin_pct', 'popularity',
    ];

    public function __construct(
        private readonly EntityRepository $merchRuleRepository,
        private readonly EntityRepository $merchRuleCategoryRepository,
    ) {
    }

    public function apply(BuilderInterface $query, Criteria $criteria, Context $context): BuilderInterface
    {
        $categoryId = $this->extractCategoryId($criteria);
        if ($categoryId === null) {
            return $query;
        }

        $rules = $this->getActiveRules($categoryId, $context);
        if ($rules->count() === 0) {
            return $query;
        }

        $functionScoreQuery = new FunctionScoreQuery($query);

        foreach ($rules as $rule) {
            match ($rule->getType()) {
                'weighted_sort' => $this->applyWeightedSort($functionScoreQuery, $rule->getConfig()),
                'boost' => $this->applyBoost($functionScoreQuery, $rule->getConfig()),
                'bury' => $this->applyBury($functionScoreQuery, $rule->getConfig()),
                'formula_order' => $this->applyFormulaOrder($functionScoreQuery, $rule->getConfig()),
                default => null,
            };
        }

        return $functionScoreQuery;
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

    private function applyWeightedSort(FunctionScoreQuery $query, array $config): void
    {
        $factors = $config['factors'] ?? [];

        foreach ($factors as $factor) {
            $field = $factor['field'];
            if (!\in_array($field, self::ALLOWED_FIELDS, true)) {
                continue;
            }
            $weight = (float) ($factor['weight'] ?? 0);
            $direction = $factor['direction'] ?? 'desc';

            $modifier = $direction === 'desc' ? 1 : -1;

            $query->addFunction(
                'script_score',
                [
                    'script' => [
                        'source' => sprintf(
                            "doc['%s'].size() > 0 ? doc['%s'].value * %s : 0",
                            $field,
                            $field,
                            $modifier,
                        ),
                    ],
                ],
                null,
                $weight / 100,
            );
        }

        $query->addParameter('score_mode', 'sum');
        $query->addParameter('boost_mode', 'replace');
    }

    private function applyBoost(FunctionScoreQuery $query, array $config): void
    {
        $multiplier = (float) ($config['multiplier'] ?? 1.0);
        $filterQuery = $this->buildCriteriaFilter($config['criteria'] ?? []);

        if ($filterQuery !== null) {
            $query->addFunction('weight', ['weight' => $multiplier], $filterQuery);
        }
    }

    private function applyBury(FunctionScoreQuery $query, array $config): void
    {
        $multiplier = (float) ($config['multiplier'] ?? 1.0);
        $filterQuery = $this->buildCriteriaFilter($config['criteria'] ?? []);

        if ($filterQuery !== null) {
            $query->addFunction('weight', ['weight' => $multiplier], $filterQuery);
        }
    }

    private function applyFormulaOrder(FunctionScoreQuery $query, array $config): void
    {
        $steps = $config['steps'] ?? [];

        if (empty($steps)) {
            return;
        }

        // Each step gets a large additive score based on its position.
        // "first" steps boost matching products to the top.
        // "last" steps bury matching products to the bottom.
        // The step priority decreases with index (first step = most important).
        $totalSteps = \count($steps);

        foreach ($steps as $index => $step) {
            $field = $step['field'] ?? null;
            $operator = $step['operator'] ?? '=';
            $value = $step['value'] ?? null;
            $direction = $step['direction'] ?? 'first';

            if ($field === null || $value === null) {
                continue;
            }

            if (!\in_array($field, self::ALLOWED_FIELDS, true)) {
                continue;
            }

            // Higher-priority steps (lower index) get a larger additive score
            $stepWeight = ($totalSteps - $index) * 10000;

            $scriptSource = $this->buildFormulaStepScript($field, $operator, $value, $direction, $stepWeight);

            $query->addFunction(
                'script_score',
                [
                    'script' => [
                        'source' => $scriptSource,
                        'params' => [
                            'stepWeight' => $stepWeight,
                            'compareValue' => $value,
                        ],
                    ],
                ],
            );
        }

        // formula_order overrides all other scoring
        $query->addParameter('score_mode', 'sum');
        $query->addParameter('boost_mode', 'replace');
    }

    private function buildFormulaStepScript(
        string $field,
        string $operator,
        string $value,
        string $direction,
        int $stepWeight,
    ): string {
        $condition = match ($operator) {
            '=' => sprintf(
                "doc['%s'].size() > 0 && doc['%s'].value == params.compareValue",
                $field,
                $field,
            ),
            '!=' => sprintf(
                "doc['%s'].size() > 0 && doc['%s'].value != params.compareValue",
                $field,
                $field,
            ),
            '>' => sprintf(
                "doc['%s'].size() > 0 && doc['%s'].value > Double.parseDouble(params.compareValue)",
                $field,
                $field,
            ),
            '>=' => sprintf(
                "doc['%s'].size() > 0 && doc['%s'].value >= Double.parseDouble(params.compareValue)",
                $field,
                $field,
            ),
            '<' => sprintf(
                "doc['%s'].size() > 0 && doc['%s'].value < Double.parseDouble(params.compareValue)",
                $field,
                $field,
            ),
            '<=' => sprintf(
                "doc['%s'].size() > 0 && doc['%s'].value <= Double.parseDouble(params.compareValue)",
                $field,
                $field,
            ),
            'contains' => sprintf(
                "doc['%s'].size() > 0 && doc['%s'].value.toString().contains(params.compareValue)",
                $field,
                $field,
            ),
            default => sprintf(
                "doc['%s'].size() > 0 && doc['%s'].value == params.compareValue",
                $field,
                $field,
            ),
        };

        // "first" → matching products get a large positive score (pushed to top)
        // "last"  → matching products get 0, non-matching get the large score (pushed to bottom)
        if ($direction === 'first') {
            return sprintf('(%s) ? params.stepWeight : 0', $condition);
        }

        // direction === 'last': invert — non-matching products get boosted
        return sprintf('(%s) ? 0 : params.stepWeight', $condition);
    }

    private function buildCriteriaFilter(array $criteria): ?BuilderInterface
    {
        $field = $criteria['field'] ?? null;
        $value = $criteria['value'] ?? null;

        if ($field === null || $value === null) {
            return null;
        }

        return new TermQuery($field, $value);
    }
}
