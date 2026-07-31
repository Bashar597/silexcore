<?php
require __DIR__ . '/config.php';
session_start();

if (empty($_SESSION['user_id'])) {
    json_out(['error' => 'Not authenticated'], 401);
}

$uid = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = db()->prepare('SELECT first_name, last_name, email, phone, address_line1, address_line2, city, state, zip, country FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    json_out($stmt->fetch() ?: []);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'profile') {
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name']  ?? '');
        $phone = trim($_POST['phone']      ?? '');
        if (!$first || !$last) {
            json_out(['error' => 'First and last name are required.'], 400);
        }
        db()->prepare('UPDATE users SET first_name=?, last_name=?, phone=? WHERE id=?')
             ->execute([$first, $last, $phone, $uid]);
        $_SESSION['first_name'] = $first;
        json_out(['ok' => true, 'first_name' => $first]);
    }

    if ($action === 'address') {
        db()->prepare('UPDATE users SET address_line1=?, address_line2=?, city=?, state=?, zip=?, country=? WHERE id=?')
             ->execute([
                 trim($_POST['address_line1'] ?? ''),
                 trim($_POST['address_line2'] ?? ''),
                 trim($_POST['city']          ?? ''),
                 trim($_POST['state']         ?? ''),
                 trim($_POST['zip']           ?? ''),
                 trim($_POST['country']       ?? 'United States'),
                 $uid
             ]);
        json_out(['ok' => true]);
    }

    if ($action === 'password') {
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id=?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();

        if (!password_verify($_POST['current_password'] ?? '', $row['password_hash'])) {
            json_out(['error' => 'Current password is incorrect.'], 400);
        }
        $new  = $_POST['new_password']     ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if (strlen($new) < 8) {
            json_out(['error' => 'New password must be at least 8 characters.'], 400);
        }
        if ($new !== $conf) {
            json_out(['error' => 'Passwords do not match.'], 400);
        }
        db()->prepare('UPDATE users SET password_hash=? WHERE id=?')
             ->execute([password_hash($new, PASSWORD_BCRYPT), $uid]);
        json_out(['ok' => true]);
    }

    json_out(['error' => 'Unknown action.'], 400);
}
