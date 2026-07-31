<?php
require __DIR__ . '/config.php';
require_post();

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['error' => 'Please enter a valid email address.'], 400);
}

$stmt = db()->prepare('SELECT id, first_name FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

// Always return ok — prevents email enumeration
if ($user) {
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    db()->prepare('UPDATE users SET reset_token=?, reset_expires=? WHERE id=?')
        ->execute([$token, $expires, $user['id']]);

    $link    = 'https://silexcore.com/reset-password.html?token=' . $token;
    $subject = 'Reset your SilexCore password';
    $body    = "Hi {$user['first_name']},\n\n";
    $body   .= "We received a request to reset your SilexCore account password.\n\n";
    $body   .= "Click the link below to choose a new password (expires in 1 hour):\n";
    $body   .= $link . "\n\n";
    $body   .= "If you didn't request this, you can safely ignore this email — your password won't change.\n\n";
    $body   .= "— SilexCore Technologies\n";
    $body   .= "Charlotte, NC  |  info@silexcore.com";

    $headers  = "From: SilexCore Technologies <noreply@silexcore.com>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    mail($email, $subject, $body, $headers);
}

json_out(['ok' => true]);
