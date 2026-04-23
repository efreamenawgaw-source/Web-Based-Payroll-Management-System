<?php
// ============================================================
// Fix UTF-8 encoding corruption caused by PowerShell
// Run once: http://localhost/payroll/database/fix_encoding.php
// DELETE after use!
// ============================================================
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    die('Access denied.');
}

// Map of corrupted sequences → correct UTF-8
$replacements = [
    "\xc3\xa2\xc2\x80\xc2\x93" => '&mdash;',   // â€" → —
    "\xc3\xa2\xc2\x80\xc2\x94" => '&mdash;',   // â€" variant
    "\xc3\xa2\xc2\x80\xc2\x98" => '&lsquo;',   // â€˜ → '
    "\xc3\xa2\xc2\x80\xc2\x99" => '&rsquo;',   // â€™ → '
    "\xc3\xa2\xc2\x80\xc2\x9c" => '&ldquo;',   // â€œ → "
    "\xc3\xa2\xc2\x80\xc2\x9d" => '&rdquo;',   // â€ → "
    // Also fix the literal garbled text versions
    'â€"'  => '&mdash;',
    'â€˜'  => '&lsquo;',
    'â€™'  => '&rsquo;',
    'â€œ'  => '&ldquo;',
    'â€'   => '&rdquo;',
];

$base_dir = dirname(__DIR__); // payroll/
$fixed    = [];
$errors   = [];

// Recursively find all PHP files
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base_dir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    if ($file->getFilename() === 'fix_encoding.php') continue;

    $path    = $file->getPathname();
    $content = file_get_contents($path);

    $new_content = str_replace(
        array_keys($replacements),
        array_values($replacements),
        $content
    );

    if ($new_content !== $content) {
        if (file_put_contents($path, $new_content) !== false) {
            $fixed[] = str_replace($base_dir, '', $path);
        } else {
            $errors[] = $path;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Fix Encoding</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f0f4f8; }
        .card { background: white; border-radius: 10px; padding: 24px; max-width: 700px; margin: 0 auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .ok  { color: #2E7D32; background: #E8F5E9; padding: 6px 12px; border-radius: 6px; margin: 4px 0; font-size: 0.85rem; }
        .err { color: #C62828; background: #FFEBEE; padding: 6px 12px; border-radius: 6px; margin: 4px 0; font-size: 0.85rem; }
        h2 { color: #1565C0; }
        .warn { background: #FFF3E0; color: #E65100; padding: 12px; border-radius: 8px; margin-top: 16px; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="card">
    <h2>BiT Payroll — Encoding Fix</h2>
    <p>Fixed <strong><?= count($fixed) ?></strong> files. <?= count($errors) ?> errors.</p>

    <?php foreach ($fixed as $f): ?>
    <div class="ok">✅ Fixed: <?= htmlspecialchars($f) ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $e): ?>
    <div class="err">❌ Error: <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if (empty($fixed) && empty($errors)): ?>
    <div class="ok">✅ No corrupted files found — all clean!</div>
    <?php endif; ?>

    <div class="warn">
        ⚠️ Delete this file after use: <code>payroll/database/fix_encoding.php</code>
    </div>
    <p style="margin-top:16px;">
        <a href="../pages/auth/login.php" style="color:#1565C0;">→ Go to Login</a>
    </p>
</div>
</body>
</html>
