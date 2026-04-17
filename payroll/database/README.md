# BiT Payroll — Database

## Files

| File | Purpose |
|------|---------|
| `payroll_db.sql` | Full schema — tables, indexes, seed data, views |
| `db_connect.php` | PDO connection singleton — include in all pages |
| `setup.php` | One-click installer — run once then delete |

## How to Set Up

### Option A — Setup Script (easiest)
1. Make sure XAMPP Apache + MySQL are running
2. Open: `http://localhost/payroll/database/setup.php`
3. The script creates the database and all tables automatically
4. **Delete `setup.php` after setup**

### Option B — phpMyAdmin
1. Open `http://localhost/phpmyadmin`
2. Click **Import** → choose `payroll_db.sql` → click **Go**

### Option C — MySQL CLI
```bash
mysql -u root -p < payroll_db.sql
```

---

## Tables

| Table | Description |
|-------|-------------|
| `users` | Login accounts for all roles (Admin, HR, Finance, Employee) |
| `departments` | BiT faculties and offices |
| `employees` | Core employee records managed by HR |
| `allowances` | Per-employee allowance configuration |
| `payroll_periods` | Monthly payroll batches |
| `payroll_records` | Per-employee salary calculations per period |
| `payslips` | Generated payslip file references |
| `employee_status_history` | Audit trail for status changes |
| `audit_logs` | System-wide action log |
| `system_settings` | Configurable parameters (pension rates, etc.) |

## Views

| View | Description |
|------|-------------|
| `vw_active_employees` | Active employees with department name |
| `vw_current_allowances` | Latest allowances per employee |
| `vw_payroll_summary` | Aggregated payroll totals per period |

## Relationships

```
users ──────────────── employees (1:1 via user_id)
departments ─────────── employees (1:N via dept_id)
employees ───────────── allowances (1:N)
employees ───────────── payroll_records (1:N)
employees ───────────── employee_status_history (1:N)
employees ───────────── payslips (1:N)
payroll_periods ──────── payroll_records (1:N)
payroll_records ──────── payslips (1:1)
users ───────────────── audit_logs (1:N)
```

## Tax Rules (2025)

| Taxable Income (ETB/month) | Rate | Deduction |
|---------------------------|------|-----------|
| 0 – 2,000 | 0% (Exempt) | 0 |
| 2,001 – 4,000 | 15% | 300 |
| 4,001 – 7,000 | 20% | 500 |
| 7,001 – 10,000 | 25% | 850 |
| 10,001 – 14,000 | 30% | 1,350 |
| Over 14,000 | 35% | 2,050 |

**Taxable Income** = Gross Salary − Employee Pension (7% of basic)

## Pension Rules (2025)
- Employee contribution: **7%** of basic salary
- Employer contribution: **11%** of basic salary

## Default Login
- Username: `admin`
- Password: `Admin@2025`
- **Change immediately after first login**
