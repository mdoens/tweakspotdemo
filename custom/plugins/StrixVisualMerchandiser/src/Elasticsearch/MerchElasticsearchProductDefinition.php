<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Elasticsearch;

use OpenSearchDSL\BuilderInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;

class MerchElasticsearchProductDefinition extends AbstractElasticsearchDefinition
{
    public function __construct(
        private readonly AbstractElasticsearchDefinition $inner,
        private readonly ProductDefinition $productDefinition,
    ) {
    }

    public function getDecorated(): AbstractElasticsearchDefinition
    {
        return $this->inner;
    }

    public function getEntityDefinition(): EntityDefinition
    {
        return $this->productDefinition;
    }

    public function getMapping(Context $context): array
    {
        $mapping = $this->inner->getMapping($context);

        $mapping['properties']['sales_30d'] = ['type' => 'integer'];
        $mapping['properties']['margin_pct'] = ['type' => 'float'];
        $mapping['properties']['popularity'] = ['type' => 'float'];

        return $mapping;
    }

    public function buildTermQuery(Context $context, Criteria $criteria): BuilderInterface
    {
        return $this->inner->buildTermQuery($context, $criteria);
    }

    public function fetch(array $ids, Context $context): array
    {
        $documents = $this->inner->fetch($ids, $context);

        foreach ($documents as &$document) {
            $document['sales_30d'] = $document['sales_30d'] ?? 0;
            $document['margin_pct'] = $document['margin_pct'] ?? 0.0;
            $document['popularity'] = $document['popularity'] ?? 0.0;
        }

        return $documents;
    }
}
