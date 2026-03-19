<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1710000001AddSegmentFields extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1710000001;
    }

    public function update(Connection $connection): void
    {
        $columns = array_column(
            $connection->fetchAllAssociative('SHOW COLUMNS FROM `strix_merch_customer_segment`'),
            'Field',
        );

        if (!\in_array('customer_count', $columns, true)) {
            $connection->executeStatement('
                ALTER TABLE `strix_merch_customer_segment`
                    ADD COLUMN `customer_count` INT NULL AFTER `boost_factor`
            ');
        }

        if (!\in_array('signal_description', $columns, true)) {
            $connection->executeStatement('
                ALTER TABLE `strix_merch_customer_segment`
                    ADD COLUMN `signal_description` VARCHAR(500) NULL AFTER `customer_count`
            ');
        }

        if (!\in_array('categories', $columns, true)) {
            $connection->executeStatement('
                ALTER TABLE `strix_merch_customer_segment`
                    ADD COLUMN `categories` JSON NULL AFTER `signal_description`
            ');
        }

        if (!\in_array('last_calculated_at', $columns, true)) {
            $connection->executeStatement('
                ALTER TABLE `strix_merch_customer_segment`
                    ADD COLUMN `last_calculated_at` DATETIME(3) NULL AFTER `categories`
            ');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // Nothing to do
    }
}
