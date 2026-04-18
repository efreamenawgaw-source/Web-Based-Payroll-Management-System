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
