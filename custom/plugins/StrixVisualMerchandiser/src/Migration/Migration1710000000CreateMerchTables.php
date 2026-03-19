<?php declare(strict_types=1);

namespace Strix\VisualMerchandiser\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1710000000CreateMerchTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1710000000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `strix_merch_rule` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `type` VARCHAR(50) NOT NULL,
                `config` JSON NOT NULL,
                `priority` INT NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `valid_from` DATETIME(3) NULL,
                `valid_until` DATETIME(3) NULL,
                `sales_channel_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.strix_merch_rule.active` (`active`),
                INDEX `idx.strix_merch_rule.type` (`type`),
                INDEX `idx.strix_merch_rule.priority` (`priority`),
                CONSTRAINT `fk.strix_merch_rule.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `strix_merch_rule_category` (
                `merch_rule_id` BINARY(16) NOT NULL,
                `category_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`merch_rule_id`, `category_id`),
                CONSTRAINT `fk.strix_merch_rule_category.merch_rule_id` FOREIGN KEY (`merch_rule_id`)
                    REFERENCES `strix_merch_rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.strix_merch_rule_category.category_id` FOREIGN KEY (`category_id`)
                    REFERENCES `category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `strix_merch_pin` (
                `id` BINARY(16) NOT NULL,
                `category_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `position` INT NOT NULL,
                `label` VARCHAR(50) NULL,
                `custom_label` JSON NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `valid_from` DATETIME(3) NULL,
                `valid_until` DATETIME(3) NULL,
                `sales_channel_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.strix_merch_pin.category_position` (`category_id`, `position`),
                INDEX `idx.strix_merch_pin.active` (`active`),
                CONSTRAINT `fk.strix_merch_pin.category_id` FOREIGN KEY (`category_id`)
                    REFERENCES `category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.strix_merch_pin.product_id` FOREIGN KEY (`product_id`)
                    REFERENCES `product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.strix_merch_pin.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `strix_merch_filter_template` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `filters` JSON NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `sales_channel_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.strix_merch_filter_template.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `strix_merch_filter_template_category` (
                `id` BINARY(16) NOT NULL,
                `filter_template_id` BINARY(16) NOT NULL,
                `category_id` BINARY(16) NOT NULL,
                `override_mode` VARCHAR(20) NOT NULL DEFAULT \'inherit\',
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `uniq.strix_merch_ftc.template_category` (`filter_template_id`, `category_id`),
                INDEX `idx.strix_merch_ftc.category_id` (`category_id`),
                CONSTRAINT `fk.strix_merch_ftc.filter_template_id` FOREIGN KEY (`filter_template_id`)
                    REFERENCES `strix_merch_filter_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.strix_merch_ftc.category_id` FOREIGN KEY (`category_id`)
                    REFERENCES `category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `strix_merch_sorting_template` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `sort_options` JSON NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `sales_channel_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.strix_merch_sorting_template.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `strix_merch_sorting_template_category` (
                `id` BINARY(16) NOT NULL,
                `sorting_template_id` BINARY(16) NOT NULL,
                `category_id` BINARY(16) NOT NULL,
                `override_mode` VARCHAR(20) NOT NULL DEFAULT \'inherit\',
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `uniq.strix_merch_stc.template_category` (`sorting_template_id`, `category_id`),
                INDEX `idx.strix_merch_stc.category_id` (`category_id`),
                CONSTRAINT `fk.strix_merch_stc.sorting_template_id` FOREIGN KEY (`sorting_template_id`)
                    REFERENCES `strix_merch_sorting_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.strix_merch_stc.category_id` FOREIGN KEY (`category_id`)
                    REFERENCES `category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `strix_merch_audit_log` (
                `id` BINARY(16) NOT NULL,
                `entity_type` VARCHAR(100) NOT NULL,
                `entity_id` VARCHAR(64) NOT NULL,
                `action` VARCHAR(20) NOT NULL,
                `changes` JSON NULL,
                `user_id` VARCHAR(64) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.strix_merch_audit_log.entity` (`entity_type`, `entity_id`),
                INDEX `idx.strix_merch_audit_log.created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `strix_merch_click_event` (
                `id` BINARY(16) NOT NULL,
                `category_id` BINARY(16) NOT NULL,
                `product_id` VARCHAR(64) NOT NULL,
                `event_type` VARCHAR(20) NOT NULL,
                `customer_id` BINARY(16) NULL,
                `session_id` VARCHAR(128) NULL,
                `position` INT NULL,
                `sales_channel_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.strix_merch_click_event.category_product` (`category_id`, `product_id`),
                INDEX `idx.strix_merch_click_event.created_at` (`created_at`),
                INDEX `idx.strix_merch_click_event.event_type` (`event_type`),
                CONSTRAINT `fk.strix_merch_click_event.category_id` FOREIGN KEY (`category_id`)
                    REFERENCES `category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.strix_merch_click_event.customer_id` FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.strix_merch_click_event.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `strix_merch_click_aggregate` (
                `id` BINARY(16) NOT NULL,
                `category_id` BINARY(16) NOT NULL,
                `product_id` VARCHAR(64) NOT NULL,
                `date` DATE NOT NULL,
                `total_clicks` INT NOT NULL DEFAULT 0,
                `avg_position` DOUBLE NULL,
                `ctr` DOUBLE NULL,
                `sales_channel_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `uniq.strix_merch_click_agg.cat_prod_date` (`category_id`, `product_id`, `date`),
                CONSTRAINT `fk.strix_merch_click_aggregate.category_id` FOREIGN KEY (`category_id`)
                    REFERENCES `category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.strix_merch_click_aggregate.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `strix_merch_customer_segment` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `type` VARCHAR(50) NOT NULL,
                `config` JSON NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `boost_factor` DOUBLE NOT NULL DEFAULT 1.0,
                `sales_channel_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.strix_merch_customer_segment.active` (`active`),
                CONSTRAINT `fk.strix_merch_customer_segment.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `strix_merch_customer_segment_membership` (
                `id` BINARY(16) NOT NULL,
                `segment_id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `score` DOUBLE NULL,
                `calculated_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE INDEX `uniq.strix_merch_csm.segment_customer` (`segment_id`, `customer_id`),
                INDEX `idx.strix_merch_csm.customer_id` (`customer_id`),
                CONSTRAINT `fk.strix_merch_csm.segment_id` FOREIGN KEY (`segment_id`)
                    REFERENCES `strix_merch_customer_segment` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.strix_merch_csm.customer_id` FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // Nothing to do
    }
}
