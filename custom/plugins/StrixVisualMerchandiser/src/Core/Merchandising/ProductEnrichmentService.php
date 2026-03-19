<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Merchandising;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

class ProductEnrichmentService
{
    private const BULK_CHUNK_SIZE = 500;
    private const POPULARITY_DECAY_HALFLIFE_DAYS = 14;

    public function __construct(
        private readonly EntityRepository $orderLineItemRepository,
        private readonly EntityRepository $clickAggregateRepository,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly Client $openSearchClient,
        private readonly ElasticsearchHelper $elasticsearchHelper,
        private readonly ProductDefinition $productDefinition,
    ) {
    }

    public function enrich(Context $context): void
    {
        $this->enrichSales30d();
        $this->enrichMarginPct();
        $this->enrichPopularity();
    }

    private function enrichSales30d(): void
    {
        $since = (new \DateTimeImmutable('-30 days'))->format('Y-m-d H:i:s');

        $sql = <<<'SQL'
            SELECT
                LOWER(HEX(oli.product_id)) AS product_id,
                COALESCE(SUM(oli.quantity), 0) AS total_quantity
            FROM order_line_item oli
            INNER JOIN `order` o ON o.id = oli.order_id AND o.version_id = oli.order_version_id
            WHERE oli.type = 'product'
              AND oli.product_id IS NOT NULL
              AND o.order_date_time >= :since
            GROUP BY oli.product_id
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql, ['since' => $since]);

        $data = [];
        foreach ($rows as $row) {
            $productId = (string) $row['product_id'];
            $totalQuantity = (int) $row['total_quantity'];

            $data[] = [
                'productId' => $productId,
                'field' => 'sales_30d',
                'value' => $totalQuantity,
            ];
        }

        $this->writeToOpenSearch($data);
    }

    private function enrichMarginPct(): void
    {
        // Only calculates margin for products WITH purchase prices.
        // Products without purchase_prices are excluded — they get no margin_pct
        // in the ES index, so the scoring script returns 0 for them.
        $sql = <<<'SQL'
            SELECT
                LOWER(HEX(p.id)) AS product_id,
                JSON_UNQUOTE(JSON_EXTRACT(p.price, '$[0].net')) AS net_price,
                JSON_UNQUOTE(JSON_EXTRACT(p.purchase_prices, '$[0].net')) AS purchase_price
            FROM product p
            WHERE p.purchase_prices IS NOT NULL
              AND JSON_EXTRACT(p.purchase_prices, '$[0].net') IS NOT NULL
              AND JSON_EXTRACT(p.purchase_prices, '$[0].net') > 0
              AND p.version_id = :liveVersionId
              AND p.parent_id IS NULL
        SQL;

        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $rows = $this->connection->fetchAllAssociative($sql, [
            'liveVersionId' => $liveVersionId,
        ]);

        $data = [];
        foreach ($rows as $row) {
            $productId = (string) $row['product_id'];
            $netPrice = (float) $row['net_price'];
            $purchasePrice = (float) $row['purchase_price'];

            if ($netPrice <= 0.0) {
                continue;
            }

            $marginPct = (($netPrice - $purchasePrice) / $netPrice) * 100.0;
            $marginPct = round($marginPct, 2);

            $data[] = [
                'productId' => $productId,
                'field' => 'margin_pct',
                'value' => $marginPct,
            ];
        }

        $this->writeToOpenSearch($data);
    }

    private function enrichPopularity(): void
    {
        $halflife = self::POPULARITY_DECAY_HALFLIFE_DAYS;

        $sql = <<<'SQL'
            SELECT
                ca.product_id AS product_id,
                SUM(
                    ca.total_clicks * EXP(
                        -0.693147 * DATEDIFF(CURDATE(), ca.date) / :halflife
                    )
                ) AS weighted_clicks
            FROM strix_merch_click_aggregate ca
            WHERE ca.date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY ca.product_id
            HAVING weighted_clicks > 0
            ORDER BY weighted_clicks DESC
        SQL;

        $rows = $this->connection->fetchAllAssociative($sql, ['halflife' => $halflife]);

        if (empty($rows)) {
            return;
        }

        $maxWeightedClicks = 0.0;
        foreach ($rows as $row) {
            $weighted = (float) $row['weighted_clicks'];
            if ($weighted > $maxWeightedClicks) {
                $maxWeightedClicks = $weighted;
            }
        }

        $data = [];
        foreach ($rows as $row) {
            $productId = (string) $row['product_id'];
            $weightedClicks = (float) $row['weighted_clicks'];

            $popularity = $maxWeightedClicks > 0.0
                ? log(1 + $weightedClicks) / log(1 + $maxWeightedClicks)
                : 0.0;

            $data[] = [
                'productId' => $productId,
                'field' => 'popularity',
                'value' => round($popularity, 4),
            ];
        }

        $this->writeToOpenSearch($data);
    }

    /**
     * Write enrichment data to the OpenSearch index via bulk update.
     *
     * Products without a value for the field (e.g. no purchase_prices → no margin_pct)
     * will simply not have that field in the ES document. The scoring script handles
     * this with `doc[field].size() > 0 ? ... : 0` — returning 0 for missing fields.
     *
     * @param array<int, array{productId: string, field: string, value: int|float}> $data
     */
    private function writeToOpenSearch(array $data): void
    {
        if (empty($data)) {
            return;
        }

        $indexName = $this->elasticsearchHelper->getIndexName($this->productDefinition);

        // Group by product to batch field updates
        $productUpdates = [];
        foreach ($data as $entry) {
            $productId = $entry['productId'];
            $productUpdates[$productId][$entry['field']] = $entry['value'];
        }

        // Process in chunks
        $chunks = array_chunk($productUpdates, self::BULK_CHUNK_SIZE, true);
        $totalWritten = 0;

        foreach ($chunks as $chunk) {
            $bulkBody = [];

            foreach ($chunk as $productId => $fields) {
                $bulkBody[] = [
                    'update' => [
                        '_index' => $indexName,
                        '_id' => $productId,
                    ],
                ];
                $bulkBody[] = [
                    'doc' => $fields,
                    'doc_as_upsert' => false,
                ];
            }

            try {
                $response = $this->openSearchClient->bulk(['body' => $bulkBody]);

                if ($response['errors'] ?? false) {
                    $errorCount = 0;
                    foreach ($response['items'] ?? [] as $item) {
                        if (isset($item['update']['error'])) {
                            $errorCount++;
                        }
                    }
                    $this->logger->warning('ProductEnrichmentService: bulk update had errors', [
                        'errorCount' => $errorCount,
                        'totalInChunk' => \count($chunk),
                    ]);
                }

                $totalWritten += \count($chunk);
            } catch (\Throwable $e) {
                $this->logger->error('ProductEnrichmentService: bulk update failed', [
                    'exception' => $e->getMessage(),
                    'productCount' => \count($chunk),
                ]);
            }
        }

        $this->logger->info('ProductEnrichmentService: wrote enrichment data', [
            'productCount' => $totalWritten,
            'fields' => array_unique(array_column($data, 'field')),
            'index' => $indexName,
        ]);
    }
}
