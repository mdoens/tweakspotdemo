<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class MerchScheduleTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'strix.merch.schedule';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // Daily
    }
}
