<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1710000002AddClickEventMetadata extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1710000002;
    }

    public function update(Connection $connection): void
    {
        $columns = array_column(
            $connection->fetchAllAssociative('SHOW COLUMNS FROM `strix_merch_click_event`'),
            'Field',
        );

        if (!\in_array('metadata', $columns, true)) {
            $connection->executeStatement('
                ALTER TABLE `strix_merch_click_event`
                    ADD COLUMN `metadata` JSON NULL AFTER `sales_channel_id`
            ');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // Nothing to do
    }
}
