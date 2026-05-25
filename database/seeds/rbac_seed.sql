-- =============================================================
-- Treasury Revenue System — RBAC Seed
-- Run once to bootstrap permissions, roles, and role-permission
-- mappings. Safe to re-run: uses INSERT IGNORE throughout.
-- =============================================================

-- ---------------------------------------------------------------
-- Tables (created only if they don't already exist)
-- ---------------------------------------------------------------

CREATE TABLE IF NOT EXISTS permissions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(120) NOT NULL,
    name        VARCHAR(150) NOT NULL,
    description TEXT,
    module      VARCHAR(60)  NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_permission_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(60)  NOT NULL,
    name        VARCHAR(100) NOT NULL,
    description TEXT,
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    role_id       INT NOT NULL,
    permission_id INT NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_permission (role_id, permission_id),
    CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_roles (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    role_id    INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_role (user_id, role_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- Permissions
-- ---------------------------------------------------------------

INSERT IGNORE INTO permissions (code, name, description, module) VALUES

-- Master Data
('master_data.departments.manage',          'Manage Departments',           'Create, edit and manage departments',                    'master_data'),
('master_data.sub_treasuries.manage',       'Manage Sub-Treasuries',        'Create, edit and manage sub-treasury offices',           'master_data'),
('master_data.registers.manage',            'Manage Registers',             'Create, edit and manage cash registers',                 'master_data'),
('master_data.bank_accounts.manage',        'Manage Bank Accounts',         'Create, edit and manage bank accounts',                  'master_data'),
('master_data.cost_centers.manage',         'Manage Cost Centers',          'Create, edit and manage cost centers',                   'master_data'),
('master_data.cost_center_activities.manage','Manage Cost Center Activities','Create, edit and manage cost center activities',         'master_data'),
('master_data.expenditure_types.manage',    'Manage Expenditure Types',     'Create, edit and manage expenditure types',              'master_data'),
('master_data.customers.manage',            'Manage Customers',             'Create, edit and manage customer records',               'master_data'),
('master_data.suppliers.manage',            'Manage Suppliers',             'Create, edit and manage supplier records',               'master_data'),
('master_data.users.manage',                'Manage Users',                 'Create, edit and manage user accounts and assignments',  'master_data'),
('master_data.roles.manage',                'Manage Role Permissions',      'Assign and revoke permissions for roles',                'master_data'),

-- Cashiering
('cashiering.view',        'View Cashiering',        'Access the cashiering module dashboard',           'cashiering'),

-- Transactions
('transactions.view',      'View Transactions',       'View and search transaction records',              'transactions'),
('transactions.create',    'Create Transactions',     'Create and submit new transactions',               'transactions'),

-- Expenses
('expenses.view',          'View Expenses',           'View and search expense entries',                  'expenses'),
('expenses.manage',        'Manage Expenses',         'Create and edit expense entries',                  'expenses'),
('expenses.approve',       'Approve Expenses',        'Approve submitted expense entries',                'expenses'),
('expenses.pay',           'Pay Expenses',            'Mark approved expenses as paid',                   'expenses'),

-- Reports
('reports.view',           'View Reports',            'Access and generate financial reports',            'reports');

-- ---------------------------------------------------------------
-- Roles
-- ---------------------------------------------------------------

INSERT IGNORE INTO roles (code, name, description, status) VALUES
('admin',        'System Administrator', 'Full access to all modules and configuration',                      'active'),
('data_manager', 'Data Manager',         'Manage master data records; no role-permission administration',     'active'),
('cashier',      'Cashier',              'Process transactions and manage expense entries',                   'active'),
('supervisor',   'Supervisor',           'All cashier actions plus expense approval and payment',             'active'),
('auditor',      'Auditor',              'Read-only access to transactions and expenses',                     'active');

-- ---------------------------------------------------------------
-- Role → Permission mappings
-- ---------------------------------------------------------------

-- Admin: every permission
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r CROSS JOIN permissions p
WHERE r.code = 'admin';

-- Data Manager: all master_data.* except roles.manage, plus cashiering.view
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r JOIN permissions p
    ON p.module = 'master_data' AND p.code != 'master_data.roles.manage'
WHERE r.code = 'data_manager';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r JOIN permissions p ON p.code = 'cashiering.view'
WHERE r.code = 'data_manager';

-- Cashier
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r JOIN permissions p
    ON p.code IN ('cashiering.view','transactions.view','transactions.create',
                  'expenses.view','expenses.manage')
WHERE r.code = 'cashier';

-- Supervisor: cashier permissions + approve + pay
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r JOIN permissions p
    ON p.code IN ('cashiering.view','transactions.view','transactions.create',
                  'expenses.view','expenses.manage','expenses.approve','expenses.pay')
WHERE r.code = 'supervisor';

-- Auditor: read-only
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r JOIN permissions p
    ON p.code IN ('cashiering.view','transactions.view','expenses.view')
WHERE r.code = 'auditor';

-- ---------------------------------------------------------------
-- Bootstrap: assign the admin role to your first/superadmin user.
-- Replace `1` with the actual user ID, then run this line once.
-- ---------------------------------------------------------------
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT 1, id FROM roles WHERE code = 'admin';
