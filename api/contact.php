<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Honeypot — bots fill this, humans leave it blank
if (!empty($_POST['website'])) {
    echo json_encode(['ok' => true]);
    exit;
}

// reCAPTCHA server-side verification
// Swap the secret key below with your real one from g.co/recaptcha
$recaptcha_secret   = '6LcR_v0sAAAAAHv78ctyWlYj5S-jF4E3bVkqjgsz';
$recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

if (empty($recaptcha_response)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please complete the CAPTCHA.']);
    exit;
}

$verify = file_get_contents(
    'https://www.google.com/recaptcha/api/siteverify?secret='
    . urlencode($recaptcha_secret)
    . '&response=' . urlencode($recaptcha_response)
);
$captcha = json_decode($verify, true);

if (empty($captcha['success'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'CAPTCHA verification failed. Please try again.']);
    exit;
}

$name    = trim(strip_tags($_POST['name']    ?? ''));
$email   = trim(strip_tags($_POST['email']   ?? ''));
$subject = trim(strip_tags($_POST['subject'] ?? ''));
$message = trim(strip_tags($_POST['message'] ?? ''));

if (!$name || !$email || !$message) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please fill in all required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

$to           = 'info@silexcore.com';
$subject_line = 'SilexCore Contact: ' . ($subject ?: 'General Inquiry');

$body  = "New message from silexcore.com\n";
$body .= "==============================\n\n";
$body .= "Name:    {$name}\n";
$body .= "Email:   {$email}\n";
$body .= "Subject: " . ($subject ?: 'General Inquiry') . "\n\n";
$body .= "Message:\n{$message}\n\n";
$body .= "==============================\n";
$body .= "Sent via silexcore.com/contact\n";

$headers  = "From: SilexCore Website <noreply@silexcore.com>\r\n";
$headers .= "Reply-To: {$name} <{$email}>\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

if (mail($to, $subject_line, $body, $headers)) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not send. Please email us directly at info@silexcore.com.']);
}
