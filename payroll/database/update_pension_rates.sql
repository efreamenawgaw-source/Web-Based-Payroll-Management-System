-- ============================================================
-- Update pension rates: Employee 7%→11%, Employer 11%→18%
-- Run in phpMyAdmin → payroll_db → SQL tab
-- ============================================================

USE payroll_db;

-- Update system settings
UPDATE system_settings SET setting_value = '0.11' WHERE setting_key = 'pension_employee_rate';
UPDATE system_settings SET setting_value = '0.18' WHERE setting_key = 'pension_employer_rate';

-- Verify
SELECT setting_key, setting_value, description FROM system_settings
WHERE setting_key LIKE 'pension%';
