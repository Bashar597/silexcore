<?php
require __DIR__ . '/config.php';
session_start();
require_post();

// reCAPTCHA verification
$recaptcha_secret = '6LeYRAEtAAAAADjPTAbJ2O51B__dN-jn-INw51fX';
$recaptcha_token  = $_POST['g-recaptcha-response'] ?? '';
if ($recaptcha_secret !== 'RECAPTCHA_SECRET_KEY_HERE') {
    $verify = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' .
        urlencode($recaptcha_secret) . '&response=' . urlencode($recaptcha_token));
    $result = json_decode($verify, true);
    if (empty($result['success'])) {
        json_out(['error' => 'Please complete the reCAPTCHA verification.'], 400);
    }
}

$first = trim($_POST['first_name'] ?? '');
$last  = trim($_POST['last_name']  ?? '');
$email = trim($_POST['email']      ?? '');
$pw    = $_POST['password']        ?? '';
$conf  = $_POST['confirm']         ?? '';

if (!$first || !$last || !$email || !$pw) {
    json_out(['error' => 'All fields are required.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['error' => 'Invalid email address.'], 400);
}
if ($pw !== $conf) {
    json_out(['error' => 'Passwords do not match.'], 400);
}
if (strlen($pw) < 8) {
    json_out(['error' => 'Password must be at least 8 characters.'], 400);
}

try {
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        json_out(['error' => 'An account with this email already exists.'], 409);
    }

    $hash = password_hash($pw, PASSWORD_BCRYPT);
    $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)')
        ->execute([$first, $last, $email, $hash]);

    $_SESSION['user_id']    = (int) $pdo->lastInsertId();
    $_SESSION['first_name'] = $first;
    $_SESSION['email']      = $email;

    // Notify company of new account
    $subject = 'New Account Created — ' . $first . ' ' . $last;
    $body = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;padding:20px;color:#222;">
        <h2 style="color:#d4a017;">New Account on SilexCore</h2>
        <p><strong>Name:</strong> ' . htmlspecialchars($first . ' ' . $last) . '</p>
        <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
        <p><strong>Registered:</strong> ' . date('F j, Y \a\t g:i A') . '</p>
        <hr style="border:none;border-top:1px solid #eee;margin:16px 0;">
        <p style="font-size:12px;color:#888;">Verify this is a real customer before processing any orders from this account.</p>
        </body></html>';
    $headers  = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    @mail(MAIL_COMPANY, $subject, $body, $headers, '-f' . MAIL_FROM);

    json_out(['ok' => true, 'first_name' => $first]);
} catch (PDOException) {
    json_out(['error' => 'Database error — please try again.'], 500);
}
