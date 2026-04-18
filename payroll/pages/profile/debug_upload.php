<?php
session_start();
echo "<pre style='font-family:monospace;font-size:13px;padding:20px;background:#f5f5f5;'>";
echo "=== UPLOAD DEBUG ===\n\n";

$doc_root    = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$project_url = substr($script_name, 0, strpos($script_name, '/pages/'));
$uploads_url = $project_url . '/assets/uploads/profiles/';
$uploads_fs  = str_replace('/', DIRECTORY_SEPARATOR, $doc_root . $project_url . '/assets/uploads/profiles/');

echo "DOCUMENT_ROOT : " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "SCRIPT_NAME   : " . $_SERVER['SCRIPT_NAME']   . "\n";
echo "project_url   : " . $project_url . "\n";
echo "uploads_url   : " . $uploads_url . "\n";
echo "uploads_fs    : " . $uploads_fs  . "\n\n";

echo "Folder exists  : " . (is_dir($uploads_fs)     ? '✅ YES' : '❌ NO') . "\n";
echo "Folder writable: " . (is_writable($uploads_fs) ? '✅ YES' : '❌ NO') . "\n\n";

$files = is_dir($uploads_fs) ? array_diff(scandir($uploads_fs), ['.','..']) : [];
echo "Files in folder: " . count($files) . "\n";
foreach ($files as $f) echo "  → $f\n";

echo "\n=== DATABASE ===\n";
if (isset($_SESSION['user_id'])) {
    require_once '../../database/db_connect.php';
    $pdo = getDB();
    try {
        $s = $pdo->prepare("SELECT user_id, username, full_name, profile_photo FROM users WHERE user_id=?");
        $s->execute([$_SESSION['user_id']]);
        $u = $s->fetch();
        echo "user_id      : " . $u['user_id']       . "\n";
        echo "username     : " . $u['username']       . "\n";
        echo "full_name    : " . $u['full_name']      . "\n";
        echo "profile_photo: " . ($u['profile_photo'] ?? 'NULL (not set)') . "\n";

        if ($u['profile_photo']) {
            $img_url = $uploads_url . rawurlencode($u['profile_photo']);
            echo "\nPhoto URL: " . $img_url . "\n";
            echo "</pre>";
            echo '<img src="'.htmlspecialchars($img_url).'" style="width:120px;height:120px;object-fit:cover;border-radius:50%;border:3px solid #1565C0;margin:10px;">';
            echo "<pre>";
        }
    } catch (Exception $e) {
        echo "❌ DB ERROR: " . $e->getMessage() . "\n";
        echo "→ Run: ALTER TABLE users ADD COLUMN profile_photo VARCHAR(300) DEFAULT NULL AFTER email;\n";
    }
}

echo "\n=== SESSION ===\n";
echo "user_id      : " . ($_SESSION['user_id']       ?? 'NOT SET') . "\n";
echo "profile_photo: " . ($_SESSION['profile_photo'] ?? 'NOT SET') . "\n";

// Handle test upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['test_photo']['name'])) {
    $f    = $_FILES['test_photo'];
    $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $dest = $uploads_fs . 'test_' . time() . '.' . $ext;
    echo "\n=== TEST UPLOAD ===\n";
    echo "tmp_name: " . $f['tmp_name'] . "\n";
    echo "dest:     " . $dest . "\n";
    echo "result:   " . (move_uploaded_file($f['tmp_name'], $dest) ? '✅ SUCCESS' : '❌ FAILED') . "\n";
}

echo "\n=== END ===\n</pre>";
?>
<!-- Test upload form -->
<form method="POST" enctype="multipart/form-data" style="padding:20px;">
    <input type="file" name="test_photo" accept="image/*">
    <button type="submit" style="padding:8px 16px;background:#1565C0;color:white;border:none;border-radius:6px;cursor:pointer;">
        Test Upload
    </button>
</form>
