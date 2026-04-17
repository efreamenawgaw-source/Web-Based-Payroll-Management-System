-- Run this in phpMyAdmin to fix the admin password
-- Password: Admin@2025

USE payroll_db;

UPDATE users
SET    password = '$2y$12$KerV8WU3DPW6xN0dpwBHNunby5mGqHsHAXUU5Ldn1Y9iAZJYIdFxxa'
WHERE  username = 'admin';

-- Verify
SELECT user_id, username, role, full_name, is_active FROM users;
