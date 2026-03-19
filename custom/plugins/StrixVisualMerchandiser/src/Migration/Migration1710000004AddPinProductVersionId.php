<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1710000004AddPinProductVersionId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1710000004;
    }

    public function update(Connection $connection): void
    {
        $columnExists = $connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_NAME = 'strix_merch_pin'
             AND COLUMN_NAME = 'product_version_id'
             AND TABLE_SCHEMA = DATABASE()",
        );

        if ((int) $columnExists > 0) {
            return;
        }

        $connection->executeStatement('
            ALTER TABLE `strix_merch_pin`
            ADD COLUMN `product_version_id` BINARY(16) NOT NULL DEFAULT 0x0FA91CE3E96A4BC2BE4BD9CE752C3425
            AFTER `product_id`
        ');

        // Set the live version ID for all existing rows
        $connection->executeStatement("
            UPDATE `strix_merch_pin`
            SET `product_version_id` = UNHEX(REPLACE(
                (SELECT `id` FROM `version` WHERE `name` = '' LIMIT 1),
                '-', ''
            ))
            WHERE `product_version_id` = 0x0FA91CE3E96A4BC2BE4BD9CE752C3425
        ");
    }
}
