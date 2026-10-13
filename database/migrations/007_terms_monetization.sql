CREATE TABLE IF NOT EXISTS terms_acceptances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    terms_version VARCHAR(40) NOT NULL,
    accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    acceptance_source ENUM('cadastro','login','atualizacao') NOT NULL DEFAULT 'atualizacao',
    ip_hash CHAR(64) NULL,
    user_agent_hash CHAR(64) NULL,
    CONSTRAINT fk_terms_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_terms_user_version (user_id, terms_version),
    INDEX idx_terms_version (terms_version, accepted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE plans
    ADD COLUMN audience ENUM('condominio','administradora','todos') NOT NULL DEFAULT 'todos' AFTER monthly_price,
    ADD COLUMN trial_days SMALLINT UNSIGNED NOT NULL DEFAULT 14 AFTER audience,
    ADD COLUMN monthly_request_limit SMALLINT UNSIGNED NULL AFTER trial_days,
    ADD COLUMN external_plan_id VARCHAR(120) NULL AFTER monthly_request_limit,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER active;

ALTER TABLE subscriptions
    ADD COLUMN provider ENUM('mercadopago','manual') NOT NULL DEFAULT 'manual' AFTER plan_id,
    ADD COLUMN trial_ends_at DATETIME NULL AFTER starts_at,
    ADD COLUMN current_period_end DATETIME NULL AFTER trial_ends_at,
    ADD COLUMN external_subscription_id VARCHAR(120) NULL AFTER external_reference,
    ADD COLUMN canceled_at DATETIME NULL AFTER external_subscription_id,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER canceled_at,
    ADD UNIQUE KEY uq_subscription_external (external_subscription_id);

CREATE TABLE IF NOT EXISTS subscription_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NOT NULL,
    provider_payment_id VARCHAR(120) NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pendente','aprovado','recusado','reembolsado','cancelado') NOT NULL DEFAULT 'pendente',
    paid_at DATETIME NULL,
    raw_reference VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_subscription_payment_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_subscription_payment_provider (provider_payment_id),
    INDEX idx_subscription_payment_status (subscription_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE plans SET audience='condominio', trial_days=14, monthly_request_limit=5 WHERE slug='basico';
UPDATE plans SET audience='condominio', trial_days=14, monthly_request_limit=30 WHERE slug='profissional';
UPDATE plans SET audience='condominio', trial_days=14, monthly_request_limit=NULL WHERE slug='premium';

INSERT INTO plans (name, slug, monthly_price, audience, trial_days, monthly_request_limit, active)
VALUES ('Administradora','administradora',299.90,'administradora',14,NULL,1)
ON DUPLICATE KEY UPDATE monthly_price=VALUES(monthly_price), audience=VALUES(audience), trial_days=VALUES(trial_days), monthly_request_limit=VALUES(monthly_request_limit), active=1;
