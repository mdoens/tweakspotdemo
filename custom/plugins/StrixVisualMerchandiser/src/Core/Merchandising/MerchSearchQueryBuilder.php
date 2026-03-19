<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Merchandising;

use OpenSearchDSL\BuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Elasticsearch\Product\AbstractProductSearchQueryBuilder;

/**
 * Decorator on AbstractProductSearchQueryBuilder.
 *
 * All merchandising scoring (weighted_sort, boost, bury, pins, personalization)
 * is handled by ElasticsearchQuerySubscriber which listens to
 * ElasticsearchEntitySearcherSearchEvent. This covers BOTH search and category
 * listing queries.
 *
 * This decorator is intentionally a pass-through to avoid double-applying
 * function_score modifications. It remains registered so the decoration chain
 * is intact and can be extended in the future if needed.
 */
class MerchSearchQueryBuilder extends AbstractProductSearchQueryBuilder
{
    public function __construct(
        private readonly AbstractProductSearchQueryBuilder $inner,
    ) {
    }

    public function getDecorated(): AbstractProductSearchQueryBuilder
    {
        return $this->inner;
    }

    public function build(Criteria $criteria, Context $context): BuilderInterface
    {
        return $this->inner->build($criteria, $context);
    }
}
