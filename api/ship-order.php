<?php
require __DIR__ . '/config.php';
session_start();

if (empty($_SESSION['is_admin'])) {
    json_out(['error' => 'Forbidden'], 403);
}

require_post();

$ref      = trim($_POST['reference']          ?? '');
$tracking = trim($_POST['tracking_number']    ?? '');
$carrier  = trim($_POST['carrier']            ?? '');
$eta      = trim($_POST['estimated_delivery'] ?? '');

if (!$ref || !$tracking) {
    json_out(['error' => 'Reference and tracking number required'], 400);
}

try {
    $pdo = db();

    $pdo->prepare('
        UPDATE orders
        SET tracking_number = ?, carrier = ?, estimated_delivery = ?, shipped_at = NOW(), status = "paid"
        WHERE reference = ?
    ')->execute([$tracking, $carrier, $eta ?: null, $ref]);

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE reference = ?');
    $stmt->execute([$ref]);
    $order = $stmt->fetch();

    if (!$order) {
        json_out(['error' => 'Order not found: ' . $ref], 404);
    }

    // Send shipping notification email — wrapped so a mail failure never blocks the response
    try {
        require_once __DIR__ . '/mailer.php';
        email_shipping($order);
    } catch (Throwable $mailErr) {
        error_log('ship-order email failed: ' . $mailErr->getMessage());
    }

    json_out(['ok' => true, 'tracked' => $tracking]);

} catch (Throwable $e) {
    error_log('ship-order: ' . $e->getMessage());
    json_out(['error' => $e->getMessage()], 500);
}
