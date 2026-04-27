-- Add last_name column to employees table
-- Run this once against your payroll_db database

ALTER TABLE employees
    ADD COLUMN last_name VARCHAR(100) NULL DEFAULT NULL
    AFTER full_name;
