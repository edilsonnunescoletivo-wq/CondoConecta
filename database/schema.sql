-- CondoConecta: schema base.
-- Após este arquivo, execute as migrações de database/migrations em ordem numérica.
SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin','contratante','fornecedor') NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    email_verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role_active (role, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    account_type ENUM('condominio','administradora','sindico_profissional','empresa') NOT NULL DEFAULT 'condominio',
    legal_name VARCHAR(180) NULL,
    display_name VARCHAR(180) NOT NULL,
    cpf_cnpj VARCHAR(20) NULL,
    city VARCHAR(100) NOT NULL,
    state CHAR(2) NOT NULL DEFAULT 'BA',
    address VARCHAR(220) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contractor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_contractor_location (state, city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    legal_name VARCHAR(180) NULL,
    trade_name VARCHAR(180) NOT NULL,
    cpf_cnpj VARCHAR(20) NULL,
    description TEXT NULL,
    city VARCHAR(100) NOT NULL,
    state CHAR(2) NOT NULL DEFAULT 'BA',
    service_radius_km SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    emergency_service TINYINT(1) NOT NULL DEFAULT 0,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_supplier_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_supplier_location (state, city),
    INDEX idx_supplier_verified (verified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_categories (
    supplier_id BIGINT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (supplier_id, category_id),
    CONSTRAINT fk_supplier_category_supplier FOREIGN KEY (supplier_id) REFERENCES supplier_profiles(id) ON DELETE CASCADE,
    CONSTRAINT fk_supplier_category_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_regions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT UNSIGNED NOT NULL,
    city VARCHAR(100) NOT NULL,
    state CHAR(2) NOT NULL DEFAULT 'BA',
    CONSTRAINT fk_supplier_region_supplier FOREIGN KEY (supplier_id) REFERENCES supplier_profiles(id) ON DELETE CASCADE,
    UNIQUE KEY uq_supplier_region (supplier_id, state, city),
    INDEX idx_supplier_region_location (state, city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contractor_user_id BIGINT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NOT NULL,
    urgency ENUM('normal','urgente','agora') NOT NULL DEFAULT 'normal',
    city VARCHAR(100) NOT NULL,
    state CHAR(2) NOT NULL DEFAULT 'BA',
    address_reference VARCHAR(220) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    desired_date DATE NULL,
    status ENUM('rascunho','aberta','em_cotacao','contratada','cancelada','concluida') NOT NULL DEFAULT 'aberta',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_request_contractor FOREIGN KEY (contractor_user_id) REFERENCES users(id),
    CONSTRAINT fk_request_category FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_request_match (status, state, city, category_id, urgency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE proposals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_request_id BIGINT UNSIGNED NOT NULL,
    supplier_user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    estimated_days SMALLINT UNSIGNED NULL,
    warranty_days SMALLINT UNSIGNED NULL,
    details TEXT NULL,
    status ENUM('enviada','aceita','recusada','retirada') NOT NULL DEFAULT 'enviada',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_proposal_request FOREIGN KEY (service_request_id) REFERENCES service_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_proposal_supplier FOREIGN KEY (supplier_user_id) REFERENCES users(id),
    UNIQUE KEY uq_request_supplier (service_request_id, supplier_user_id),
    INDEX idx_proposal_request_status (service_request_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hirings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_request_id BIGINT UNSIGNED NOT NULL UNIQUE,
    proposal_id BIGINT UNSIGNED NOT NULL UNIQUE,
    contractor_user_id BIGINT UNSIGNED NOT NULL,
    supplier_user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('contratada','em_execucao','concluida','cancelada') NOT NULL DEFAULT 'contratada',
    hired_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    CONSTRAINT fk_hiring_request FOREIGN KEY (service_request_id) REFERENCES service_requests(id),
    CONSTRAINT fk_hiring_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id),
    CONSTRAINT fk_hiring_contractor FOREIGN KEY (contractor_user_id) REFERENCES users(id),
    CONSTRAINT fk_hiring_supplier FOREIGN KEY (supplier_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hiring_id BIGINT UNSIGNED NOT NULL UNIQUE,
    contractor_user_id BIGINT UNSIGNED NOT NULL,
    supplier_user_id BIGINT UNSIGNED NOT NULL,
    quality_rating TINYINT UNSIGNED NOT NULL,
    deadline_rating TINYINT UNSIGNED NOT NULL,
    service_rating TINYINT UNSIGNED NOT NULL,
    value_rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_review_hiring FOREIGN KEY (hiring_id) REFERENCES hirings(id) ON DELETE CASCADE,
    CONSTRAINT fk_review_contractor FOREIGN KEY (contractor_user_id) REFERENCES users(id),
    CONSTRAINT fk_review_supplier FOREIGN KEY (supplier_user_id) REFERENCES users(id),
    CONSTRAINT chk_quality_rating CHECK (quality_rating BETWEEN 1 AND 5),
    CONSTRAINT chk_deadline_rating CHECK (deadline_rating BETWEEN 1 AND 5),
    CONSTRAINT chk_service_rating CHECK (service_rating BETWEEN 1 AND 5),
    CONSTRAINT chk_value_rating CHECK (value_rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_user_id BIGINT UNSIGNED NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status ENUM('pendente','aprovado','rejeitado') NOT NULL DEFAULT 'pendente',
    expires_at DATE NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_document_supplier FOREIGN KEY (supplier_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_document_status_expiry (status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    monthly_price DECIMAL(10,2) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contractor_user_id BIGINT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    status ENUM('trial','ativa','inadimplente','cancelada') NOT NULL DEFAULT 'trial',
    starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    external_reference VARCHAR(120) NULL,
    CONSTRAINT fk_subscription_user FOREIGN KEY (contractor_user_id) REFERENCES users(id),
    CONSTRAINT fk_subscription_plan FOREIGN KEY (plan_id) REFERENCES plans(id),
    INDEX idx_subscription_user_status (contractor_user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (name, slug) VALUES
('Elétrica','eletrica'),
('Hidráulica','hidraulica'),
('Bombas e motores','bombas-e-motores'),
('Piscinas','piscinas'),
('Elevadores','elevadores'),
('Geradores','geradores'),
('Portões e controle de acesso','portoes-controle-acesso'),
('CFTV e monitoramento','cftv-monitoramento'),
('Jardinagem','jardinagem'),
('Pintura','pintura'),
('Impermeabilização','impermeabilizacao'),
('Extintores e AVCB','extintores-avcb'),
('Dedetização','dedetizacao'),
('Limpeza de reservatórios','limpeza-reservatorios');

INSERT INTO plans (name, slug, monthly_price) VALUES
('Básico','basico',39.90),
('Profissional','profissional',79.90),
('Premium','premium',129.90);
