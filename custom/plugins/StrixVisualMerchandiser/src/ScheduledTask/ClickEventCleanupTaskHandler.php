<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\ScheduledTask;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: ClickEventCleanupTask::class)]
class ClickEventCleanupTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public function run(): void
    {
        $this->aggregateEvents();
        $this->cleanupOldEvents();
    }

    /**
     * Aggregate raw click events into daily summaries (MerchClickAggregate).
     * Groups by category_id + product_id + date → total_clicks, avg_position, ctr.
     */
    private function aggregateEvents(): void
    {
        $this->logger->info('ClickEventCleanupTask: starting event aggregation');

        try {
            // Aggregate raw events that haven't been aggregated yet
            // (events from the last 24 hours, or all events if first run)
            // Use a subquery to avoid ONLY_FULL_GROUP_BY issues with the ID column
            $rows = $this->connection->fetchAllAssociative(
                'SELECT category_id, product_id, DATE(created_at) AS d, COUNT(*) AS cnt, AVG(position) AS avgp, sales_channel_id
                 FROM strix_merch_click_event
                 WHERE event_type = :type
                 GROUP BY category_id, product_id, DATE(created_at), sales_channel_id',
                ['type' => 'click'],
            );

            $stmt = $this->connection->prepare(
                'INSERT INTO strix_merch_click_aggregate (id, category_id, product_id, date, total_clicks, avg_position, ctr, sales_channel_id, created_at, updated_at)
                 VALUES (UNHEX(:id), :catId, :prodId, :date, :cnt, :avgp, :ctr, :scId, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE total_clicks = VALUES(total_clicks), avg_position = VALUES(avg_position), ctr = VALUES(ctr), updated_at = NOW()',
            );

            $affected = 0;
            foreach ($rows as $row) {
                $uid = md5(bin2hex($row['category_id']) . '-' . $row['product_id'] . '-' . $row['d']);
                // CTR estimate: clicks / page_size (24)
                $ctr = (int) $row['cnt'] > 0 ? (int) $row['cnt'] / 24.0 : null;

                $stmt->executeStatement([
                    'id' => $uid,
                    'catId' => $row['category_id'],
                    'prodId' => $row['product_id'],
                    'date' => $row['d'],
                    'cnt' => $row['cnt'],
                    'avgp' => $row['avgp'],
                    'ctr' => $ctr,
                    'scId' => $row['sales_channel_id'],
                ]);
                $affected++;
            }

            $this->logger->info('ClickEventCleanupTask: aggregated events', [
                'affectedRows' => $affected,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('ClickEventCleanupTask: aggregation failed', [
                'exception' => $e->getMessage(),
            ]);
            // Don't throw — still proceed with cleanup
        }
    }

    /**
     * Delete raw click events older than the retention period.
     */
    private function cleanupOldEvents(): void
    {
        $retentionDays = (int) $this->systemConfigService->get(
            'StrixVisualMerchandiser.config.clickEventRetentionDays',
        ) ?: 90;

        $cutoff = (new \DateTimeImmutable())->modify("-{$retentionDays} days")->format('Y-m-d H:i:s');

        $this->logger->info('ClickEventCleanupTask: deleting events older than {cutoff}', [
            'cutoff' => $cutoff,
            'retentionDays' => $retentionDays,
        ]);

        try {
            $deleted = $this->connection->executeStatement(
                'DELETE FROM strix_merch_click_event WHERE created_at < :cutoff',
                ['cutoff' => $cutoff],
            );

            $this->logger->info('ClickEventCleanupTask: deleted {count} events', [
                'count' => $deleted,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('ClickEventCleanupTask: cleanup failed', [
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
