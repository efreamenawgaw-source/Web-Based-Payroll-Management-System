<?php
// ============================================================
// BiT Payroll — Contact Form API endpoint
// POST /api/contact.php
// Returns JSON: { success: bool, message: string }
// ============================================================

header('Content-Type: application/json; charset=utf-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$depth = '../';
require_once $depth . 'database/db_connect.php';

// ── Read & sanitise input ──────────────────────────────────
$full_name = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email']     ?? '');
$subject   = trim($_POST['subject']   ?? 'General Inquiry');
$message   = trim($_POST['message']   ?? '');
$ip        = $_SERVER['REMOTE_ADDR'] ?? null;

// ── Validate ───────────────────────────────────────────────
$errors = [];

// Full name: letters and spaces only, 2–100 chars
if (empty($full_name) || strlen($full_name) < 2)
    $errors[] = 'Full name is required (min 2 characters).';
elseif (strlen($full_name) > 100)
    $errors[] = 'Full name is too long (max 100 characters).';
elseif (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $full_name))
    $errors[] = 'Full name must contain letters only — no numbers or symbols.';

// Email: must be a valid @gmail.com address
if (empty($email))
    $errors[] = 'Email address is required.';
elseif (!preg_match('/^[a-zA-Z0-9._%+\-]+@gmail\.com$/i', $email))
    $errors[] = 'Email must be a valid Gmail address ending with @gmail.com';
elseif (strlen($email) > 180)
    $errors[] = 'Email address is too long.';

// Message: 10–3000 chars
if (empty($message) || strlen($message) < 10)
    $errors[] = 'Message is required (min 10 characters).';
if (strlen($message) > 3000)
    $errors[] = 'Message is too long (max 3000 characters).';

// Basic spam check — honeypot field
if (!empty($_POST['website'])) {
    // Silently succeed to fool bots
    echo json_encode(['success' => true, 'message' => 'Message sent successfully!']);
    exit;
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ── Save to database ───────────────────────────────────────
try {
    $pdo = getDB();

    // Create table if it doesn't exist yet (safe fallback)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contact_messages (
            message_id   INT          AUTO_INCREMENT PRIMARY KEY,
            full_name    VARCHAR(120) NOT NULL,
            email        VARCHAR(180) NOT NULL,
            subject      VARCHAR(100) NOT NULL DEFAULT 'General Inquiry',
            message      TEXT         NOT NULL,
            ip_address   VARCHAR(45)  NULL,
            is_read      TINYINT(1)   NOT NULL DEFAULT 0,
            replied_at   DATETIME     NULL,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmt = $pdo->prepare("
        INSERT INTO contact_messages (full_name, email, subject, message, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$full_name, $email, $subject, $message, $ip]);
    $message_id = $pdo->lastInsertId();

    // ── Notify admin via in-app notification ──────────────
    try {
        $admin_users = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1");
        foreach ($admin_users->fetchAll() as $admin) {
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, link)
                VALUES (?, ?, ?, 'info', '/pages/admin/contact_messages.php')
            ")->execute([
                $admin['user_id'],
                'New Contact Message',
                "New message from {$full_name} ({$email}): " . mb_substr($subject, 0, 60)
            ]);
        }
    } catch (Exception $e) { /* notifications table may not exist — skip */ }

    // ── Send email to admin (optional — only if mail configured) ──
    try {
        require_once $depth . 'includes/mailer.php';

        // Get admin email from DB
        $admin_email_row = $pdo->query("
            SELECT email FROM users WHERE role = 'admin' AND is_active = 1 AND email IS NOT NULL
            LIMIT 1
        ")->fetch();

        if ($admin_email_row && !empty($admin_email_row['email'])) {
            $year     = date('Y');
            $sent_at  = date('M d, Y H:i');
            $html = '
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>New Contact Message</title></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:\'Segoe UI\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
  <tr>
    <td style="background:linear-gradient(135deg,#0D47A1,#1565C0);
               border-radius:12px 12px 0 0;padding:28px 36px;text-align:center;">
      <div style="display:inline-block;background:white;border-radius:10px;padding:8px 18px;margin-bottom:12px;">
        <span style="font-size:1.4rem;font-weight:900;color:#1565C0;">BiT</span>
      </div>
      <h1 style="color:white;margin:0;font-size:1.2rem;font-weight:700;">New Contact Message</h1>
      <p style="color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:0.88rem;">BiT Payroll System &mdash; Contact Form</p>
    </td>
  </tr>
  <tr>
    <td style="background:white;padding:32px 36px;">
      <p style="color:#546E7A;margin:0 0 20px;font-size:0.95rem;">
        A new message was submitted via the BiT Payroll contact form.
      </p>
      <table width="100%" cellpadding="0" cellspacing="0"
             style="background:#E3F2FD;border-radius:10px;border-left:4px solid #1565C0;margin:0 0 20px;">
        <tr><td style="padding:18px 22px;">
          <table cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td style="padding:6px 0;color:#546E7A;font-size:0.88rem;width:110px;vertical-align:top;">From:</td>
              <td style="padding:6px 0;font-weight:700;color:#0D47A1;">' . htmlspecialchars($full_name) . '</td>
            </tr>
            <tr>
              <td style="padding:6px 0;color:#546E7A;font-size:0.88rem;vertical-align:top;">Email:</td>
              <td style="padding:6px 0;"><a href="mailto:' . htmlspecialchars($email) . '" style="color:#1565C0;">' . htmlspecialchars($email) . '</a></td>
            </tr>
            <tr>
              <td style="padding:6px 0;color:#546E7A;font-size:0.88rem;vertical-align:top;">Subject:</td>
              <td style="padding:6px 0;font-weight:600;">' . htmlspecialchars($subject) . '</td>
            </tr>
            <tr>
              <td style="padding:6px 0;color:#546E7A;font-size:0.88rem;vertical-align:top;">Sent:</td>
              <td style="padding:6px 0;">' . $sent_at . '</td>
            </tr>
          </table>
        </td></tr>
      </table>
      <div style="background:#F5F7FA;border-radius:8px;padding:16px 20px;margin:0 0 20px;border-left:4px solid #90A4AE;">
        <p style="font-size:0.78rem;color:#90A4AE;font-weight:700;text-transform:uppercase;margin:0 0 8px;">Message</p>
        <p style="color:#263238;line-height:1.7;margin:0;white-space:pre-wrap;">' . htmlspecialchars($message) . '</p>
      </div>
      <div style="text-align:center;">
        <a href="' . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '') . '/pages/admin/contact_messages.php"
           style="display:inline-block;background:#1565C0;color:white;text-decoration:none;
                  padding:11px 32px;border-radius:8px;font-weight:700;font-size:0.9rem;">
          View in Admin Panel
        </a>
      </div>
    </td>
  </tr>
  <tr>
    <td style="background:#0D47A1;border-radius:0 0 12px 12px;padding:18px 36px;text-align:center;">
      <p style="color:rgba(255,255,255,0.6);font-size:0.75rem;margin:0;">
        &copy; ' . $year . ' BiT Payroll System &mdash; Bahir Dar Institute of Technology
      </p>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>';

            sendMail(
                $admin_email_row['email'],
                'BiT Admin',
                '[BiT Payroll] New Contact Message: ' . $subject,
                $html
            );
        }
    } catch (Exception $e) { /* email not configured — skip silently */ }

    echo json_encode([
        'success' => true,
        'message' => 'Your message has been sent successfully! We will get back to you soon.'
    ]);

} catch (PDOException $e) {
    error_log('Contact form DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send message. Please try again or contact us directly by phone.'
    ]);
}
