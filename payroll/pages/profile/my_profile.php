<?php
session_start();
$page_title = 'My Profile';
$active_nav = 'my_profile';
$depth      = '../../';
require_once $depth . 'database/db_connect.php';

$pdo     = getDB();
$success = '';
$error   = '';

$user_id = (int)$_SESSION['user_id'];

// -- Build upload paths from SCRIPT_NAME so spaces in folder names are handled --
$doc_root    = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$project_url = substr($script_name, 0, strpos($script_name, '/pages/'));
$uploads_url = $project_url . '/assets/uploads/profiles/';
$uploads_fs  = str_replace('/', DIRECTORY_SEPARATOR,
                   $doc_root . $project_url . '/assets/uploads/profiles/');

if (!is_dir($uploads_fs)) {
    mkdir($uploads_fs, 0755, true);
}

// -- Load current user record --
$user_stmt = $pdo->prepare("
    SELECT u.*,
           e.emp_id, e.position, e.status AS emp_status,
           d.dept_name
    FROM   users u
    LEFT JOIN employees e  ON e.user_id = u.user_id
    LEFT JOIN departments d ON e.dept_id = d.dept_id
    WHERE  u.user_id = ?
");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// ============================================================
// HANDLE: Profile photo upload
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    if (!empty($_FILES['profile_photo']['name'])) {
        $file    = $_FILES['profile_photo'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowed)) {
            $error = 'Only JPG, PNG, GIF, or WEBP files are allowed.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $error = 'File size must be under 2 MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload error. Please try again.';
        } else {
            $filename    = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $upload_path = $uploads_fs . $filename;

            // Remove old photo file if it exists
            if ($user['profile_photo']) {
                $old = $uploads_fs . $user['profile_photo'];
                if (file_exists($old)) unlink($old);
            }

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $pdo->prepare("UPDATE users SET profile_photo = ? WHERE user_id = ?")
                    ->execute([$filename, $user_id]);
                $_SESSION['profile_photo'] = $filename;
                $user['profile_photo']     = $filename;
                $success = 'Profile photo updated successfully.';
            } else {
                $error = 'Failed to save photo. Please try again.';
            }
        }
    }
}

// ============================================================
// HANDLE: Username update
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_username'])) {
    $new_username = strtolower(trim($_POST['username'] ?? ''));

    if (empty($new_username)) {
        $error = 'Username is required.';
    } elseif (strlen($new_username) < 3 || strlen($new_username) > 30) {
        $error = 'Username must be 3–30 characters.';
    } elseif (!preg_match('/^[a-z0-9_\.]+$/', $new_username)) {
        $error = 'Username may only contain lowercase letters, numbers, underscores, and dots.';
    } else {
        // Check if username is already taken by another user
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $chk->execute([$new_username, $user_id]);
        if ($chk->fetch()) {
            $error = 'Username <strong>' . htmlspecialchars($new_username) . '</strong> is already taken.';
        } else {
            $pdo->prepare("UPDATE users SET username = ? WHERE user_id = ?")
                ->execute([$new_username, $user_id]);
            $_SESSION['username']  = $new_username;
            $user['username']      = $new_username;

            // Audit log
            $pdo->prepare("
                INSERT INTO audit_logs (user_id, username, role, action, details, ip_address)
                VALUES (?, ?, ?, 'Update Username', 'Username changed', ?)
            ")->execute([$user_id, $new_username, $_SESSION['role'], $_SERVER['REMOTE_ADDR'] ?? null]);

            $success = 'Username updated successfully.';
        }
    }
}

// ============================================================
// HANDLE: Password change
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass     = $_POST['new_password']     ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
        $error = 'All password fields are required.';
    } elseif (!password_verify($current_pass, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new_pass) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $new_pass)) {
        $error = 'New password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_pass)) {
        $error = 'New password must contain at least one number.';
    } elseif ($new_pass !== $confirm_pass) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")
            ->execute([$hash, $user_id]);

        // Regenerate session ID after password change for security
        session_regenerate_id(true);

        // Audit log
        $pdo->prepare("
            INSERT INTO audit_logs (user_id, username, role, action, details, ip_address)
            VALUES (?, ?, ?, 'Change Password', 'Password changed by user', ?)
        ")->execute([$user_id, $_SESSION['username'], $_SESSION['role'], $_SERVER['REMOTE_ADDR'] ?? null]);

        // In-app notification
        try {
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (?, 'Password Changed',
                        'Your password was changed. If this was not you, contact the administrator immediately.',
                        'warning')
            ")->execute([$user_id]);
        } catch (Exception $e) { /* ignore if notifications table missing */ }

        $success = 'Password changed successfully.';
    }
}

// Build photo URL for display
$photo_url = $user['profile_photo']
    ? $uploads_url . rawurlencode($user['profile_photo'])
    : null;
$initials = strtoupper(substr($user['full_name'], 0, 1));

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <i class="fas fa-home"></i><span>/</span><span>My Profile</span>
</div>

<div class="page-header">
    <h1>My Profile</h1>
    <p>Update your profile photo, username, and password.</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
<?php endif; ?>

<!-- ============================================================
     PROFILE HEADER — photo + identity summary
     ============================================================ -->
<div class="card mb-3">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">

            <!-- Clickable avatar -->
            <div style="flex-shrink:0;text-align:center;">
                <div style="position:relative;display:inline-block;cursor:pointer;"
                     onclick="document.getElementById('photoInput').click()"
                     title="Click to change photo">

                    <img id="photoPreview"
                         src="<?= $photo_url ? htmlspecialchars($photo_url) : '' ?>"
                         alt="Profile Photo"
                         style="width:100px;height:100px;border-radius:50%;object-fit:cover;
                                border:3px solid var(--primary);box-shadow:var(--shadow-md);
                                display:<?= $photo_url ? 'block' : 'none' ?>;">

                    <div id="photoInitials"
                         style="width:100px;height:100px;border-radius:50%;background:var(--primary);
                                display:<?= $photo_url ? 'none' : 'flex' ?>;
                                align-items:center;justify-content:center;
                                color:white;font-size:2.5rem;font-weight:700;
                                border:3px solid var(--accent-light);box-shadow:var(--shadow-md);">
                        <?= $initials ?>
                    </div>

                    <!-- Camera overlay on hover -->
                    <div style="position:absolute;inset:0;border-radius:50%;
                                background:rgba(0,0,0,0.45);display:flex;flex-direction:column;
                                align-items:center;justify-content:center;gap:4px;
                                opacity:0;transition:opacity 0.2s;color:white;"
                         onmouseover="this.style.opacity='1'"
                         onmouseout="this.style.opacity='0'">
                        <i class="fas fa-camera" style="font-size:1.4rem;"></i>
                        <span style="font-size:0.65rem;font-weight:600;">Change</span>
                    </div>
                </div>

                <p style="font-size:0.72rem;color:var(--gray-400);margin:8px 0 0;">
                    Click photo to change
                </p>

                <!-- Hidden photo upload form — auto-submits on file select -->
                <form method="POST" enctype="multipart/form-data" id="photoForm">
                    <input type="hidden" name="upload_photo" value="1">
                    <input type="file" id="photoInput" name="profile_photo"
                           accept="image/*" style="display:none;"
                           onchange="previewAndUpload(this)">
                </form>
            </div>

            <!-- Identity summary -->
            <div style="flex:1;min-width:0;">
                <h2 style="margin:0 0 4px;"><?= htmlspecialchars($user['full_name']) ?></h2>
                <p style="color:var(--gray-600);margin:0 0 6px;">
                    <i class="fas fa-user-circle" style="color:var(--primary);"></i>
                    <?= htmlspecialchars($user['username']) ?>
                    &nbsp;&bull;&nbsp;
                    <span class="badge badge-primary"><?= ucfirst($user['role']) ?></span>
                    <?php if ($user['emp_id']): ?>
                    &nbsp;&bull;&nbsp;
                    <span class="badge badge-gray"><?= htmlspecialchars($user['emp_id']) ?></span>
                    <?php endif; ?>
                </p>
                <?php if ($user['dept_name']): ?>
                <p style="color:var(--gray-400);font-size:0.85rem;margin:0 0 12px;">
                    <i class="fas fa-building"></i>
                    <?= htmlspecialchars($user['position'] ?? '') ?>
                    &mdash; <?= htmlspecialchars($user['dept_name']) ?>
                </p>
                <?php endif; ?>
                <button type="button"
                        onclick="document.getElementById('photoInput').click()"
                        class="btn btn-secondary btn-sm">
                    <i class="fas fa-camera"></i> Change Photo
                </button>
                <span style="font-size:0.75rem;color:var(--gray-400);margin-left:8px;">
                    JPG, PNG, GIF or WEBP &mdash; max 2 MB
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     TWO-COLUMN: Username  |  Change Password
     ============================================================ -->
<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- Update Username -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-edit" style="color:var(--primary);margin-right:8px"></i>
                Update Username
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Username <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="username" class="form-control"
                           value="<?= htmlspecialchars($user['username']) ?>"
                           placeholder="e.g. john.doe"
                           autocomplete="username" required>
                    <span class="form-hint">
                        Lowercase letters, numbers, underscores and dots only (3–30 characters).
                    </span>
                </div>

                <!-- Read-only account info -->
                <div style="background:var(--bg-light);border-radius:var(--radius);
                            padding:12px 14px;margin-bottom:16px;">
                    <p style="font-size:0.72rem;color:var(--gray-400);margin:0 0 8px;
                               text-transform:uppercase;font-weight:700;">
                        Account Info (read-only)
                    </p>
                    <div style="display:flex;flex-direction:column;gap:6px;font-size:0.85rem;">
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--gray-400);">Full Name</span>
                            <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--gray-400);">Role</span>
                            <span class="badge badge-primary"><?= ucfirst($user['role']) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--gray-400);">Email</span>
                            <strong><?= htmlspecialchars($user['email'] ?? '—') ?></strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--gray-400);">Last Login</span>
                            <strong>
                                <?= $user['last_login']
                                    ? date('M d, Y H:i', strtotime($user['last_login']))
                                    : 'First login' ?>
                            </strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--gray-400);">Member Since</span>
                            <strong><?= date('M d, Y', strtotime($user['created_at'])) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info" style="font-size:0.82rem;margin-bottom:16px;">
                    <i class="fas fa-info-circle"></i>
                    Changing your username will update it on your next login.
                    Other profile details (name, salary, department) are managed by HR or Admin.
                </div>

                <button type="submit" name="update_username" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i> Save Username
                </button>
            </form>
        </div>
    </div>

    <!-- Change Password -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-lock" style="color:var(--primary);margin-right:8px"></i>
                Change Password
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" novalidate>
                <div class="form-group">
                    <label class="form-label">Current Password <span style="color:var(--danger)">*</span></label>
                    <div style="position:relative;">
                        <input type="password" name="current_password" id="cur_pass"
                               class="form-control" placeholder="Enter current password"
                               autocomplete="current-password" required>
                        <button type="button" onclick="togglePwd('cur_pass','eye1')"
                                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                       background:none;border:none;cursor:pointer;color:var(--gray-400);">
                            <i class="fas fa-eye" id="eye1"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">New Password <span style="color:var(--danger)">*</span></label>
                    <div style="position:relative;">
                        <input type="password" name="new_password" id="new_pass"
                               class="form-control" placeholder="Min. 8 characters"
                               autocomplete="new-password"
                               oninput="checkStrength(this.value)" required>
                        <button type="button" onclick="togglePwd('new_pass','eye2')"
                                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                       background:none;border:none;cursor:pointer;color:var(--gray-400);">
                            <i class="fas fa-eye" id="eye2"></i>
                        </button>
                    </div>
                    <!-- Password strength bar -->
                    <div style="margin-top:6px;">
                        <div style="height:4px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                            <div id="strengthBar"
                                 style="height:100%;width:0;border-radius:4px;transition:all 0.3s;"></div>
                        </div>
                        <p id="strengthText"
                           style="font-size:0.72rem;margin:3px 0 0;color:var(--gray-400);"></p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm New Password <span style="color:var(--danger)">*</span></label>
                    <div style="position:relative;">
                        <input type="password" name="confirm_password" id="conf_pass"
                               class="form-control" placeholder="Repeat new password"
                               autocomplete="new-password"
                               oninput="checkMatch()" required>
                        <button type="button" onclick="togglePwd('conf_pass','eye3')"
                                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                       background:none;border:none;cursor:pointer;color:var(--gray-400);">
                            <i class="fas fa-eye" id="eye3"></i>
                        </button>
                    </div>
                    <p id="matchText" style="font-size:0.72rem;margin:3px 0 0;"></p>
                </div>

                <div class="alert alert-info" style="font-size:0.82rem;margin-bottom:16px;">
                    <i class="fas fa-info-circle"></i>
                    At least <strong>8 characters</strong> with 1 uppercase letter and 1 number.
                </div>

                <button type="submit" name="change_password" class="btn btn-warning w-100">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </form>
        </div>
    </div>

</div>

<script>
// Toggle password field visibility
function togglePwd(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}

// Live password strength indicator
function checkStrength(val) {
    const bar  = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    let score  = 0;
    if (val.length >= 8)           score++;
    if (val.length >= 12)          score++;
    if (/[A-Z]/.test(val))         score++;
    if (/[0-9]/.test(val))         score++;
    if (/[^A-Za-z0-9]/.test(val))  score++;

    const levels = [
        { pct: '20%',  color: '#C62828', label: 'Very Weak' },
        { pct: '40%',  color: '#E65100', label: 'Weak' },
        { pct: '60%',  color: '#F57F17', label: 'Fair' },
        { pct: '80%',  color: '#2E7D32', label: 'Strong' },
        { pct: '100%', color: '#1B5E20', label: 'Very Strong' },
    ];
    const lvl = levels[Math.max(0, score - 1)] || levels[0];
    bar.style.width      = val.length ? lvl.pct : '0';
    bar.style.background = lvl.color;
    text.textContent     = val.length ? lvl.label : '';
    text.style.color     = lvl.color;
    checkMatch();
}

// Live password match indicator
function checkMatch() {
    const p1   = document.getElementById('new_pass').value;
    const p2   = document.getElementById('conf_pass').value;
    const text = document.getElementById('matchText');
    if (!p2) { text.textContent = ''; return; }
    if (p1 === p2) {
        text.textContent  = '&#10003; Passwords match';
        text.style.color  = '#2E7D32';
    } else {
        text.textContent  = '&#10007; Passwords do not match';
        text.style.color  = '#C62828';
    }
}

// Preview photo locally then auto-submit the upload form
function previewAndUpload(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function (e) {
        const preview  = document.getElementById('photoPreview');
        const initials = document.getElementById('photoInitials');
        if (preview)  { preview.src = e.target.result; preview.style.display = 'block'; }
        if (initials) { initials.style.display = 'none'; }
        document.getElementById('photoForm').submit();
    };
    reader.readAsDataURL(input.files[0]);
}
</script>

<?php require_once $depth . 'includes/footer.php'; ?>
