-- ============================================================
-- BiT Payroll Management System â€” Database Schema
-- Bahir Dar Institute of Technology
-- Database: payroll_db
-- Engine: InnoDB | Charset: utf8mb4
-- Tax: Revised Monthly Employment Tax Brackets (Ethiopia 2025)
-- Pension: Employee 7% + Employer 11% of basic salary
-- ============================================================

CREATE DATABASE IF NOT EXISTS payroll_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE payroll_db;

-- ============================================================
-- TABLE 1: users
-- System login accounts for all roles
-- ============================================================
CREATE TABLE users (
    user_id       INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)     NOT NULL UNIQUE,
    password      VARCHAR(255)    NOT NULL,          -- bcrypt hashed
    role          ENUM('admin','hr','finance','employee') NOT NULL DEFAULT 'employee',
    full_name     VARCHAR(100)    NOT NULL,
    email         VARCHAR(100)             UNIQUE,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    last_login    DATETIME                 DEFAULT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                           ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    INDEX idx_users_role     (role),
    INDEX idx_users_active   (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='System user accounts â€” all roles';

-- ============================================================
-- TABLE 2: departments
-- Academic and administrative departments at BiT
-- ============================================================
CREATE TABLE departments (
    dept_id       INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    dept_name     VARCHAR(100)    NOT NULL UNIQUE,
    dept_code     VARCHAR(20)     NOT NULL UNIQUE,
    description   TEXT                     DEFAULT NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (dept_id),
    INDEX idx_dept_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='BiT departments and faculties';

-- ============================================================
-- TABLE 3: employees
-- Core employee records managed by HR
-- ============================================================
CREATE TABLE employees (
    emp_id            VARCHAR(20)     NOT NULL,       -- e.g. EMP-101
    user_id           INT UNSIGNED             DEFAULT NULL,  -- FK â†’ users
    full_name         VARCHAR(100)    NOT NULL,
    gender            ENUM('male','female','other') NOT NULL,
    date_of_birth     DATE                     DEFAULT NULL,
    phone             VARCHAR(20)              DEFAULT NULL,
    email             VARCHAR(100)             DEFAULT NULL,
    address           VARCHAR(200)             DEFAULT NULL,
    dept_id           INT UNSIGNED    NOT NULL,       -- FK â†’ departments
    position          VARCHAR(100)    NOT NULL,
    employment_type   ENUM('permanent','contract','part_time') NOT NULL DEFAULT 'permanent',
    basic_salary      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    employment_date   DATE            NOT NULL,
    status            ENUM('active','on_leave','transferred','promoted','terminated')
                                      NOT NULL DEFAULT 'active',
    status_updated_at DATETIME                 DEFAULT NULL,
    created_by        INT UNSIGNED             DEFAULT NULL,  -- FK â†’ users (HR)
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (emp_id),
    UNIQUE  KEY uq_emp_user   (user_id),
    INDEX   idx_emp_dept      (dept_id),
    INDEX   idx_emp_status    (status),
    INDEX   idx_emp_type      (employment_type),
    CONSTRAINT fk_emp_user    FOREIGN KEY (user_id)    REFERENCES users(user_id)       ON DELETE SET NULL,
    CONSTRAINT fk_emp_dept    FOREIGN KEY (dept_id)    REFERENCES departments(dept_id) ON DELETE RESTRICT,
    CONSTRAINT fk_emp_created FOREIGN KEY (created_by) REFERENCES users(user_id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Employee master records';

-- ============================================================
-- TABLE 4: allowances
-- Per-employee allowance configuration (1:1 with employee)
-- ============================================================
CREATE TABLE allowances (
    allowance_id        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    emp_id              VARCHAR(20)     NOT NULL,       -- FK â†’ employees
    housing             DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    transport           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    position_allowance  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    teaching            DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    other               DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    effective_from      DATE            NOT NULL,
    effective_to        DATE                     DEFAULT NULL,  -- NULL = current
    updated_by          INT UNSIGNED             DEFAULT NULL,  -- FK â†’ users (HR)
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (allowance_id),
    INDEX idx_allow_emp    (emp_id),
    INDEX idx_allow_active (effective_to),
    CONSTRAINT fk_allow_emp     FOREIGN KEY (emp_id)      REFERENCES employees(emp_id) ON DELETE CASCADE,
    CONSTRAINT fk_allow_updated FOREIGN KEY (updated_by)  REFERENCES users(user_id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Employee allowance configurations';

-- ============================================================
-- TABLE 5: payroll_periods
-- Monthly payroll batches â€” one row per month
-- ============================================================
CREATE TABLE payroll_periods (
    period_id       INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    period_label    VARCHAR(30)     NOT NULL UNIQUE,   -- e.g. 'July 2025'
    period_month    TINYINT         NOT NULL,           -- 1â€“12
    period_year     SMALLINT        NOT NULL,
    status          ENUM('pending','processing','processed','verified','finalized')
                                    NOT NULL DEFAULT 'pending',
    processed_by    INT UNSIGNED             DEFAULT NULL,  -- FK â†’ users (Finance)
    processed_at    DATETIME                 DEFAULT NULL,
    verified_by     INT UNSIGNED             DEFAULT NULL,  -- FK â†’ users (Finance)
    verified_at     DATETIME                 DEFAULT NULL,
    finalized_at    DATETIME                 DEFAULT NULL,
    notes           TEXT                     DEFAULT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (period_id),
    UNIQUE  KEY uq_period_month_year (period_month, period_year),
    INDEX   idx_period_status        (status),
    CONSTRAINT fk_period_processed FOREIGN KEY (processed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_period_verified  FOREIGN KEY (verified_by)  REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Monthly payroll batch records';

-- ============================================================
-- TABLE 6: payroll_records
-- Per-employee salary calculation for each period
-- ============================================================
CREATE TABLE payroll_records (
    record_id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    period_id           INT UNSIGNED    NOT NULL,       -- FK â†’ payroll_periods
    emp_id              VARCHAR(20)     NOT NULL,       -- FK â†’ employees
    basic_salary        DECIMAL(12,2)   NOT NULL,
    housing             DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    transport           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    position_allowance  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    teaching            DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    other_allowance     DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    total_allowances    DECIMAL(12,2)   NOT NULL,       -- sum of all allowances
    gross_salary        DECIMAL(12,2)   NOT NULL,       -- basic + total_allowances
    pension_employee    DECIMAL(10,2)   NOT NULL,       -- 7% of basic
    pension_employer    DECIMAL(10,2)   NOT NULL,       -- 11% of basic
    taxable_income      DECIMAL(12,2)   NOT NULL,       -- gross âˆ’ pension_employee
    income_tax          DECIMAL(10,2)   NOT NULL,       -- per 2025 brackets
    other_deductions    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    net_pay             DECIMAL(12,2)   NOT NULL,       -- taxable âˆ’ tax âˆ’ other_deductions
    tax_bracket         VARCHAR(20)              DEFAULT NULL,  -- e.g. '30%'
    calculated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (record_id),
    UNIQUE  KEY uq_record_period_emp (period_id, emp_id),
    INDEX   idx_record_emp           (emp_id),
    INDEX   idx_record_period        (period_id),
    CONSTRAINT fk_record_period FOREIGN KEY (period_id) REFERENCES payroll_periods(period_id) ON DELETE CASCADE,
    CONSTRAINT fk_record_emp    FOREIGN KEY (emp_id)    REFERENCES employees(emp_id)           ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-employee payroll calculations per period';

-- ============================================================
-- TABLE 7: payslips
-- Generated payslip files linked to payroll records
-- ============================================================
CREATE TABLE payslips (
    payslip_id      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    record_id       INT UNSIGNED    NOT NULL UNIQUE,    -- FK â†’ payroll_records (1:1)
    emp_id          VARCHAR(20)     NOT NULL,
    period_id       INT UNSIGNED    NOT NULL,
    file_path       VARCHAR(300)             DEFAULT NULL,  -- PDF storage path
    generated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    generated_by    INT UNSIGNED             DEFAULT NULL,  -- FK â†’ users (Finance)
    viewed_at       DATETIME                 DEFAULT NULL,  -- first view by employee
    downloaded_at   DATETIME                 DEFAULT NULL,  -- first download
    PRIMARY KEY (payslip_id),
    INDEX idx_payslip_emp    (emp_id),
    INDEX idx_payslip_period (period_id),
    CONSTRAINT fk_payslip_record    FOREIGN KEY (record_id)    REFERENCES payroll_records(record_id) ON DELETE CASCADE,
    CONSTRAINT fk_payslip_emp       FOREIGN KEY (emp_id)       REFERENCES employees(emp_id)          ON DELETE RESTRICT,
    CONSTRAINT fk_payslip_period    FOREIGN KEY (period_id)    REFERENCES payroll_periods(period_id) ON DELETE CASCADE,
    CONSTRAINT fk_payslip_generated FOREIGN KEY (generated_by) REFERENCES users(user_id)             ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Generated payslip records and file references';

-- ============================================================
-- TABLE 8: employee_status_history
-- Audit trail for every status change (HR)
-- ============================================================
CREATE TABLE employee_status_history (
    history_id      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    emp_id          VARCHAR(20)     NOT NULL,
    previous_status ENUM('active','on_leave','transferred','promoted','terminated') DEFAULT NULL,
    new_status      ENUM('active','on_leave','transferred','promoted','terminated') NOT NULL,
    effective_date  DATE            NOT NULL,
    reason          TEXT                     DEFAULT NULL,
    changed_by      INT UNSIGNED             DEFAULT NULL,  -- FK â†’ users (HR)
    changed_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    INDEX idx_status_hist_emp  (emp_id),
    INDEX idx_status_hist_date (effective_date),
    CONSTRAINT fk_status_hist_emp     FOREIGN KEY (emp_id)     REFERENCES employees(emp_id) ON DELETE CASCADE,
    CONSTRAINT fk_status_hist_changed FOREIGN KEY (changed_by) REFERENCES users(user_id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Employee status change audit trail';

-- ============================================================
-- TABLE 9: audit_logs
-- System-wide action log for security and accountability
-- ============================================================
CREATE TABLE audit_logs (
    log_id      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED             DEFAULT NULL,  -- FK â†’ users
    username    VARCHAR(50)              DEFAULT NULL,  -- snapshot at time of action
    role        VARCHAR(20)              DEFAULT NULL,
    action      VARCHAR(100)    NOT NULL,               -- e.g. 'Login', 'Process Payroll'
    target      VARCHAR(100)             DEFAULT NULL,  -- e.g. 'EMP-101', 'June 2025'
    details     TEXT                     DEFAULT NULL,
    ip_address  VARCHAR(45)              DEFAULT NULL,  -- supports IPv6
    status      ENUM('success','failed') NOT NULL DEFAULT 'success',
    logged_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    INDEX idx_audit_user   (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_date   (logged_at),
    INDEX idx_audit_status (status),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='System-wide audit log for all user actions';

-- ============================================================
-- TABLE 10: system_settings
-- Configurable system parameters (pension rates, etc.)
-- ============================================================
CREATE TABLE system_settings (
    setting_key     VARCHAR(80)     NOT NULL,
    setting_value   VARCHAR(255)    NOT NULL,
    description     VARCHAR(200)             DEFAULT NULL,
    updated_by      INT UNSIGNED             DEFAULT NULL,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key),
    CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configurable system settings';

-- ============================================================
-- SEED DATA
-- ============================================================

-- Departments
INSERT INTO departments (dept_name, dept_code) VALUES
('Faculty of Computing',          'FOC'),
('Faculty of Engineering',        'FOE'),
('Faculty of Science',            'FOS'),
('Administrative Office',         'ADM'),
('Finance Office',                'FIN'),
('Human Resources Office',        'HRO'),
('Library',                       'LIB'),
('IT Support',                    'ITS');

-- System Settings (pension rates + tax year)
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('pension_employee_rate',      '0.18',  'Employee pension rate (7% of basic salary)'),
('pension_employer_rate',      '0.18',  'Employer pension rate (11% of basic salary)'),
('credit_association_rate',    '0.10',  'Credit Association deduction rate (10% of basic salary)'),
('renaissance_dam_rate',       '0.01',  'Renaissance Dam (GERD) deduction rate (1% of basic salary)'),
('tax_year',                   '2025',  'Active tax bracket year'),
('tax_proclamation',           'Revised 2025 Brackets', 'Tax regulation reference'),
('institution_name',           'Bahir Dar Institute of Technology', 'Institution full name'),
('institution_short',          'BiT',   'Institution abbreviation'),
('currency',                   'ETB',   'Currency code'),
('payroll_day',                '28',    'Default payroll processing day of month'),
('session_timeout_minutes',    '15',    'Auto-logout after inactivity (minutes)');

-- Default admin user  (password: Admin@2025 â€” bcrypt hash)
INSERT INTO users (username, password, role, full_name, email) VALUES
('admin',
 '$2y$12$KerV8WU3DPW6xN0dpwBHNunby5mGqHsHAXUU5Ldn1Y9iAZJYIdFxxa',
 'admin',
 'System Administrator',
 'admin@bit.edu.et');

-- ============================================================
-- VIEWS (useful for reports and dashboards)
-- ============================================================

-- Active employees with department name
CREATE OR REPLACE VIEW vw_active_employees AS
SELECT
    e.emp_id,
    e.full_name,
    e.gender,
    e.phone,
    e.email,
    d.dept_name,
    d.dept_code,
    e.position,
    e.employment_type,
    e.basic_salary,
    e.employment_date,
    e.status,
    u.username
FROM employees e
JOIN departments d ON e.dept_id = d.dept_id
LEFT JOIN users u  ON e.user_id = u.user_id
WHERE e.status = 'active';

-- Latest allowances per employee
CREATE OR REPLACE VIEW vw_current_allowances AS
SELECT
    a.emp_id,
    e.full_name,
    a.housing,
    a.transport,
    a.position_allowance,
    a.teaching,
    a.other,
    (a.housing + a.transport + a.position_allowance + a.teaching + a.other) AS total_allowances,
    a.effective_from
FROM allowances a
JOIN employees e ON a.emp_id = e.emp_id
WHERE a.effective_to IS NULL;

-- Full payroll summary per period
CREATE OR REPLACE VIEW vw_payroll_summary AS
SELECT
    pp.period_label,
    pp.period_month,
    pp.period_year,
    pp.status          AS period_status,
    COUNT(pr.record_id)          AS employee_count,
    SUM(pr.gross_salary)         AS total_gross,
    SUM(pr.pension_employee)     AS total_pension_emp,
    SUM(pr.pension_employer)     AS total_pension_org,
    SUM(pr.income_tax)           AS total_tax,
    SUM(pr.net_pay)              AS total_net_pay
FROM payroll_periods pp
LEFT JOIN payroll_records pr ON pp.period_id = pr.period_id
GROUP BY pp.period_id;

-- ============================================================
-- END OF SCHEMA
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    reset_id    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NOT NULL,
    token       VARCHAR(64)     NOT NULL UNIQUE,
    expires_at  DATETIME        NOT NULL,
    used        TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reset_id),
    INDEX idx_reset_token (token),
    INDEX idx_reset_user  (user_id),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

