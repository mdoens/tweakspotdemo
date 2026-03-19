<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Core\Merchandising;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Strix\VisualMerchandiser\Core\Content\MerchCustomerSegment\MerchCustomerSegmentEntity;

class SegmentCalculator
{
    private const RECENCY_DECAY_DAYS = 90;

    public function __construct(
        private readonly EntityRepository $segmentRepository,
        private readonly EntityRepository $membershipRepository,
        private readonly EntityRepository $clickEventRepository,
        private readonly EntityRepository $orderLineItemRepository,
    ) {
    }

    public function recalculate(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));

        $segments = $this->segmentRepository->search($criteria, $context);

        foreach ($segments as $segment) {
            $memberCount = match ($segment->getType()) {
                'purchase_history' => $this->calculatePurchaseHistory($segment, $context),
                'click_behavior' => $this->calculateClickBehavior($segment, $context),
                'filter_behavior' => $this->calculateFilterBehavior($segment, $context),
                'combined' => $this->calculateCombined($segment, $context),
                default => null,
            };

            if ($memberCount !== null) {
                $this->updateSegmentMetadata($segment->getId(), $memberCount, $context);
            }
        }
    }

    private function updateSegmentMetadata(string $segmentId, int $customerCount, Context $context): void
    {
        $this->segmentRepository->update([
            [
                'id' => $segmentId,
                'customerCount' => $customerCount,
                'lastCalculatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ],
        ], $context);
    }

    private function calculatePurchaseHistory(MerchCustomerSegmentEntity $segment, Context $context): int
    {
        $config = $segment->getConfig();
        $categoryIds = $config['categoryIds'] ?? [];
        $lookbackDays = (int) ($config['lookbackDays'] ?? 90);

        $since = new \DateTimeImmutable(sprintf('-%d days', $lookbackDays));

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('order.orderDateTime', [
            RangeFilter::GTE => $since->format('Y-m-d H:i:s'),
        ]));
        $criteria->addFilter(new EqualsFilter('type', 'product'));
        $criteria->addAssociation('order');

        if (!empty($categoryIds)) {
            $criteria->addFilter(new EqualsFilter('product.categoriesRo.id', $categoryIds[0]));
            $criteria->addAssociation('product.categoriesRo');
        }

        $criteria->addAggregation(
            new TermsAggregation(
                'customers',
                'order.orderCustomerId',
                null,
                null,
                new CountAggregation('order_count', 'id'),
            ),
        );
        $criteria->addAggregation(
            new TermsAggregation(
                'customer_recency',
                'order.orderCustomerId',
                null,
                null,
                new MaxAggregation('last_order', 'order.orderDateTime'),
            ),
        );

        $result = $this->orderLineItemRepository->search($criteria, $context);

        $customersBucket = $result->getAggregations()->get('customers');
        $recencyBucket = $result->getAggregations()->get('customer_recency');

        if ($customersBucket === null || $recencyBucket === null) {
            return 0;
        }

        // Build recency map: customerId => last order date
        $recencyMap = [];
        foreach ($recencyBucket->getBuckets() as $bucket) {
            $recencyMap[$bucket->getKey()] = $bucket->getResult()->getMax();
        }

        // Find the maximum order count for normalization
        $maxCount = 1;
        foreach ($customersBucket->getBuckets() as $bucket) {
            $count = $bucket->getResult()->getCount();
            if ($count > $maxCount) {
                $maxCount = $count;
            }
        }

        $now = new \DateTimeImmutable();
        $upserts = [];

        foreach ($customersBucket->getBuckets() as $bucket) {
            $customerId = $bucket->getKey();
            $orderCount = $bucket->getResult()->getCount();

            // Frequency score: normalized 0-1 based on max orders in cohort
            $frequencyScore = $orderCount / $maxCount;

            // Recency score: exponential decay based on days since last order
            $recencyScore = 0.0;
            if (isset($recencyMap[$customerId])) {
                $lastOrderDate = $recencyMap[$customerId];
                if (\is_string($lastOrderDate)) {
                    $lastOrder = new \DateTimeImmutable($lastOrderDate);
                    $daysSinceLastOrder = (int) $now->diff($lastOrder)->days;
                    $recencyScore = exp(-$daysSinceLastOrder / self::RECENCY_DECAY_DAYS);
                }
            }

            // Combined score: 60% frequency, 40% recency
            $score = min(1.0, max(0.0, ($frequencyScore * 0.6) + ($recencyScore * 0.4)));

            $upserts[] = [
                'id' => Uuid::randomHex(),
                'segmentId' => $segment->getId(),
                'customerId' => $customerId,
                'score' => round($score, 4),
                'calculatedAt' => $now->format('Y-m-d H:i:s'),
            ];
        }

        $this->upsertMemberships($upserts, $segment->getId(), $context);

        return \count($upserts);
    }

    private function calculateClickBehavior(MerchCustomerSegmentEntity $segment, Context $context): int
    {
        $config = $segment->getConfig();
        $categoryIds = $config['categoryIds'] ?? [];
        $lookbackDays = (int) ($config['lookbackDays'] ?? 30);

        $since = new \DateTimeImmutable(sprintf('-%d days', $lookbackDays));

        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('createdAt', [
            RangeFilter::GTE => $since->format('Y-m-d H:i:s'),
        ]));
        // Only events with a logged-in customer
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsFilter('customerId', null),
            ]),
        );

        if (!empty($categoryIds)) {
            $criteria->addFilter(new EqualsFilter('categoryId', $categoryIds[0]));
        }

        // Include click, add_to_cart, purchase, wishlist events (not filter_use)
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('eventType', 'click'),
                new EqualsFilter('eventType', 'add_to_cart'),
                new EqualsFilter('eventType', 'purchase'),
                new EqualsFilter('eventType', 'wishlist'),
            ]),
        );

        $criteria->addAggregation(
            new TermsAggregation(
                'customers',
                'customerId',
                null,
                null,
                new CountAggregation('event_count', 'id'),
            ),
        );

        $result = $this->clickEventRepository->search($criteria, $context);
        $customersBucket = $result->getAggregations()->get('customers');

        if ($customersBucket === null) {
            return 0;
        }

        // Find max engagement for normalization
        $maxEngagement = 1;
        foreach ($customersBucket->getBuckets() as $bucket) {
            $count = $bucket->getResult()->getCount();
            if ($count > $maxEngagement) {
                $maxEngagement = $count;
            }
        }

        $now = new \DateTimeImmutable();
        $upserts = [];

        foreach ($customersBucket->getBuckets() as $bucket) {
            $customerId = $bucket->getKey();
            $eventCount = $bucket->getResult()->getCount();

            // Normalize engagement score to 0-1 using logarithmic scale to
            // avoid a small number of power users dominating the distribution
            $score = min(1.0, log(1 + $eventCount) / log(1 + $maxEngagement));

            $upserts[] = [
                'id' => Uuid::randomHex(),
                'segmentId' => $segment->getId(),
                'customerId' => $customerId,
                'score' => round($score, 4),
                'calculatedAt' => $now->format('Y-m-d H:i:s'),
            ];
        }

        $this->upsertMemberships($upserts, $segment->getId(), $context);

        return \count($upserts);
    }

    private function calculateFilterBehavior(MerchCustomerSegmentEntity $segment, Context $context): int
    {
        $config = $segment->getConfig();
        $categoryIds = $config['categoryIds'] ?? [];
        $lookbackDays = (int) ($config['lookbackDays'] ?? 30);

        $since = new \DateTimeImmutable(sprintf('-%d days', $lookbackDays));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('eventType', 'filter_use'));
        $criteria->addFilter(new RangeFilter('createdAt', [
            RangeFilter::GTE => $since->format('Y-m-d H:i:s'),
        ]));
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsFilter('customerId', null),
            ]),
        );

        if (!empty($categoryIds)) {
            $criteria->addFilter(new EqualsFilter('categoryId', $categoryIds[0]));
        }

        $criteria->addAggregation(
            new TermsAggregation(
                'customers',
                'customerId',
                null,
                null,
                new CountAggregation('filter_count', 'id'),
            ),
        );

        $result = $this->clickEventRepository->search($criteria, $context);
        $customersBucket = $result->getAggregations()->get('customers');

        if ($customersBucket === null) {
            return 0;
        }

        // Find max filter usage for normalization
        $maxUsage = 1;
        foreach ($customersBucket->getBuckets() as $bucket) {
            $count = $bucket->getResult()->getCount();
            if ($count > $maxUsage) {
                $maxUsage = $count;
            }
        }

        $now = new \DateTimeImmutable();
        $upserts = [];

        foreach ($customersBucket->getBuckets() as $bucket) {
            $customerId = $bucket->getKey();
            $filterCount = $bucket->getResult()->getCount();

            // Filter-heavy users are likely more intentional shoppers
            $score = min(1.0, log(1 + $filterCount) / log(1 + $maxUsage));

            $upserts[] = [
                'id' => Uuid::randomHex(),
                'segmentId' => $segment->getId(),
                'customerId' => $customerId,
                'score' => round($score, 4),
                'calculatedAt' => $now->format('Y-m-d H:i:s'),
            ];
        }

        $this->upsertMemberships($upserts, $segment->getId(), $context);

        return \count($upserts);
    }

    private function calculateCombined(MerchCustomerSegmentEntity $segment, Context $context): int
    {
        $config = $segment->getConfig();
        $sourceSegmentIds = $config['sourceSegmentIds'] ?? [];
        $weights = $config['weights'] ?? [];

        if (empty($sourceSegmentIds)) {
            return 0;
        }

        // Load existing memberships from source segments
        /** @var array<string, array<string, float>> $customerScores customerId => [segmentId => score] */
        $customerScores = [];

        foreach ($sourceSegmentIds as $sourceSegmentId) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('segmentId', $sourceSegmentId));
            $criteria->setLimit(10000);

            $memberships = $this->membershipRepository->search($criteria, $context);

            foreach ($memberships as $membership) {
                $customerId = $membership->get('customerId');
                $score = $membership->get('score') ?? 0.0;
                $customerScores[$customerId][$sourceSegmentId] = (float) $score;
            }
        }

        if (empty($customerScores)) {
            return 0;
        }

        // Calculate default equal weight if no specific weights are configured
        $segmentCount = \count($sourceSegmentIds);
        $defaultWeight = 1.0 / $segmentCount;

        $now = new \DateTimeImmutable();
        $upserts = [];

        foreach ($customerScores as $customerId => $scores) {
            $totalWeight = 0.0;
            $weightedSum = 0.0;

            foreach ($sourceSegmentIds as $sourceSegmentId) {
                $weight = (float) ($weights[$sourceSegmentId] ?? $defaultWeight);
                $sourceScore = $scores[$sourceSegmentId] ?? 0.0;

                $weightedSum += $sourceScore * $weight;
                $totalWeight += $weight;
            }

            $combinedScore = $totalWeight > 0.0 ? $weightedSum / $totalWeight : 0.0;
            $combinedScore = min(1.0, max(0.0, $combinedScore));

            $upserts[] = [
                'id' => Uuid::randomHex(),
                'segmentId' => $segment->getId(),
                'customerId' => $customerId,
                'score' => round($combinedScore, 4),
                'calculatedAt' => $now->format('Y-m-d H:i:s'),
            ];
        }

        $this->upsertMemberships($upserts, $segment->getId(), $context);

        return \count($upserts);
    }

    /**
     * Upsert membership records. First deletes existing memberships for the segment,
     * then creates new ones with updated scores.
     *
     * @param array<int, array{id: string, segmentId: string, customerId: string, score: float, calculatedAt: string}> $upserts
     */
    private function upsertMemberships(array $upserts, string $segmentId, Context $context): void
    {
        if (empty($upserts)) {
            return;
        }

        // Load existing memberships for this segment to build delete + upsert
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('segmentId', $segmentId));
        $criteria->setLimit(50000);

        $existing = $this->membershipRepository->search($criteria, $context);

        // Build a map of existing customerId => membershipId for reuse
        $existingMap = [];
        foreach ($existing as $membership) {
            $existingMap[$membership->get('customerId')] = $membership->getId();
        }

        // Reuse existing IDs where possible to perform updates instead of inserts
        foreach ($upserts as &$upsert) {
            if (isset($existingMap[$upsert['customerId']])) {
                $upsert['id'] = $existingMap[$upsert['customerId']];
            }
        }
        unset($upsert);

        // Batch upsert in chunks of 250 to avoid memory issues
        foreach (array_chunk($upserts, 250) as $chunk) {
            $this->membershipRepository->upsert($chunk, $context);
        }
    }
}
