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

// ── Paths — use DOCUMENT_ROOT for maximum reliability ─────
// DOCUMENT_ROOT on XAMPP = C:\xampp\htdocs
// We need the subfolder path from SCRIPT_NAME
// e.g. SCRIPT_NAME = /Web_based payroll system/payroll/pages/profile/my_profile.php
// Project URL root  = /Web_based payroll system/payroll
// Uploads URL       = /Web_based payroll system/payroll/assets/uploads/profiles/
// Uploads FS        = C:\xampp\htdocs\Web_based payroll system\payroll\assets\uploads\profiles\

$doc_root    = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);

// Extract project root: everything up to /pages/profile/my_profile.php
// /Web_based payroll system/payroll/pages/profile/my_profile.php
// → /Web_based payroll system/payroll
$project_url = substr($script_name, 0, strpos($script_name, '/pages/'));

$uploads_url = $project_url . '/assets/uploads/profiles/';
$uploads_fs  = $doc_root    . $project_url . '/assets/uploads/profiles/';

// Normalize backslashes for Windows
$uploads_fs  = str_replace('/', DIRECTORY_SEPARATOR, $uploads_fs);

// Create folder if missing
if (!is_dir($uploads_fs)) {
    mkdir($uploads_fs, 0755, true);
}

// ── Load current user ──────────────────────────────────────
$user_stmt = $pdo->prepare("
    SELECT u.*,
           e.emp_id, e.full_name AS emp_name, e.last_name, e.gender, e.date_of_birth,
           e.phone AS emp_phone, e.address, e.position, e.employment_type,
           e.basic_salary, e.employment_date, e.status AS emp_status,
           e.cbe_account_number, e.cbe_account_name,
           d.dept_name
    FROM   users u
    LEFT JOIN employees e ON e.user_id = u.user_id
    LEFT JOIN departments d ON e.dept_id = d.dept_id
    WHERE  u.user_id = ?
");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// ── HANDLE PROFILE PHOTO UPLOAD ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    if (!empty($_FILES['profile_photo']['name'])) {
        $file     = $_FILES['profile_photo'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed  = ['jpg','jpeg','png','gif','webp'];
        $max_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($ext, $allowed)) {
            $error = 'Only JPG, PNG, GIF, WEBP files are allowed.';
        } elseif ($file['size'] > $max_size) {
            $error = 'File size must be under 2MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload error. Please try again.';
        } else {
            $filename    = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $upload_path = $uploads_fs . $filename;

            // Delete old photo
            if ($user['profile_photo']) {
                $old_path = $uploads_fs . $user['profile_photo'];
                if (file_exists($old_path)) unlink($old_path);
            }

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $pdo->prepare("UPDATE users SET profile_photo = ? WHERE user_id = ?")
                    ->execute([$filename, $user_id]);
                $_SESSION['profile_photo'] = $filename;
                $user['profile_photo']     = $filename;
                $success = 'Profile photo updated successfully.';

                try {
                    $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type)
                        VALUES (?, 'Profile Photo Updated', 'Your profile photo has been updated.', 'success')
                    ")->execute([$user_id]);
                } catch (Exception $e) { /* ignore */ }

            } else {
                $error = 'Failed to save photo. Please try again or contact the administrator.';
            }
        }
    }
}

// ── HANDLE PROFILE INFO UPDATE ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name          = trim($_POST['full_name'] ?? '');
    $last_name          = trim($_POST['last_name'] ?? '') ?: null;
    $email              = trim($_POST['email']     ?? '') ?: null;
    $phone              = trim($_POST['phone']     ?? '') ?: null;
    $address            = trim($_POST['address']   ?? '') ?: null;
    // NOTE: cbe_account_number and cbe_account_name are NOT updatable by the employee

    $errs = [];

    if (empty($full_name))
        $errs[] = 'Full name is required.';
    elseif (strlen($full_name) < 2 || strlen($full_name) > 100)
        $errs[] = 'Full name must be 2–100 characters.';
    elseif (!preg_match('/^[\p{L}\s\'\-\.]+$/u', $full_name))
        $errs[] = 'Full name may only contain letters, spaces, hyphens, apostrophes, and dots.';

    if ($last_name !== null && strlen($last_name) > 100)
        $errs[] = 'Last name must be 100 characters or fewer.';

    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errs[] = 'Please enter a valid email address.';

    if ($phone !== null && !preg_match('/^(\+251|0)[0-9]{8,13}$/', preg_replace('/[\s\-]/', '', $phone)))
        $errs[] = 'Phone must be a valid Ethiopian number (e.g. +251911234567 or 0911234567).';

    if ($address !== null && strlen($address) > 255)
        $errs[] = 'Address must be 255 characters or fewer.';

    if (!empty($errs)) {
        $error = implode('<br>', $errs);
    } else {
        try {
            // Update users table
            $pdo->prepare("
                UPDATE users SET full_name = ?, email = ? WHERE user_id = ?
            ")->execute([$full_name, $email, $user_id]);

            // Update employees table if linked (no CBE fields here)
            if ($user['emp_id']) {
                $pdo->prepare("
                    UPDATE employees
                    SET full_name = ?, last_name = ?, email = ?, phone = ?, address = ?
                    WHERE emp_id = ?
                ")->execute([
                    $full_name, $last_name, $email, $phone, $address,
                    $user['emp_id']
                ]);
            }

            // Update session
            $_SESSION['name']          = $full_name;
            $user['full_name']         = $full_name;
            $user['last_name']         = $last_name;
            $user['email']             = $email;

            // Notification
            $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, link)
                VALUES (?, 'Profile Updated', 'Your personal information has been updated successfully.', 'success', '/pages/profile/my_profile.php')
            ")->execute([$user_id]);

            $success = 'Profile information updated successfully.';
        } catch (PDOException $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}

// ── HANDLE PASSWORD CHANGE ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'] ?? '';   // do NOT trim
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
        $error = 'New passwords do not match.';
    } else {
        $hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")
            ->execute([$hash, $user_id]);

        // Regenerate session after password change
        session_regenerate_id(true);

        // Audit log
        $pdo->prepare("
            INSERT INTO audit_logs (user_id, username, role, action, details, ip_address)
            VALUES (?, ?, ?, 'Change Password', 'Password changed by user', ?)
        ")->execute([$user_id, $_SESSION['username'], $_SESSION['role'], $_SERVER['REMOTE_ADDR'] ?? null]);

        // Notification
        $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, 'Password Changed', 'Your password was changed successfully. If this was not you, contact admin immediately.', 'warning')
        ")->execute([$user_id]);

        $success = 'Password changed successfully.';
    }
}

// ── Photo URL (reuse $uploads_url defined at top) ─────────
$photo_url = $user['profile_photo']
    ? $uploads_url . rawurlencode($user['profile_photo'])
    : null;
$initials  = strtoupper(substr($user['full_name'], 0, 1));

require_once $depth . 'includes/header.php';
?>

<div class="breadcrumb">
    <i class="fas fa-home"></i><span>/</span><span>My Profile</span>
</div>

<div class="page-header">
    <h1>My Profile</h1>
    <p>Update your personal information, profile photo, and password.</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- ── Profile Header ── -->
<div class="card mb-3">
    <div class="card-body">
        <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">

            <!-- Avatar / Photo with upload -->
            <div style="flex-shrink:0;text-align:center;">

                <!-- Photo display -->
                <div style="position:relative;display:inline-block;cursor:pointer;"
                     onclick="document.getElementById('photoInput').click()"
                     title="Click to change photo">

                    <!-- Current photo or initials -->
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
                                background:rgba(0,0,0,0.45);
                                display:flex;flex-direction:column;align-items:center;
                                justify-content:center;gap:4px;
                                opacity:0;transition:opacity 0.2s;color:white;"
                         onmouseover="this.style.opacity='1'"
                         onmouseout="this.style.opacity='0'">
                        <i class="fas fa-camera" style="font-size:1.4rem;"></i>
                        <span style="font-size:0.65rem;font-weight:600;">Change</span>
                    </div>
                </div>

                <!-- Upload hint -->
                <p style="font-size:0.72rem;color:var(--gray-400);margin:8px 0 0;text-align:center;">
                    Click photo to change
                </p>

                <!-- Hidden upload form -->
                <form method="POST" enctype="multipart/form-data" id="photoForm">
                    <input type="hidden" name="upload_photo" value="1">
                    <input type="file" id="photoInput" name="profile_photo"
                           accept="image/*"
                           style="display:none;"
                           onchange="previewAndUpload(this)">
                </form>
            </div>

            <!-- User Info -->
            <div style="flex:1;min-width:0;">
                <h2 style="margin:0 0 4px;"><?= htmlspecialchars($user['full_name']) ?></h2>
                <p style="color:var(--gray-600);margin:0 0 8px;">
                    <?= htmlspecialchars($user['username']) ?>
                    &nbsp;·&nbsp;
                    <span class="badge badge-primary"><?= ucfirst($user['role']) ?></span>
                    <?php if ($user['emp_id']): ?>
                    &nbsp;·&nbsp;
                    <span class="badge badge-gray"><?= htmlspecialchars($user['emp_id']) ?></span>
                    <?php endif; ?>
                </p>
                <?php if ($user['dept_name']): ?>
                <p style="color:var(--gray-400);font-size:0.85rem;margin:0 0 12px;">
                    <i class="fas fa-building"></i>
                    <?= htmlspecialchars($user['position'] ?? '') ?>
                    — <?= htmlspecialchars($user['dept_name']) ?>
                </p>
                <?php endif; ?>

                <!-- Upload button (alternative to clicking photo) -->
                <button type="button"
                        onclick="document.getElementById('photoInput').click()"
                        class="btn btn-secondary btn-sm">
                    <i class="fas fa-camera"></i> Change Profile Photo
                </button>
                <p style="font-size:0.75rem;color:var(--gray-400);margin:6px 0 0;">
                    JPG, PNG, GIF or WEBP — max 2MB — from any folder on your computer
                </p>
            </div>

        </div>
    </div>
</div>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- ── Update Personal Info ── -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-edit" style="color:var(--primary);margin-right:8px"></i>
                Update Personal Information
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Full Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="full_name" class="form-control"
                           value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name (Father's Name)</label>
                    <input type="text" name="last_name" class="form-control"
                           value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
                           placeholder="e.g. Simane">
                    <span class="form-hint">Optional &mdash; 3rd name / family name</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                           placeholder="your@email.com">
                </div>
                <?php if ($user['emp_id']): ?>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-control"
                           value="<?= htmlspecialchars($user['emp_phone'] ?? '') ?>"
                           placeholder="+251 9XX XXX XXX">
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control"
                           value="<?= htmlspecialchars($user['address'] ?? '') ?>"
                           placeholder="Your address">
                </div>

                <!-- CBE Bank Account — read-only, set by HR -->
                <div style="background:var(--info-light);border-radius:var(--radius);
                            padding:14px;margin-bottom:16px;border-left:3px solid var(--info);">
                    <p style="font-size:0.75rem;font-weight:700;color:var(--info);
                               text-transform:uppercase;margin:0 0 10px;">
                        <i class="fas fa-university"></i> CBE Bank Account (salary transfer)
                    </p>
                    <?php if ($user['cbe_account_number']): ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:0.85rem;">
                        <div>
                            <p style="font-size:0.7rem;color:var(--info);margin:0;text-transform:uppercase;">Account Number</p>
                            <p style="font-weight:700;font-family:monospace;letter-spacing:1px;margin:0;color:var(--gray-800);">
                                <?= htmlspecialchars($user['cbe_account_number']) ?>
                            </p>
                        </div>
                        <div>
                            <p style="font-size:0.7rem;color:var(--info);margin:0;text-transform:uppercase;">Account Holder</p>
                            <p style="font-weight:700;margin:0;color:var(--gray-800);">
                                <?= htmlspecialchars($user['cbe_account_name'] ?? '&mdash;') ?>
                            </p>
                        </div>
                    </div>
                    <p style="font-size:0.72rem;color:var(--info);margin:8px 0 0;">
                        <i class="fas fa-lock"></i>
                        Bank account details are managed by HR. Contact HR to update.
                    </p>
                    <?php else: ?>
                    <p style="font-size:0.82rem;color:var(--warning);margin:0;">
                        <i class="fas fa-exclamation-triangle"></i>
                        No CBE account on file. Contact HR to add your bank account.
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Read-only fields -->
                <?php if ($user['emp_id']): ?>
                <div style="background:var(--bg-light);border-radius:var(--radius);padding:12px;margin-bottom:16px;">
                    <p style="font-size:0.75rem;color:var(--gray-400);margin:0 0 8px;text-transform:uppercase;font-weight:700;">
                        Read-only (managed by HR)
                    </p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:0.82rem;">
                        <div>
                            <span style="color:var(--gray-400);">Department:</span>
                            <strong> <?= htmlspecialchars($user['dept_name'] ?? '—') ?></strong>
                        </div>
                        <div>
                            <span style="color:var(--gray-400);">Position:</span>
                            <strong> <?= htmlspecialchars($user['position'] ?? '—') ?></strong>
                        </div>
                        <div>
                            <span style="color:var(--gray-400);">Basic Salary:</span>
                            <strong> ETB <?= number_format($user['basic_salary'] ?? 0, 2) ?></strong>
                        </div>
                        <div>
                            <span style="color:var(--gray-400);">Status:</span>
                            <strong> <?= ucfirst(str_replace('_',' ',$user['emp_status'] ?? '—')) ?></strong>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <button type="submit" name="update_profile" class="btn btn-primary w-100">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>

    <!-- ── Change Password ── -->
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
                    <div class="input-wrapper" style="position:relative;">
                        <input type="password" name="current_password" id="cur_pass"
                               class="form-control" placeholder="Enter current password" required>
                        <button type="button" onclick="togglePwd('cur_pass','eye1')"
                                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                       background:none;border:none;cursor:pointer;color:var(--gray-400);">
                            <i class="fas fa-eye" id="eye1"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password <span style="color:var(--danger)">*</span></label>
                    <div class="input-wrapper" style="position:relative;">
                        <input type="password" name="new_password" id="new_pass"
                               class="form-control" placeholder="Min. 6 characters"
                               oninput="checkStrength(this.value)" required>
                        <button type="button" onclick="togglePwd('new_pass','eye2')"
                                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                       background:none;border:none;cursor:pointer;color:var(--gray-400);">
                            <i class="fas fa-eye" id="eye2"></i>
                        </button>
                    </div>
                    <!-- Strength bar -->
                    <div style="margin-top:6px;">
                        <div style="height:4px;background:var(--gray-200);border-radius:4px;overflow:hidden;">
                            <div id="strengthBar" style="height:100%;width:0;border-radius:4px;transition:all 0.3s;"></div>
                        </div>
                        <p id="strengthText" style="font-size:0.72rem;margin:3px 0 0;color:var(--gray-400);"></p>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password <span style="color:var(--danger)">*</span></label>
                    <div class="input-wrapper" style="position:relative;">
                        <input type="password" name="confirm_password" id="conf_pass"
                               class="form-control" placeholder="Repeat new password" required>
                        <button type="button" onclick="togglePwd('conf_pass','eye3')"
                                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                       background:none;border:none;cursor:pointer;color:var(--gray-400);">
                            <i class="fas fa-eye" id="eye3"></i>
                        </button>
                    </div>
                </div>

                <div class="alert alert-info" style="font-size:0.82rem;">
                    <i class="fas fa-info-circle"></i>
                    Use at least <strong>8 characters</strong> with at least 1 uppercase letter and 1 number for a strong password.
                </div>

                <button type="submit" name="change_password" class="btn btn-warning w-100">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </form>
        </div>
    </div>

</div>

<!-- ── Account Info ── -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-info-circle" style="color:var(--primary);margin-right:8px"></i>
            Account Information
        </h3>
    </div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
            <?php
            $acc_info = [
                ['Username',    $user['username'],                                          'fas fa-user-circle'],
                ['Role',        ucfirst($user['role']),                                     'fas fa-user-tag'],
                ['Account Status', $user['is_active'] ? 'Active' : 'Inactive',             'fas fa-check-circle'],
                ['Last Login',  $user['last_login']
                                ? date('M d, Y H:i', strtotime($user['last_login']))
                                : 'First login',                                            'fas fa-clock'],
                ['Member Since',date('M d, Y', strtotime($user['created_at'])),             'fas fa-calendar'],
                ['Profile Photo',$user['profile_photo'] ? 'Uploaded' : 'Not set',          'fas fa-camera'],
            ];
            foreach ($acc_info as $ai): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px;
                        background:var(--bg-light);border-radius:var(--radius);">
                <i class="<?= $ai[2] ?>" style="color:var(--primary);font-size:1.1rem;width:20px;text-align:center;flex-shrink:0;"></i>
                <div>
                    <p style="font-size:0.7rem;color:var(--gray-400);margin:0;text-transform:uppercase;"><?= $ai[0] ?></p>
                    <p style="font-weight:600;margin:0;font-size:0.88rem;"><?= htmlspecialchars($ai[1]) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePwd(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Password strength checker
function checkStrength(val) {
    const bar  = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    let score  = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { pct: '20%', color: '#C62828', label: 'Very Weak' },
        { pct: '40%', color: '#E65100', label: 'Weak' },
        { pct: '60%', color: '#F57F17', label: 'Fair' },
        { pct: '80%', color: '#2E7D32', label: 'Strong' },
        { pct: '100%',color: '#1B5E20', label: 'Very Strong' },
    ];
    const lvl = levels[Math.max(0, score - 1)] || levels[0];
    bar.style.width     = val.length ? lvl.pct : '0';
    bar.style.background= lvl.color;
    text.textContent    = val.length ? lvl.label : '';
    text.style.color    = lvl.color;
}

// ── Profile photo: preview then auto-submit ────────────────
function previewAndUpload(input) {
    if (!input.files || !input.files[0]) return;

    const file   = input.files[0];
    const reader = new FileReader();

    reader.onload = function(e) {
        // Show preview immediately
        const preview  = document.getElementById('photoPreview');
        const initials = document.getElementById('photoInitials');

        if (preview) {
            preview.src           = e.target.result;
            preview.style.display = 'block';
        }
        if (initials) {
            initials.style.display = 'none';
        }

        // Submit the form to upload
        document.getElementById('photoForm').submit();
    };

    reader.readAsDataURL(file);
}
</script>

<?php require_once $depth . 'includes/footer.php'; ?>
