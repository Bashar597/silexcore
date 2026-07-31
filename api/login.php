<?php
require __DIR__ . '/config.php';
session_start();
require_post();

$email = trim($_POST['email']    ?? '');
$pw    = $_POST['password'] ?? '';

if (!$email || !$pw) {
    json_out(['error' => 'Email and password are required.'], 400);
}

try {
    $pdo  = db();
    $stmt = $pdo->prepare('SELECT id, first_name, last_name, password_hash, is_admin FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pw, $user['password_hash'])) {
        json_out(['error' => 'Incorrect email or password.'], 401);
    }

    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
        ->execute([$user['id']]);

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name']  = $user['last_name'];
    $_SESSION['email']      = $email;
    $_SESSION['is_admin']   = !empty($user['is_admin']);

    json_out(['ok' => true, 'first_name' => $user['first_name'], 'is_admin' => !empty($user['is_admin'])]);
} catch (PDOException) {
    json_out(['error' => 'Database error — please try again.'], 500);
}
