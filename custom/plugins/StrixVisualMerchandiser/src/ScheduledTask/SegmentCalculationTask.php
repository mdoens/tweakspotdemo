<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class SegmentCalculationTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'strix.merch.segment_calculation';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // Daily
    }
}
