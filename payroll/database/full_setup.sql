-- ============================================================
-- BiT Payroll System — Full Database Setup (Run Once)
-- phpMyAdmin → Select payroll_db → SQL tab → paste → Go
-- ============================================================

USE payroll_db;

SET FOREIGN_KEY_CHECKS = 0;

-- Step 1: Drop all tables cleanly
DROP TABLE IF EXISTS working_days;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS contact_replies;
DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS payslips;
DROP TABLE IF EXISTS payroll_records;
DROP TABLE IF EXISTS payroll_periods;
DROP TABLE IF EXISTS deductions;
DROP TABLE IF EXISTS allowances;
DROP TABLE IF EXISTS employee_status_history;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- TABLE 1: users
-- ============================================================
CREATE TABLE users (
    user_id       INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)     NOT NULL UNIQUE,
    password      VARCHAR(255)    NOT NULL,
    role          ENUM('admin','hr','finance','employee') NOT NULL DEFAULT 'employee',
    full_name     VARCHAR(100)    NOT NULL,
    email         VARCHAR(100)             UNIQUE,
    profile_photo VARCHAR(300)             DEFAULT NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    last_login    DATETIME                 DEFAULT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                           ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    INDEX idx_users_role   (role),
    INDEX idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 2: departments
-- ============================================================
CREATE TABLE departments (
    dept_id     INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    dept_name   VARCHAR(100)    NOT NULL UNIQUE,
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (dept_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 3: employees
-- ============================================================
CREATE TABLE employees (
    emp_id              VARCHAR(20)     NOT NULL,
    user_id             INT UNSIGNED             DEFAULT NULL,
    full_name           VARCHAR(100)    NOT NULL,
    last_name           VARCHAR(100)             DEFAULT NULL,
    cbe_account_number  VARCHAR(20)              DEFAULT NULL,
    cbe_account_name    VARCHAR(150)             DEFAULT NULL,
    gender              ENUM('male','female','other') NOT NULL DEFAULT 'male',
    date_of_birth       DATE                     DEFAULT NULL,
    phone               VARCHAR(20)              DEFAULT NULL,
    email               VARCHAR(100)             DEFAULT NULL,
    dept_id             INT UNSIGNED    NOT NULL,
    position            VARCHAR(100)    NOT NULL,
    employment_type     ENUM('permanent','contract','part_time') NOT NULL DEFAULT 'permanent',
    basic_salary        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    employment_date     DATE            NOT NULL,
    status              ENUM('active','on_leave','terminated','transferred','promoted')
                                        NOT NULL DEFAULT 'active',
    created_by          INT UNSIGNED             DEFAULT NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (emp_id),
    INDEX idx_emp_user   (user_id),
    INDEX idx_emp_dept   (dept_id),
    INDEX idx_emp_status (status),
    CONSTRAINT fk_emp_user FOREIGN KEY (user_id)  REFERENCES users(user_id)       ON DELETE SET NULL,
    CONSTRAINT fk_emp_dept FOREIGN KEY (dept_id)  REFERENCES departments(dept_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 4: allowances
-- ============================================================
CREATE TABLE allowances (
    allowance_id        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    emp_id              VARCHAR(20)     NOT NULL,
    housing             DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    transport           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    position_allowance  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    teaching            DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    other               DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    effective_from      DATE            NOT NULL,
    effective_to        DATE                     DEFAULT NULL,
    updated_by          INT UNSIGNED             DEFAULT NULL,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (allowance_id),
    INDEX idx_allow_emp (emp_id),
    CONSTRAINT fk_allow_emp FOREIGN KEY (emp_id) REFERENCES employees(emp_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 5: deductions
-- ============================================================
CREATE TABLE deductions (
    deduction_id        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    emp_id              VARCHAR(20)     NOT NULL,
    credit_association  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    renaissance_dam     DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    loan_repayment      DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    penalty             DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    other               DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    description         TEXT                     DEFAULT NULL,
    effective_month     TINYINT         NOT NULL,
    effective_year      SMALLINT        NOT NULL,
    status              ENUM('active','applied','cancelled') NOT NULL DEFAULT 'active',
    created_by          INT UNSIGNED             DEFAULT NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (deduction_id),
    UNIQUE KEY uq_ded_emp_period (emp_id, effective_month, effective_year),
    INDEX idx_ded_emp    (emp_id),
    INDEX idx_ded_period (effective_year, effective_month),
    CONSTRAINT fk_ded_emp     FOREIGN KEY (emp_id)     REFERENCES employees(emp_id) ON DELETE CASCADE,
    CONSTRAINT fk_ded_created FOREIGN KEY (created_by) REFERENCES users(user_id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 6: employee_status_history
-- ============================================================
CREATE TABLE employee_status_history (
    history_id      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    emp_id          VARCHAR(20)     NOT NULL,
    previous_status VARCHAR(30)              DEFAULT NULL,
    new_status      VARCHAR(30)     NOT NULL,
    effective_date  DATE            NOT NULL,
    reason          TEXT                     DEFAULT NULL,
    changed_by      INT UNSIGNED             DEFAULT NULL,
    changed_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (history_id),
    INDEX idx_sh_emp (emp_id),
    CONSTRAINT fk_sh_emp FOREIGN KEY (emp_id) REFERENCES employees(emp_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 7: payroll_periods
-- ============================================================
CREATE TABLE payroll_periods (
    period_id       INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    period_label    VARCHAR(50)     NOT NULL,
    period_month    TINYINT         NOT NULL,
    period_year     SMALLINT        NOT NULL,
    status          ENUM('open','processed','verified','finalized') NOT NULL DEFAULT 'open',
    created_by      INT UNSIGNED             DEFAULT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (period_id),
    UNIQUE KEY uq_period (period_month, period_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 8: payroll_records
-- ============================================================
CREATE TABLE payroll_records (
    record_id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    period_id           INT UNSIGNED    NOT NULL,
    emp_id              VARCHAR(20)     NOT NULL,
    basic_salary        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    housing             DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    transport           DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    position_allowance  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    teaching            DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    other_allowance     DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    total_allowances    DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    gross_salary        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    pension_employee    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    pension_employer    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    taxable_income      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    income_tax          DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    other_deductions    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    net_pay             DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    tax_bracket         VARCHAR(30)              DEFAULT NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (record_id),
    UNIQUE KEY uq_pr_period_emp (period_id, emp_id),
    INDEX idx_pr_emp    (emp_id),
    INDEX idx_pr_period (period_id),
    CONSTRAINT fk_pr_period FOREIGN KEY (period_id) REFERENCES payroll_periods(period_id) ON DELETE CASCADE,
    CONSTRAINT fk_pr_emp    FOREIGN KEY (emp_id)    REFERENCES employees(emp_id)           ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 9: payslips
-- ============================================================
CREATE TABLE payslips (
    payslip_id      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    period_id       INT UNSIGNED    NOT NULL,
    emp_id          VARCHAR(20)     NOT NULL,
    generated_by    INT UNSIGNED             DEFAULT NULL,
    generated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    file_path       VARCHAR(300)             DEFAULT NULL,
    PRIMARY KEY (payslip_id),
    UNIQUE KEY uq_ps_period_emp (period_id, emp_id),
    CONSTRAINT fk_ps_period FOREIGN KEY (period_id) REFERENCES payroll_periods(period_id) ON DELETE CASCADE,
    CONSTRAINT fk_ps_emp    FOREIGN KEY (emp_id)    REFERENCES employees(emp_id)           ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 10: audit_logs
-- ============================================================
CREATE TABLE audit_logs (
    log_id      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED             DEFAULT NULL,
    username    VARCHAR(50)              DEFAULT NULL,
    role        VARCHAR(20)              DEFAULT NULL,
    action      VARCHAR(100)    NOT NULL,
    target      VARCHAR(100)             DEFAULT NULL,
    details     TEXT                     DEFAULT NULL,
    ip_address  VARCHAR(45)              DEFAULT NULL,
    status      ENUM('success','failed') NOT NULL DEFAULT 'success',
    logged_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    INDEX idx_audit_user   (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_date   (logged_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 11: system_settings
-- ============================================================
CREATE TABLE system_settings (
    setting_key     VARCHAR(80)     NOT NULL,
    setting_value   VARCHAR(255)    NOT NULL,
    description     VARCHAR(200)             DEFAULT NULL,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 12: notifications
-- ============================================================
CREATE TABLE notifications (
    notif_id    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NOT NULL,
    title       VARCHAR(120)    NOT NULL,
    message     TEXT            NOT NULL,
    type        ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    link        VARCHAR(200)             DEFAULT NULL,
    is_read     TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notif_id),
    INDEX idx_notif_user   (user_id),
    INDEX idx_notif_unread (user_id, is_read),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 13: working_days
-- ============================================================
CREATE TABLE working_days (
    wd_id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    emp_id          VARCHAR(20)     NOT NULL,
    period_month    TINYINT         NOT NULL,
    period_year     SMALLINT        NOT NULL,
    working_days    TINYINT         NOT NULL DEFAULT 30,
    notes           VARCHAR(200)             DEFAULT NULL,
    submitted_by    INT UNSIGNED             DEFAULT NULL,
    submitted_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (wd_id),
    UNIQUE KEY uq_wd_emp_period (emp_id, period_month, period_year),
    CONSTRAINT fk_wd_emp  FOREIGN KEY (emp_id)       REFERENCES employees(emp_id) ON DELETE CASCADE,
    CONSTRAINT fk_wd_user FOREIGN KEY (submitted_by) REFERENCES users(user_id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 14: password_resets
-- ============================================================
CREATE TABLE password_resets (
    reset_id    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NOT NULL,
    token       VARCHAR(64)     NOT NULL UNIQUE,
    expires_at  DATETIME        NOT NULL,
    used        TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reset_id),
    INDEX idx_reset_token   (token),
    INDEX idx_reset_user    (user_id),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 15: contact_messages
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_messages (
    msg_id      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    full_name   VARCHAR(100)    NOT NULL,
    email       VARCHAR(180)    NOT NULL,
    subject     VARCHAR(100)             DEFAULT NULL,
    message     TEXT            NOT NULL,
    ip_address  VARCHAR(45)              DEFAULT NULL,
    is_read     TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (msg_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Default data
-- ============================================================

-- Departments
INSERT INTO departments (dept_name) VALUES
('Computer Science'),('Electrical Engineering'),('Civil Engineering'),
('Mechanical Engineering'),('Architecture'),('Water Resources'),
('Chemical Engineering'),('Industrial Engineering'),('Administration'),
('Finance'),('Human Resources'),('Library'),('IT Support');

-- System settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('pension_employee_rate',   '0.11', 'Employee pension contribution rate (11% of basic)'),
('pension_employer_rate',   '0.18', 'Employer pension contribution rate (18% of basic)'),
('credit_association_rate', '0.00', 'Credit Association rate (0% default)'),
('renaissance_dam_rate',    '0.00', 'Renaissance Dam GERD rate (0% default)'),
('session_timeout',         '1800', 'Session timeout in seconds (30 minutes)');

-- Default admin account  (password: Admin@2025)
INSERT INTO users (username, password, role, full_name, email) VALUES
('admin',
 '$2y$12$KerV8WU3DPW6xN0dpwBHNunby5mGqHsHAXUU5Ldn1Y9iAZJYIdFxxa',
 'admin',
 'System Administrator',
 'admin@bit.edu.et');
