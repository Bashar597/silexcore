<?php
require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';
session_start();

if (empty($_SESSION['is_admin'])) {
    json_out(['error' => 'Forbidden'], 403);
}

require_post();
$ref    = trim($_POST['reference'] ?? '');
$action = trim($_POST['action']    ?? 'email');

if (!$ref) json_out(['error' => 'No reference'], 400);

try {
    $pdo = db();

    if ($action === 'markpaid') {
        $pdo->prepare("UPDATE orders SET status='paid', paid_at=NOW() WHERE reference=?")->execute([$ref]);
    }

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE reference = ?');
    $stmt->execute([$ref]);
    $order = $stmt->fetch();

    if (!$order) json_out(['error' => 'Order not found'], 404);

    if ($action === 'shipping') {
        if (empty($order['tracking_number'])) {
            json_out(['error' => 'No tracking number on this order yet.'], 400);
        }
        email_shipping($order);
        json_out(['ok' => true]);
    }

    $r1 = email_customer($order);
    $r2 = email_company($order);
    json_out(['ok' => true, 'customer_email' => $r1, 'company_email' => $r2]);

} catch (Throwable $e) {
    error_log('resend-email: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    json_out(['error' => $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'], 500);
}
