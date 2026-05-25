-- =============================================================
-- Audit Log — table, permission, and role grants
-- Safe to re-run: uses CREATE TABLE IF NOT EXISTS and INSERT IGNORE
-- =============================================================

CREATE TABLE IF NOT EXISTS audit_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT,
    event_type   VARCHAR(50)  NOT NULL COMMENT 'SECURITY, TRANSACTION, DATA_CHANGE, etc.',
    event_action VARCHAR(50)  NOT NULL COMMENT 'CREATE, UPDATE, DELETE, LOGIN, etc.',
    entity_type  VARCHAR(60)  NOT NULL DEFAULT '',
    entity_id    INT,
    old_values   LONGTEXT     COMMENT 'JSON before change',
    new_values   LONGTEXT     COMMENT 'JSON after change',
    description  TEXT,
    ip_address   VARCHAR(45),
    user_agent   VARCHAR(255),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_al_user    (user_id),
    KEY idx_al_entity  (entity_type, entity_id),
    KEY idx_al_event   (event_type, event_action),
    KEY idx_al_created (created_at),
    CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO permissions (code, name, description, module) VALUES
('master_data.audit_logs.view', 'View Audit Logs', 'View the system audit trail', 'master_data');

-- Grant to admin and auditor roles
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r JOIN permissions p ON p.code = 'master_data.audit_logs.view'
WHERE r.code IN ('admin', 'auditor');
