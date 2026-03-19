<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Strix\VisualMerchandiser\Core\Merchandising\ProductEnrichmentService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: ProductEnrichmentTask::class)]
class ProductEnrichmentTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly ProductEnrichmentService $productEnrichmentService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public function run(): void
    {
        $this->logger->info('ProductEnrichmentTask: starting enrichment');

        try {
            $this->productEnrichmentService->enrich(Context::createDefaultContext());
            $this->logger->info('ProductEnrichmentTask: completed successfully');
        } catch (\Throwable $e) {
            $this->logger->error('ProductEnrichmentTask: failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
