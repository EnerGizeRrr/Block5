CREATE TABLE audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(255) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(255) NOT NULL,
    meta JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    -- Индекс для быстрого поиска по сущностям
    INDEX idx_audit_log_entity (entity_type, entity_id)
);