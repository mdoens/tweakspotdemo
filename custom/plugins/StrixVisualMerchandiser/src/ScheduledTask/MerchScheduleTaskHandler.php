<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\ScheduledTask;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: MerchScheduleTask::class)]
class MerchScheduleTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private readonly EntityRepository $merchRuleRepository,
        private readonly EntityRepository $merchPinRepository,
        private readonly EntityRepository $auditLogRepository,
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->activateScheduled($now, $context);
        $this->deactivateExpired($now, $context);
    }

    private function activateScheduled(string $now, Context $context): void
    {
        // Activate rules where valid_from has been reached, currently inactive,
        // and valid_until has NOT yet passed
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', false));
        $criteria->addFilter(new RangeFilter('validFrom', [RangeFilter::LTE => $now]));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('validUntil', null),
            new RangeFilter('validUntil', [RangeFilter::GTE => $now]),
        ]));

        $rules = $this->merchRuleRepository->search($criteria, $context);
        $ruleUpdates = [];
        $auditRecords = [];
        foreach ($rules as $rule) {
            $ruleUpdates[] = ['id' => $rule->getId(), 'active' => true];
            $auditRecords[] = [
                'id' => Uuid::randomHex(),
                'entityType' => 'strix_merch_rule',
                'entityId' => $rule->getId(),
                'action' => 'activate',
                'changes' => ['active' => true],
            ];
        }
        if (!empty($ruleUpdates)) {
            $this->merchRuleRepository->update($ruleUpdates, $context);
        }

        // Same for pins
        $pins = $this->merchPinRepository->search($criteria, $context);
        $pinUpdates = [];
        foreach ($pins as $pin) {
            $pinUpdates[] = ['id' => $pin->getId(), 'active' => true];
            $auditRecords[] = [
                'id' => Uuid::randomHex(),
                'entityType' => 'strix_merch_pin',
                'entityId' => $pin->getId(),
                'action' => 'activate',
                'changes' => ['active' => true],
            ];
        }
        if (!empty($pinUpdates)) {
            $this->merchPinRepository->update($pinUpdates, $context);
        }

        if (!empty($auditRecords)) {
            $this->auditLogRepository->create($auditRecords, $context);
        }
    }

    private function deactivateExpired(string $now, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new RangeFilter('validUntil', [RangeFilter::LTE => $now]));

        $auditRecords = [];

        $rules = $this->merchRuleRepository->search($criteria, $context);
        $ruleUpdates = [];
        foreach ($rules as $rule) {
            $ruleUpdates[] = ['id' => $rule->getId(), 'active' => false];
            $auditRecords[] = [
                'id' => Uuid::randomHex(),
                'entityType' => 'strix_merch_rule',
                'entityId' => $rule->getId(),
                'action' => 'deactivate',
                'changes' => ['active' => false],
            ];
        }
        if (!empty($ruleUpdates)) {
            $this->merchRuleRepository->update($ruleUpdates, $context);
        }

        $pins = $this->merchPinRepository->search($criteria, $context);
        $pinUpdates = [];
        foreach ($pins as $pin) {
            $pinUpdates[] = ['id' => $pin->getId(), 'active' => false];
            $auditRecords[] = [
                'id' => Uuid::randomHex(),
                'entityType' => 'strix_merch_pin',
                'entityId' => $pin->getId(),
                'action' => 'deactivate',
                'changes' => ['active' => false],
            ];
        }
        if (!empty($pinUpdates)) {
            $this->merchPinRepository->update($pinUpdates, $context);
        }

        if (!empty($auditRecords)) {
            $this->auditLogRepository->create($auditRecords, $context);
        }
    }
}
