<?php
session_start();
$page_title = 'Email Settings';
$active_nav = 'settings';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/mailer.php';

$pdo     = getDB();
$success = '';
$error   = '';

// ── SAVE email settings ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email'])) {
    $settings = [
        'mail_host'     => trim($_POST['mail_host']     ?? 'smtp.gmail.com'),
        'mail_port'     => trim($_POST['mail_port']     ?? '587'),
        'mail_username' => trim($_POST['mail_username'] ?? ''),
        'mail_password' => trim($_POST['mail_password'] ?? ''),
        'mail_from'     => trim($_POST['mail_from']     ?? ''),
        'mail_from_name'=> trim($_POST['mail_from_name']?? 'BiT Payroll System'),
        'mail_enabled'  => isset($_POST['mail_enabled']) ? '1' : '0',
    ];

    foreach ($settings as $key => $val) {
        $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?
        ")->execute([$key, $val, $_SESSION['user_id'], $val, $_SESSION['user_id']]);
    }
    $success = 'Email settings saved successfully.';
}

// ── SEND TEST EMAIL ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $test_email = trim($_POST['test_email'] ?? '');
    if ($test_email) {
        $html = '<div style="font-family:Arial,sans-serif;padding:20px;">
            <h2 style="color:#1565C0;">✅ BiT Payroll — Test Email</h2>
            <p>This is a test email from the BiT Payroll Management System.</p>
            <p>If you received this, your Gmail SMTP configuration is working correctly!</p>
            <p style="color:#546E7A;font-size:0.85rem;">Sent at: ' . date('Y-m-d H:i:s') . '</p>
        </div>';
        $result = sendMail($test_email, 'Test Recipient', 'BiT Payroll — Test Email', $html);
        if ($result['success']) {
            $success = "✅ Test email sent successfully to <strong>{$test_email}</strong>!";
        } else {
            $error = "❌ Failed to send: " . htmlspecialchars($result['error']);
        }
    }
}

// ── Load current settings ──────────────────────────────────
$settings_stmt = $pdo->query("
    SELECT setting_key, setting_value FROM system_settings
    WHERE setting_key LIKE 'mail_%'
");
$current = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">Admin</a><span>/</span>
    <a href="settings.php">Settings</a><span>/</span>
    <span>Email Settings</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Email Settings</h1>
        <p>Configure Gmail SMTP to send welcome emails and notifications.</p>
    </div>
    <a href="settings.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?= $success ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <span><?= $error ?></span></div>
<?php endif; ?>

<!-- Setup Guide -->
<div class="alert alert-info mb-3">
    <i class="fas fa-info-circle"></i>
    <div>
        <strong>How to set up Gmail SMTP:</strong>
        <ol style="margin:8px 0 0 16px;font-size:0.85rem;line-height:1.8;">
            <li>Go to <a href="https://myaccount.google.com/security" target="_blank">myaccount.google.com/security</a></li>
            <li>Enable <strong>2-Step Verification</strong></li>
            <li>Go to <strong>Security → App passwords</strong></li>
            <li>Select <em>Mail</em> + <em>Windows Computer</em> → click <strong>Generate</strong></li>
            <li>Copy the 16-character password and paste it in the <strong>App Password</strong> field below</li>
        </ol>
    </div>
</div>

<div class="grid-2" style="gap:24px;">

    <!-- SMTP Settings Form -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-envelope" style="color:var(--primary);margin-right:8px"></i>
                Gmail SMTP Configuration
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">SMTP Host</label>
                    <input type="text" name="mail_host" class="form-control"
                           value="<?= htmlspecialchars($current['mail_host'] ?? 'smtp.gmail.com') ?>"
                           readonly style="background:var(--gray-100);">
                    <span class="form-hint">Gmail SMTP host (fixed)</span>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">SMTP Port</label>
                        <input type="number" name="mail_port" class="form-control"
                               value="<?= htmlspecialchars($current['mail_port'] ?? '587') ?>"
                               readonly style="background:var(--gray-100);">
                    </div>
                    <div class="form-group">
                        <label class="form-label">From Name</label>
                        <input type="text" name="mail_from_name" class="form-control"
                               value="<?= htmlspecialchars($current['mail_from_name'] ?? 'BiT Payroll System') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Gmail Address <span style="color:var(--danger)">*</span></label>
                    <input type="email" name="mail_username" class="form-control"
                           value="<?= htmlspecialchars($current['mail_username'] ?? '') ?>"
                           placeholder="your_gmail@gmail.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">From Email</label>
                    <input type="email" name="mail_from" class="form-control"
                           value="<?= htmlspecialchars($current['mail_from'] ?? '') ?>"
                           placeholder="Same as Gmail address">
                    <span class="form-hint">Usually same as Gmail address above</span>
                </div>
                <div class="form-group">
                    <label class="form-label">App Password <span style="color:var(--danger)">*</span></label>
                    <input type="password" name="mail_password" class="form-control"
                           value="<?= htmlspecialchars($current['mail_password'] ?? '') ?>"
                           placeholder="16-character App Password from Google">
                    <span class="form-hint">
                        ⚠️ Use <strong>App Password</strong>, NOT your regular Gmail password.
                        <a href="https://myaccount.google.com/apppasswords" target="_blank">Get App Password →</a>
                    </span>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                        <input type="checkbox" name="mail_enabled"
                               <?= ($current['mail_enabled'] ?? '1') === '1' ? 'checked' : '' ?>
                               style="width:16px;height:16px;accent-color:var(--primary);">
                        <span style="font-weight:600;">Enable email sending</span>
                    </label>
                    <span class="form-hint">Uncheck to disable all outgoing emails (useful for testing)</span>
                </div>
                <button type="submit" name="save_email" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i> Save Email Settings
                </button>
            </form>
        </div>
    </div>

    <!-- Test + Info -->
    <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Test Email -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-paper-plane" style="color:var(--success);margin-right:8px"></i>
                    Send Test Email
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Send test to</label>
                        <input type="email" name="test_email" class="form-control"
                               placeholder="recipient@gmail.com" required>
                    </div>
                    <button type="submit" name="send_test" class="btn btn-success w-100">
                        <i class="fas fa-paper-plane"></i> Send Test Email
                    </button>
                </form>
            </div>
        </div>

        <!-- When emails are sent -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bell" style="color:var(--primary);margin-right:8px"></i>
                    When Emails Are Sent
                </h3>
            </div>
            <div class="card-body" style="padding:0">
                <?php
                $triggers = [
                    ['fas fa-user-plus',   'var(--success)', 'New User Created',    'Welcome email with username & password'],
                    ['fas fa-key',         'var(--warning)', 'Password Reset',       'New password sent to user\'s email'],
                ];
                foreach ($triggers as $t): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;
                            border-bottom:1px solid var(--gray-200);">
                    <i class="<?= $t[0] ?>" style="color:<?= $t[1] ?>;font-size:1.1rem;width:20px;text-align:center;flex-shrink:0;"></i>
                    <div>
                        <p style="font-weight:600;font-size:0.88rem;margin:0;"><?= $t[2] ?></p>
                        <p style="font-size:0.78rem;color:var(--gray-400);margin:0;"><?= $t[3] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                <div style="padding:12px 16px;font-size:0.8rem;color:var(--gray-600);">
                    <i class="fas fa-info-circle" style="color:var(--info);"></i>
                    Emails are only sent if the user has an email address on their account.
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once $depth . 'includes/footer.php'; ?>
