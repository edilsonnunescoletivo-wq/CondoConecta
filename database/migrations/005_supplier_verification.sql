ALTER TABLE supplier_documents
    ADD COLUMN original_name VARCHAR(180) NULL AFTER file_path,
    ADD COLUMN mime_type VARCHAR(100) NULL AFTER original_name,
    ADD COLUMN file_size INT UNSIGNED NULL AFTER mime_type,
    ADD COLUMN review_notes VARCHAR(1000) NULL AFTER status,
    ADD COLUMN reviewed_by_user_id BIGINT UNSIGNED NULL AFTER review_notes,
    ADD CONSTRAINT fk_document_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE supplier_profiles
    ADD COLUMN verification_notes VARCHAR(1000) NULL AFTER verified_at,
    ADD COLUMN verification_reviewed_at DATETIME NULL AFTER verification_notes,
    ADD COLUMN verification_reviewed_by_user_id BIGINT UNSIGNED NULL AFTER verification_reviewed_at,
    ADD CONSTRAINT fk_supplier_verification_reviewer FOREIGN KEY (verification_reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE INDEX idx_supplier_documents_supplier_status ON supplier_documents (supplier_user_id, status, expires_at);
