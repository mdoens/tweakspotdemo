<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1710000003AddAuditLogCategoryId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1710000003;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->fetchFirstColumn(
            "SHOW COLUMNS FROM `strix_merch_audit_log` LIKE 'category_id'"
        );

        if (empty($columns)) {
            $connection->executeStatement(
                'ALTER TABLE `strix_merch_audit_log`
                 ADD COLUMN `category_id` VARCHAR(64) NULL AFTER `user_id`,
                 ADD INDEX `idx.strix_merch_audit_log.category_id` (`category_id`)'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
