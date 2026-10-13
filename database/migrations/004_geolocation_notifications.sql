SET NAMES utf8mb4;

ALTER TABLE supplier_profiles
    ADD COLUMN latitude DECIMAL(10,7) NULL AFTER state,
    ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude,
    ADD INDEX idx_supplier_coordinates (latitude, longitude);

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(60) NOT NULL,
    title VARCHAR(180) NOT NULL,
    message VARCHAR(500) NOT NULL,
    link VARCHAR(255) NULL,
    reference_type VARCHAR(60) NULL,
    reference_id BIGINT UNSIGNED NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_notification_reference (user_id, type, reference_type, reference_id),
    INDEX idx_notification_user_read (user_id, read_at, created_at),
    INDEX idx_notification_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
