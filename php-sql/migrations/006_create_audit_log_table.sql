CREATE TABLE IF NOT EXISTS `audit_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type` VARCHAR(255) NOT NULL,
    `entity_id` BIGINT UNSIGNED NOT NULL,
    `action` VARCHAR(255) NOT NULL,
    `meta` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `audit_log_entity_index` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;