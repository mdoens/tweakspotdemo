<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Strix\VisualMerchandiser\Core\Merchandising\SegmentCalculator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: SegmentCalculationTask::class)]
class SegmentCalculationTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly SegmentCalculator $segmentCalculator,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public function run(): void
    {
        $this->logger->info('SegmentCalculationTask: starting recalculation');

        try {
            $this->segmentCalculator->recalculate(Context::createDefaultContext());
            $this->logger->info('SegmentCalculationTask: completed successfully');
        } catch (\Throwable $e) {
            $this->logger->error('SegmentCalculationTask: failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
