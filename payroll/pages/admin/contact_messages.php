<?php
session_start();
$page_title = 'Contact Messages';
$active_nav = 'contact_messages';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';
require_once $depth . 'includes/mailer.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . $depth . 'pages/auth/login.php');
    exit;
}

$pdo = getDB();

// ── Ensure tables exist ────────────────────────────────────
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

$pdo->exec("
    CREATE TABLE IF NOT EXISTS contact_replies (
        reply_id     INT          AUTO_INCREMENT PRIMARY KEY,
        message_id   INT          NOT NULL,
        admin_id     INT          NOT NULL,
        admin_name   VARCHAR(120) NOT NULL,
        reply_text   TEXT         NOT NULL,
        sent_via     ENUM('email','system') NOT NULL DEFAULT 'email',
        mail_status  VARCHAR(255) NULL,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES contact_messages(message_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$success = '';
$error   = '';

// ── HANDLE REPLY ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $msg_id     = (int)($_POST['message_id'] ?? 0);
    $reply_text = trim($_POST['reply_text'] ?? '');

    if (!$msg_id || empty($reply_text)) {
        $error = 'Reply message cannot be empty.';
    } else {
        // Load original message
        $orig = $pdo->prepare("SELECT * FROM contact_messages WHERE message_id = ?");
        $orig->execute([$msg_id]);
        $orig_msg = $orig->fetch();

        if (!$orig_msg) {
            $error = 'Original message not found.';
        } else {
            // Build reply email HTML
            $year       = date('Y');
            $admin_name = $_SESSION['name'] ?? 'BiT Admin';
            $sent_at    = date('M d, Y H:i');

            $html = '
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Reply from BiT Payroll</title></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:\'Segoe UI\',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <!-- Header -->
  <tr>
    <td style="background:linear-gradient(135deg,#0D47A1,#1565C0,#1976D2);
               border-radius:12px 12px 0 0;padding:28px 36px;text-align:center;">
      <div style="display:inline-block;background:white;border-radius:10px;
                  padding:8px 18px;margin-bottom:12px;">
        <span style="font-size:1.4rem;font-weight:900;color:#1565C0;">BiT</span>
      </div>
      <h1 style="color:white;margin:0;font-size:1.2rem;font-weight:700;">
        Reply from BiT Payroll System
      </h1>
      <p style="color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:0.88rem;">
        Bahir Dar Institute of Technology
      </p>
    </td>
  </tr>

  <!-- Body -->
  <tr>
    <td style="background:white;padding:32px 36px;">

      <p style="font-size:1rem;color:#263238;margin:0 0 6px;">
        Dear <strong>' . htmlspecialchars($orig_msg['full_name']) . '</strong>,
      </p>
      <p style="color:#546E7A;font-size:0.88rem;margin:0 0 22px;">
        Thank you for contacting us. Here is our response to your message:
      </p>

      <!-- Reply box -->
      <div style="background:#E3F2FD;border-radius:10px;border-left:4px solid #1565C0;
                  padding:20px 24px;margin:0 0 24px;">
        <p style="font-size:0.78rem;color:#1565C0;font-weight:700;text-transform:uppercase;
                  letter-spacing:1px;margin:0 0 10px;">
          Response from ' . htmlspecialchars($admin_name) . '
        </p>
        <p style="color:#263238;line-height:1.8;margin:0;white-space:pre-wrap;">'
          . htmlspecialchars($reply_text) .
        '</p>
      </div>

      <!-- Original message quote -->
      <div style="background:#F5F7FA;border-radius:8px;border-left:3px solid #90A4AE;
                  padding:14px 18px;margin:0 0 24px;">
        <p style="font-size:0.75rem;color:#90A4AE;font-weight:700;text-transform:uppercase;
                  margin:0 0 8px;">Your original message</p>
        <p style="font-size:0.82rem;color:#546E7A;margin:0 0 4px;">
          <strong>Subject:</strong> ' . htmlspecialchars($orig_msg['subject']) . '
        </p>
        <p style="font-size:0.82rem;color:#546E7A;margin:0 0 4px;">
          <strong>Sent:</strong> ' . date('M d, Y H:i', strtotime($orig_msg['created_at'])) . '
        </p>
        <p style="font-size:0.82rem;color:#546E7A;line-height:1.7;margin:8px 0 0;
                  white-space:pre-wrap;">'
          . htmlspecialchars(mb_substr($orig_msg['message'], 0, 400))
          . (mb_strlen($orig_msg['message']) > 400 ? '...' : '') .
        '</p>
      </div>

      <p style="color:#546E7A;font-size:0.85rem;line-height:1.7;margin:0;">
        If you have further questions, please feel free to contact us again via the
        <a href="' . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '') . '/home.php#contact"
           style="color:#1565C0;">BiT Payroll contact form</a>
        or call us at <strong>+251 58 220 6112</strong>.
      </p>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#0D47A1;border-radius:0 0 12px 12px;padding:20px 36px;text-align:center;">
      <p style="color:rgba(255,255,255,0.9);font-size:0.85rem;margin:0 0 4px;">
        <strong>Bahir Dar Institute of Technology</strong>
      </p>
      <p style="color:rgba(255,255,255,0.6);font-size:0.75rem;margin:0;">
        Bahir Dar, Amhara Region, Ethiopia &nbsp;|&nbsp; payroll@bit.edu.et
        &nbsp;|&nbsp; &copy; ' . $year . ' BiT Payroll System
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';

            // Send email
            $mail_result = sendMail(
                $orig_msg['email'],
                $orig_msg['full_name'],
                'Re: ' . $orig_msg['subject'] . ' — BiT Payroll',
                $html
            );

            $mail_status = $mail_result['success']
                ? 'sent'
                : ('failed: ' . ($mail_result['error'] ?? 'unknown'));

            // Save reply to DB
            $pdo->prepare("
                INSERT INTO contact_replies
                    (message_id, admin_id, admin_name, reply_text, sent_via, mail_status)
                VALUES (?, ?, ?, ?, 'email', ?)
            ")->execute([
                $msg_id,
                $_SESSION['user_id'],
                $admin_name,
                $reply_text,
                $mail_status
            ]);

            // Mark original as replied
            $pdo->prepare("
                UPDATE contact_messages
                SET is_read = 1, replied_at = NOW()
                WHERE message_id = ?
            ")->execute([$msg_id]);

            if ($mail_result['success']) {
                $success = "Reply sent successfully to <strong>" . htmlspecialchars($orig_msg['email']) . "</strong>.";
            } else {
                // Reply saved but email failed
                $error = "Reply saved but email could not be sent: " . htmlspecialchars($mail_result['error'] ?? '')
                       . ". Check Admin &rarr; Email Settings.";
            }

            // Redirect to keep the view open
            $redirect_url = 'contact_messages.php?view=' . $msg_id;
            if ($success) {
                $redirect_url .= '&sent=1';
            }
            header('Location: ' . $redirect_url);
            exit;
        }
    }
}

// ── Mark as read ───────────────────────────────────────────
if (!empty($_GET['read'])) {
    $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE message_id = ?")
        ->execute([(int)$_GET['read']]);
    header('Location: contact_messages.php?view=' . (int)$_GET['read']);
    exit;
}

// ── Delete ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $pdo->prepare("DELETE FROM contact_messages WHERE message_id = ?")
        ->execute([(int)$_POST['delete_id']]);
    header('Location: contact_messages.php');
    exit;
}

// ── Mark all read ──────────────────────────────────────────
if (!empty($_GET['mark_all_read'])) {
    $pdo->exec("UPDATE contact_messages SET is_read = 1");
    header('Location: contact_messages.php');
    exit;
}

// ── Flash messages from redirect ──────────────────────────
if (!empty($_GET['sent'])) {
    $success = 'Reply sent successfully.';
}

// ── View single message + its replies ─────────────────────
$view_msg = null;
$replies  = [];
if (!empty($_GET['view'])) {
    $vs = $pdo->prepare("SELECT * FROM contact_messages WHERE message_id = ?");
    $vs->execute([(int)$_GET['view']]);
    $view_msg = $vs->fetch();

    if ($view_msg) {
        // Mark as read
        if (!$view_msg['is_read']) {
            $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE message_id = ?")
                ->execute([$view_msg['message_id']]);
            $view_msg['is_read'] = 1;
        }
        // Load replies
        $rs = $pdo->prepare("
            SELECT * FROM contact_replies
            WHERE message_id = ?
            ORDER BY created_at ASC
        ");
        $rs->execute([$view_msg['message_id']]);
        $replies = $rs->fetchAll();
    }
}

// ── Load message list ──────────────────────────────────────
$filter   = trim($_GET['filter'] ?? 'all');
$where    = $filter === 'unread'   ? 'WHERE is_read = 0 AND replied_at IS NULL'
          : ($filter === 'replied' ? 'WHERE replied_at IS NOT NULL'
          : '');

$messages = $pdo->query("
    SELECT * FROM contact_messages {$where}
    ORDER BY created_at DESC
")->fetchAll();

$unread_count  = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
$total_count   = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
$replied_count = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE replied_at IS NOT NULL")->fetchColumn();

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <a href="dashboard.php">Admin</a><span>/</span><span>Contact Messages</span>
</div>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Contact Messages
            <?php if ($unread_count > 0): ?>
            <span class="badge badge-danger" style="font-size:0.75rem;vertical-align:middle;">
                <?= $unread_count ?> new
            </span>
            <?php endif; ?>
        </h1>
        <p>Messages submitted via the public contact form. Reply directly from here.</p>
    </div>
    <?php if ($unread_count > 0): ?>
    <a href="?mark_all_read=1" class="btn btn-secondary btn-sm"
       onclick="return confirm('Mark all messages as read?')">
        <i class="fas fa-check-double"></i> Mark All Read
    </a>
    <?php endif; ?>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <span><?= $success ?></span></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <span><?= $error ?></span></div>
<?php endif; ?>

<div class="grid-2" style="gap:24px;">

    <!-- ── Message List ── -->
    <div class="card" style="height:fit-content;">
        <div class="card-header" style="flex-wrap:wrap;gap:8px;">
            <h3><i class="fas fa-inbox" style="color:var(--primary);margin-right:8px"></i>Inbox</h3>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <a href="?filter=all"
                   class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
                    All (<?= $total_count ?>)
                </a>
                <a href="?filter=unread"
                   class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-secondary' ?>">
                    Unread (<?= $unread_count ?>)
                </a>
                <a href="?filter=replied"
                   class="btn btn-sm <?= $filter === 'replied' ? 'btn-primary' : 'btn-secondary' ?>">
                    Replied (<?= $replied_count ?>)
                </a>
            </div>
        </div>
        <div class="card-body" style="padding:0;max-height:600px;overflow-y:auto;">
            <?php if (empty($messages)): ?>
            <div class="empty-state" style="padding:40px;">
                <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                <p><?= $filter === 'unread' ? 'No unread messages.' : ($filter === 'replied' ? 'No replied messages yet.' : 'No messages yet.') ?></p>
            </div>
            <?php else: ?>
            <?php foreach ($messages as $m):
                $is_active = isset($_GET['view']) && (int)$_GET['view'] === (int)$m['message_id'];
            ?>
            <a href="contact_messages.php?view=<?= $m['message_id'] ?>&filter=<?= $filter ?>"
               style="display:block;padding:13px 16px;border-bottom:1px solid var(--gray-200);
                      text-decoration:none;
                      background:<?= $is_active ? 'var(--bg-light)' : ($m['is_read'] ? 'white' : '#EFF6FF') ?>;
                      transition:background 0.15s;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                    <div style="min-width:0;flex:1;">
                        <div style="display:flex;align-items:center;gap:7px;margin-bottom:2px;">
                            <?php if (!$m['is_read']): ?>
                            <span style="width:7px;height:7px;border-radius:50%;
                                         background:var(--primary);flex-shrink:0;"></span>
                            <?php endif; ?>
                            <strong style="font-size:0.87rem;color:var(--gray-800);">
                                <?= htmlspecialchars($m['full_name']) ?>
                            </strong>
                            <?php if ($m['replied_at']): ?>
                            <span class="badge badge-success" style="font-size:0.62rem;padding:1px 6px;">
                                <i class="fas fa-reply"></i> replied
                            </span>
                            <?php endif; ?>
                        </div>
                        <p style="font-size:0.8rem;color:var(--primary);margin:0 0 2px;font-weight:600;">
                            <?= htmlspecialchars($m['subject']) ?>
                        </p>
                        <p style="font-size:0.76rem;color:var(--gray-400);margin:0;
                                  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= htmlspecialchars(mb_substr($m['message'], 0, 60)) ?>...
                        </p>
                    </div>
                    <span style="font-size:0.7rem;color:var(--gray-400);white-space:nowrap;flex-shrink:0;">
                        <?= date('M d', strtotime($m['created_at'])) ?>
                    </span>
                </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Message Detail + Reply ── -->
    <div>
        <?php if ($view_msg): ?>

        <!-- Original message card -->
        <div class="card mb-3">
            <div class="card-header">
                <h3><i class="fas fa-envelope-open" style="color:var(--primary);margin-right:8px"></i>
                    <?= htmlspecialchars($view_msg['subject']) ?>
                </h3>
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Delete this message and all its replies permanently?')">
                    <input type="hidden" name="delete_id" value="<?= $view_msg['message_id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm btn-icon-only" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
            <div class="card-body">

                <!-- Sender info bar -->
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;
                            padding:12px 14px;background:var(--bg-light);border-radius:var(--radius);">
                    <div style="width:44px;height:44px;border-radius:50%;background:var(--primary);
                                display:flex;align-items:center;justify-content:center;
                                color:white;font-size:1.1rem;font-weight:700;flex-shrink:0;">
                        <?= strtoupper(substr($view_msg['full_name'], 0, 1)) ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <p style="font-weight:700;margin:0;font-size:0.92rem;">
                            <?= htmlspecialchars($view_msg['full_name']) ?>
                        </p>
                        <a href="mailto:<?= htmlspecialchars($view_msg['email']) ?>"
                           style="font-size:0.82rem;color:var(--primary);">
                            <?= htmlspecialchars($view_msg['email']) ?>
                        </a>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <p style="font-size:0.72rem;color:var(--gray-400);margin:0;">
                            <?= date('M d, Y', strtotime($view_msg['created_at'])) ?>
                        </p>
                        <p style="font-size:0.72rem;color:var(--gray-400);margin:0;">
                            <?= date('H:i', strtotime($view_msg['created_at'])) ?>
                        </p>
                        <?php if ($view_msg['replied_at']): ?>
                        <span class="badge badge-success" style="font-size:0.65rem;margin-top:4px;">
                            <i class="fas fa-reply"></i> Replied
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Subject badge -->
                <div style="margin-bottom:14px;">
                    <span class="badge badge-info">
                        <i class="fas fa-tag"></i> <?= htmlspecialchars($view_msg['subject']) ?>
                    </span>
                </div>

                <!-- Message body -->
                <div style="background:var(--gray-100);border-radius:var(--radius);padding:16px;
                            line-height:1.8;color:var(--gray-800);font-size:0.88rem;
                            white-space:pre-wrap;word-break:break-word;">
                    <?= htmlspecialchars($view_msg['message']) ?>
                </div>
            </div>
        </div>

        <!-- Reply history -->
        <?php if (!empty($replies)): ?>
        <div class="card mb-3">
            <div class="card-header">
                <h3><i class="fas fa-comments" style="color:var(--success);margin-right:8px"></i>
                    Reply History (<?= count($replies) ?>)
                </h3>
            </div>
            <div class="card-body" style="padding:0;">
                <?php foreach ($replies as $r): ?>
                <div style="padding:16px 18px;border-bottom:1px solid var(--gray-200);">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                        <div style="width:34px;height:34px;border-radius:50%;background:var(--success);
                                    display:flex;align-items:center;justify-content:center;
                                    color:white;font-size:0.85rem;font-weight:700;flex-shrink:0;">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div style="flex:1;">
                            <p style="font-weight:700;margin:0;font-size:0.85rem;color:var(--success);">
                                <?= htmlspecialchars($r['admin_name']) ?>
                                <span style="font-weight:400;color:var(--gray-400);font-size:0.78rem;">
                                    (Admin)
                                </span>
                            </p>
                            <p style="font-size:0.72rem;color:var(--gray-400);margin:0;">
                                <?= date('M d, Y H:i', strtotime($r['created_at'])) ?>
                                &nbsp;&bull;&nbsp;
                                <?php if (strpos($r['mail_status'], 'sent') === 0): ?>
                                <span style="color:var(--success);">
                                    <i class="fas fa-check-circle"></i> Email delivered
                                </span>
                                <?php else: ?>
                                <span style="color:var(--warning);">
                                    <i class="fas fa-exclamation-triangle"></i> Email failed
                                </span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div style="background:var(--success-light);border-radius:var(--radius);
                                padding:12px 14px;font-size:0.87rem;line-height:1.7;
                                color:var(--gray-800);white-space:pre-wrap;word-break:break-word;
                                border-left:3px solid var(--success);">
                        <?= htmlspecialchars($r['reply_text']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Reply compose box -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-reply" style="color:var(--primary);margin-right:8px"></i>
                    Reply to <?= htmlspecialchars($view_msg['full_name']) ?>
                </h3>
                <span style="font-size:0.78rem;color:var(--gray-400);">
                    <i class="fas fa-paper-plane"></i>
                    Will be sent to <strong><?= htmlspecialchars($view_msg['email']) ?></strong>
                </span>
            </div>
            <div class="card-body">
                <form method="POST" action="contact_messages.php?view=<?= $view_msg['message_id'] ?>&filter=<?= $filter ?>">
                    <input type="hidden" name="message_id" value="<?= $view_msg['message_id'] ?>">

                    <!-- Quick reply templates -->
                    <div style="margin-bottom:12px;">
                        <p style="font-size:0.75rem;color:var(--gray-400);margin:0 0 6px;
                                  text-transform:uppercase;font-weight:700;">
                            Quick Templates
                        </p>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php
                            $templates = [
                                'Acknowledged'  => "Dear {$view_msg['full_name']},\n\nThank you for contacting BiT Payroll Support. We have received your message and will get back to you within 1-2 business days.\n\nBest regards,\nBiT IT Department",
                                'Issue Resolved' => "Dear {$view_msg['full_name']},\n\nThank you for reaching out. We are pleased to inform you that your issue has been resolved. Please log in to the system and verify.\n\nIf you experience any further issues, do not hesitate to contact us.\n\nBest regards,\nBiT IT Department",
                                'Need More Info' => "Dear {$view_msg['full_name']},\n\nThank you for your message. To assist you better, could you please provide more details about your issue?\n\nBest regards,\nBiT IT Department",
                            ];
                            foreach ($templates as $label => $text): ?>
                            <button type="button"
                                    onclick="setTemplate(<?= json_encode($text) ?>)"
                                    class="btn btn-secondary btn-sm">
                                <?= $label ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Your Reply <span style="color:var(--danger)">*</span>
                        </label>
                        <textarea name="reply_text" id="replyText" class="form-control"
                                  rows="7" required minlength="5"
                                  placeholder="Type your reply here...&#10;&#10;The recipient will receive this via email."
                                  style="resize:vertical;"></textarea>
                        <span class="form-hint">
                            <i class="fas fa-info-circle"></i>
                            This reply will be sent to <strong><?= htmlspecialchars($view_msg['email']) ?></strong>
                            via email and saved in the system.
                        </span>
                    </div>

                    <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </button>
                        <button type="submit" name="send_reply" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Send Reply to <?= htmlspecialchars(explode(' ', $view_msg['full_name'])[0]) ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- No message selected -->
        <div class="card">
            <div class="card-body">
                <div class="empty-state" style="padding:60px 20px;">
                    <div class="empty-icon"><i class="fas fa-envelope-open-text"></i></div>
                    <p>Select a message from the list to read and reply.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
function setTemplate(text) {
    document.getElementById('replyText').value = text;
    document.getElementById('replyText').focus();
}
</script>

<?php require_once $depth . 'includes/footer.php'; ?>
