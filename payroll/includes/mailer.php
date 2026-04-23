<?php
// ============================================================
// BiT Payroll &mdash; Email Helper using PHPMailer + Gmail SMTP
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/phpmailer/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/SMTP.php';

// ── Gmail SMTP Configuration ───────────────────────────────
// IMPORTANT: Use a Gmail App Password, NOT your real Gmail password.
// Steps to get App Password:
//   1. Go to myaccount.google.com → Security
//   2. Enable 2-Step Verification
//   3. Go to Security → App passwords
//   4. Select "Mail" + "Windows Computer" → Generate
//   5. Copy the 16-character password and paste below

define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'your_gmail@gmail.com');   // ← Change to your Gmail
define('MAIL_PASSWORD', 'xxxx xxxx xxxx xxxx');    // ← Change to your App Password
define('MAIL_FROM',     'your_gmail@gmail.com');   // ← Same as username
define('MAIL_FROM_NAME','BiT Payroll System');
define('MAIL_ENABLED',  true);  // Set false to disable all emails

/**
 * Send an HTML email &mdash; reads config from DB or falls back to constants
 */
function sendMail(string $to_email, string $to_name, string $subject, string $html_body): array {
    // Try to load settings from DB
    $host     = MAIL_HOST;
    $port     = MAIL_PORT;
    $username = MAIL_USERNAME;
    $password = MAIL_PASSWORD;
    $from     = MAIL_FROM;
    $from_name= MAIL_FROM_NAME;
    $enabled  = MAIL_ENABLED;

    try {
        require_once __DIR__ . '/../database/db_connect.php';
        $pdo  = getDB();
        $rows = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'mail_%'")
                    ->fetchAll(\PDO::FETCH_KEY_PAIR);
        if (!empty($rows)) {
            $host      = $rows['mail_host']      ?? $host;
            $port      = (int)($rows['mail_port']?? $port);
            $username  = $rows['mail_username']  ?? $username;
            $password  = $rows['mail_password']  ?? $password;
            $from      = $rows['mail_from']      ?? $from;
            $from_name = $rows['mail_from_name'] ?? $from_name;
            $enabled   = ($rows['mail_enabled']  ?? '1') === '1';
        }
    } catch (\Exception $e) { /* use constants */ }

    if (!$enabled) {
        return ['success' => false, 'error' => 'Email sending is disabled in settings.'];
    }

    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address: ' . $to_email];
    }

    if (empty($username) || $username === 'your_gmail@gmail.com') {
        return ['success' => false, 'error' => 'Gmail not configured. Go to Admin → Email Settings.'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($from ?: $username, $from_name);
        $mail->addReplyTo($from ?: $username, $from_name);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $html_body));

        $mail->send();
        return ['success' => true, 'error' => ''];

    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Build the welcome email HTML for new accounts
 */
function buildWelcomeEmail(string $full_name, string $username, string $password,
                            string $role, string $login_url): string {
    $role_label = ucfirst($role);
    $year       = date('Y');

    $role_desc = match($role) {
        'admin'    => 'You have been granted <strong>Administrator</strong> access to manage users, roles, and system settings.',
        'hr'       => 'You have been assigned the <strong>HR Personnel</strong> role to manage employee records, allowances, and working days.',
        'finance'  => 'You have been assigned the <strong>Finance Officer</strong> role to process payroll, verify calculations, and generate payslips.',
        'employee' => 'You can now <strong>view your payslips</strong>, check your salary details, and manage your personal profile.',
        default    => 'Your account has been created successfully.',
    };

    return '
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Welcome to BiT Payroll System</title>
</head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:\'Segoe UI\',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <!-- Header -->
  <tr>
    <td style="background:linear-gradient(135deg,#0D47A1,#1565C0,#1976D2);
               border-radius:12px 12px 0 0;padding:36px 40px;text-align:center;">
      <div style="display:inline-block;background:white;border-radius:12px;
                  padding:10px 20px;margin-bottom:16px;">
        <span style="font-size:1.6rem;font-weight:900;color:#1565C0;letter-spacing:-1px;">BiT</span>
      </div>
      <h1 style="color:white;margin:0;font-size:1.5rem;font-weight:700;">
        Welcome to BiT Payroll System
      </h1>
      <p style="color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:0.95rem;">
        Bahir Dar Institute of Technology
      </p>
    </td>
  </tr>

  <!-- Body -->
  <tr>
    <td style="background:white;padding:40px;">

      <p style="font-size:1.1rem;color:#263238;margin:0 0 16px;">
        Dear <strong>' . htmlspecialchars($full_name) . '</strong>,
      </p>

      <p style="color:#546E7A;line-height:1.7;margin:0 0 20px;">
        We are <strong>delighted to welcome you</strong> to the Bahir Dar Institute of Technology family!
        Your account on the <strong>BiT Payroll Management System</strong> has been created successfully.
      </p>

      <p style="color:#546E7A;line-height:1.7;margin:0 0 24px;">
        ' . $role_desc . '
      </p>

      <!-- Credentials Box -->
      <table width="100%" cellpadding="0" cellspacing="0"
             style="background:#E3F2FD;border-radius:10px;border-left:4px solid #1565C0;
                    margin:0 0 28px;">
        <tr>
          <td style="padding:20px 24px;">
            <p style="font-size:0.8rem;color:#1565C0;font-weight:700;text-transform:uppercase;
                      letter-spacing:1px;margin:0 0 14px;">
              🔐 Your Login Credentials
            </p>
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="padding:6px 0;color:#546E7A;font-size:0.9rem;width:120px;">Username:</td>
                <td style="padding:6px 0;">
                  <strong style="font-size:1rem;color:#0D47A1;font-family:monospace;
                                 background:white;padding:4px 12px;border-radius:6px;
                                 border:1px solid #BBDEFB;">
                    ' . htmlspecialchars($username) . '
                  </strong>
                </td>
              </tr>
              <tr>
                <td style="padding:6px 0;color:#546E7A;font-size:0.9rem;">Password:</td>
                <td style="padding:6px 0;">
                  <strong style="font-size:1rem;color:#0D47A1;font-family:monospace;
                                 background:white;padding:4px 12px;border-radius:6px;
                                 border:1px solid #BBDEFB;">
                    ' . htmlspecialchars($password) . '
                  </strong>
                </td>
              </tr>
              <tr>
                <td style="padding:6px 0;color:#546E7A;font-size:0.9rem;">Role:</td>
                <td style="padding:6px 0;">
                  <span style="background:#1565C0;color:white;padding:3px 12px;
                               border-radius:20px;font-size:0.82rem;font-weight:600;">
                    ' . $role_label . '
                  </span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- Security Warning -->
      <table width="100%" cellpadding="0" cellspacing="0"
             style="background:#FFF3E0;border-radius:8px;border-left:4px solid #F57F17;
                    margin:0 0 28px;">
        <tr>
          <td style="padding:14px 18px;">
            <p style="color:#E65100;font-size:0.85rem;margin:0;">
              ⚠️ <strong>Important:</strong> Please change your password immediately after your first login
              for security purposes. Go to <em>My Profile → Change Password</em>.
            </p>
          </td>
        </tr>
      </table>

      <!-- Login Button -->
      <div style="text-align:center;margin:0 0 28px;">
        <a href="' . htmlspecialchars($login_url) . '"
           style="display:inline-block;background:linear-gradient(135deg,#1565C0,#1976D2);
                  color:white;text-decoration:none;padding:14px 40px;border-radius:8px;
                  font-weight:700;font-size:1rem;letter-spacing:0.3px;">
          → Login to BiT Payroll System
        </a>
      </div>

      <!-- Features -->
      <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
        <tr>
          <td style="padding:0 0 12px;">
            <p style="font-size:0.82rem;color:#90A4AE;font-weight:700;text-transform:uppercase;
                      letter-spacing:1px;margin:0 0 12px;">What you can do:</p>
          </td>
        </tr>
        <tr>
          <td>
            <table width="100%" cellpadding="0" cellspacing="0">
              ' . getFeatureRows($role) . '
            </table>
          </td>
        </tr>
      </table>

      <p style="color:#546E7A;line-height:1.7;margin:0;">
        If you have any questions or need assistance, please contact the
        <strong>BiT IT Department</strong> or your system administrator.
      </p>

    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#0D47A1;border-radius:0 0 12px 12px;padding:24px 40px;text-align:center;">
      <p style="color:rgba(255,255,255,0.9);font-size:0.85rem;margin:0 0 6px;">
        <strong>Bahir Dar Institute of Technology</strong>
      </p>
      <p style="color:rgba(255,255,255,0.6);font-size:0.78rem;margin:0;">
        Bahir Dar, Amhara Region, Ethiopia &nbsp;|&nbsp;
        payroll@bit.edu.et &nbsp;|&nbsp;
        &copy; ' . $year . ' BiT Payroll System
      </p>
      <p style="color:rgba(255,255,255,0.4);font-size:0.72rem;margin:8px 0 0;">
        This is an automated message. Please do not reply to this email.
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
}

/**
 * Role-specific feature bullets for welcome email
 */
function getFeatureRows(string $role): string {
    $features = match($role) {
        'admin' => [
            '👥 Manage all user accounts and assign roles',
            '🔐 Monitor system security and audit logs',
            '⚙️ Configure system settings and tax brackets',
        ],
        'hr' => [
            '👤 Register and manage employee records',
            '💰 Set allowances and deductions',
            '📅 Submit working days for payroll processing',
            '🔄 Update employee status and history',
        ],
        'finance' => [
            '💼 Process monthly payroll calculations',
            '✅ Verify and approve payroll data',
            '📄 Generate electronic payslips for all employees',
            '📊 View financial reports and summaries',
        ],
        'employee' => [
            '📄 View and download your monthly payslips',
            '💰 Check your salary breakdown and deductions',
            '👤 Update your personal profile and photo',
            '🔔 Receive notifications about your payroll',
        ],
        default => ['Access the BiT Payroll Management System'],
    };

    $rows = '';
    foreach ($features as $f) {
        $rows .= '
        <tr>
          <td style="padding:5px 0;color:#546E7A;font-size:0.88rem;">
            ' . $f . '
          </td>
        </tr>';
    }
    return $rows;
}

/**
 * Build password reset email HTML
 */
function buildPasswordResetEmail(string $full_name, string $username,
                                  string $new_password, string $login_url): string {
    $year = date('Y');
    return '
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Password Reset &mdash; BiT Payroll</title></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:\'Segoe UI\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <tr>
    <td style="background:linear-gradient(135deg,#0D47A1,#1565C0);
               border-radius:12px 12px 0 0;padding:32px 40px;text-align:center;">
      <div style="display:inline-block;background:white;border-radius:10px;padding:8px 18px;margin-bottom:14px;">
        <span style="font-size:1.4rem;font-weight:900;color:#1565C0;">BiT</span>
      </div>
      <h1 style="color:white;margin:0;font-size:1.3rem;font-weight:700;">Password Reset</h1>
      <p style="color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:0.9rem;">
        BiT Payroll Management System
      </p>
    </td>
  </tr>

  <tr>
    <td style="background:white;padding:36px 40px;">
      <p style="font-size:1rem;color:#263238;margin:0 0 14px;">
        Dear <strong>' . htmlspecialchars($full_name) . '</strong>,
      </p>
      <p style="color:#546E7A;line-height:1.7;margin:0 0 22px;">
        Your password on the <strong>BiT Payroll System</strong> has been reset by the administrator.
        Here are your updated login credentials:
      </p>

      <table width="100%" cellpadding="0" cellspacing="0"
             style="background:#E3F2FD;border-radius:10px;border-left:4px solid #1565C0;margin:0 0 22px;">
        <tr>
          <td style="padding:18px 22px;">
            <p style="font-size:0.78rem;color:#1565C0;font-weight:700;text-transform:uppercase;margin:0 0 12px;">
              🔐 Updated Credentials
            </p>
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="padding:5px 0;color:#546E7A;font-size:0.88rem;width:110px;">Username:</td>
                <td><strong style="font-family:monospace;color:#0D47A1;background:white;
                                   padding:3px 10px;border-radius:5px;border:1px solid #BBDEFB;">
                  ' . htmlspecialchars($username) . '
                </strong></td>
              </tr>
              <tr>
                <td style="padding:5px 0;color:#546E7A;font-size:0.88rem;">New Password:</td>
                <td><strong style="font-family:monospace;color:#0D47A1;background:white;
                                   padding:3px 10px;border-radius:5px;border:1px solid #BBDEFB;">
                  ' . htmlspecialchars($new_password) . '
                </strong></td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <table width="100%" cellpadding="0" cellspacing="0"
             style="background:#FFEBEE;border-radius:8px;border-left:4px solid #C62828;margin:0 0 24px;">
        <tr>
          <td style="padding:12px 16px;">
            <p style="color:#C62828;font-size:0.85rem;margin:0;">
              ⚠️ <strong>Security Alert:</strong> If you did not request this password reset,
              please contact your administrator immediately.
            </p>
          </td>
        </tr>
      </table>

      <div style="text-align:center;margin:0 0 24px;">
        <a href="' . htmlspecialchars($login_url) . '"
           style="display:inline-block;background:#1565C0;color:white;text-decoration:none;
                  padding:12px 36px;border-radius:8px;font-weight:700;font-size:0.95rem;">
          → Login Now
        </a>
      </div>

      <p style="color:#546E7A;font-size:0.85rem;line-height:1.7;margin:0;">
        Please change your password after logging in via <em>My Profile → Change Password</em>.
      </p>
    </td>
  </tr>

  <tr>
    <td style="background:#0D47A1;border-radius:0 0 12px 12px;padding:20px 40px;text-align:center;">
      <p style="color:rgba(255,255,255,0.6);font-size:0.78rem;margin:0;">
        &copy; ' . $year . ' Bahir Dar Institute of Technology &mdash; BiT Payroll System
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
}

