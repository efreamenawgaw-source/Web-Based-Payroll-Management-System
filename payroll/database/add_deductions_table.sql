-- ============================================================
-- Add deductions table to payroll_db
-- Run in phpMyAdmin → payroll_db → SQL tab
-- ============================================================

USE payroll_db;

CREATE TABLE IF NOT EXISTS deductions (
    deduction_id        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    emp_id              VARCHAR(20)     NOT NULL,
    -- Fixed deduction types matching BiT payroll structure
    credit_association  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,  -- Credit Association
    renaissance_dam     DECIMAL(10,2)   NOT NULL DEFAULT 0.00,  -- Renaissance Dam (GERD)
    loan_repayment      DECIMAL(10,2)   NOT NULL DEFAULT 0.00,  -- Loan repayment
    penalty             DECIMAL(10,2)   NOT NULL DEFAULT 0.00,  -- Penalty / absence
    other               DECIMAL(10,2)   NOT NULL DEFAULT 0.00,  -- Any other deduction
    description         TEXT                     DEFAULT NULL,
    effective_month     TINYINT         NOT NULL,               -- 1–12
    effective_year      SMALLINT        NOT NULL,
    status              ENUM('active','applied','cancelled')    NOT NULL DEFAULT 'active',
    created_by          INT UNSIGNED             DEFAULT NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                 ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (deduction_id),
    UNIQUE  KEY uq_ded_emp_period (emp_id, effective_month, effective_year),
    INDEX   idx_ded_emp    (emp_id),
    INDEX   idx_ded_period (effective_year, effective_month),
    INDEX   idx_ded_status (status),
    CONSTRAINT fk_ded_emp     FOREIGN KEY (emp_id)     REFERENCES employees(emp_id) ON DELETE CASCADE,
    CONSTRAINT fk_ded_created FOREIGN KEY (created_by) REFERENCES users(user_id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-employee deductions: Credit Association, Renaissance Dam, Loan, Penalty, Other';

-- Add deduction rates to system_settings
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES
('credit_association_rate', '0.10', 'Credit Association deduction rate (10% of basic salary)'),
('renaissance_dam_rate',    '0.01', 'Renaissance Dam (GERD) deduction rate (1% of basic salary)');

-- ============================================================
-- Add working_days table
-- HR enters working days per employee per period before payroll
-- ============================================================

CREATE TABLE IF NOT EXISTS working_days (
    wd_id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    emp_id          VARCHAR(20)     NOT NULL,
    period_month    TINYINT         NOT NULL,   -- 1–12
    period_year     SMALLINT        NOT NULL,
    working_days    TINYINT         NOT NULL DEFAULT 30,  -- actual days worked
    notes           VARCHAR(200)             DEFAULT NULL,
    submitted_by    INT UNSIGNED             DEFAULT NULL,  -- FK → users (HR)
    submitted_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (wd_id),
    UNIQUE  KEY uq_wd_emp_period (emp_id, period_month, period_year),
    INDEX   idx_wd_period (period_year, period_month),
    CONSTRAINT fk_wd_emp  FOREIGN KEY (emp_id)        REFERENCES employees(emp_id) ON DELETE CASCADE,
    CONSTRAINT fk_wd_user FOREIGN KEY (submitted_by)  REFERENCES users(user_id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='HR-submitted working days per employee per payroll period';

-- ============================================================
-- Add profile_photo to users table
-- ============================================================
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(300) DEFAULT NULL
    AFTER email;

-- ============================================================
-- Notifications table
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-user notifications';

-- Create uploads directory placeholder
-- (create folder manually: payroll/assets/uploads/profiles/)

-- ============================================================
-- Password reset tokens table
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    reset_id    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NOT NULL,
    token       VARCHAR(64)     NOT NULL UNIQUE,
    expires_at  DATETIME        NOT NULL,
    used        TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reset_id),
    INDEX idx_reset_token   (token),
    INDEX idx_reset_user    (user_id),
    INDEX idx_reset_expires (expires_at),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Password reset tokens — expire after 30 minutes';

-- ============================================================
-- Password reset tokens table
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Password reset tokens — expire after 30 minutes';
