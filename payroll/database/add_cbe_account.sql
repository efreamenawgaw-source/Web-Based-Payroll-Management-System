-- Add CBE bank account fields to employees table
-- Run once against payroll_db

ALTER TABLE employees
    ADD COLUMN cbe_account_number VARCHAR(20)  NULL DEFAULT NULL AFTER last_name,
    ADD COLUMN cbe_account_name   VARCHAR(150) NULL DEFAULT NULL AFTER cbe_account_number;
