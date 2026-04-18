-- ============================================================
-- Fix: Link employee records to user accounts
-- Run in phpMyAdmin → payroll_db → SQL tab
-- ============================================================

-- Step 1: See which users have no linked employee
SELECT u.user_id, u.username, u.full_name, u.role,
       e.emp_id, e.full_name AS emp_name
FROM users u
LEFT JOIN employees e ON e.user_id = u.user_id
WHERE u.role = 'employee';

-- Step 2: See which employees have no linked user
SELECT e.emp_id, e.full_name, e.user_id
FROM employees e
WHERE e.user_id IS NULL;

-- Step 3: Link an employee to a user (replace values as needed)
-- Example: Link employee EMP-101 to user_id 5
-- UPDATE employees SET user_id = 5 WHERE emp_id = 'EMP-101';

-- To link ALL employees to users by matching full_name:
UPDATE employees e
JOIN users u ON u.full_name = e.full_name AND u.role = 'employee'
SET e.user_id = u.user_id
WHERE e.user_id IS NULL;
