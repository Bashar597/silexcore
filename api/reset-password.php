<?php
require __DIR__ . '/config.php';
require_post();

$token = trim($_POST['token']   ?? '');
$new   = $_POST['password']     ?? '';
$conf  = $_POST['confirm']      ?? '';

if (!$token) {
    json_out(['error' => 'Invalid or missing reset token.'], 400);
}
if (strlen($new) < 8) {
    json_out(['error' => 'Password must be at least 8 characters.'], 400);
}
if ($new !== $conf) {
    json_out(['error' => 'Passwords do not match.'], 400);
}

$stmt = db()->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()');
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    json_out(['error' => 'This reset link has expired or is invalid. Please request a new one.'], 400);
}

db()->prepare('UPDATE users SET password_hash=?, reset_token=NULL, reset_expires=NULL WHERE id=?')
    ->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);

json_out(['ok' => true]);
