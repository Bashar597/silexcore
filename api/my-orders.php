<?php
require __DIR__ . '/config.php';
session_start();

if (empty($_SESSION['user_id'])) {
    json_out(['error' => 'Not authenticated'], 401);
}

// Admins don't have order history
if (!empty($_SESSION['is_admin'])) {
    json_out(['ok' => true, 'orders' => [], 'admin' => true]);
}

$stmt = db()->prepare('
    SELECT reference, created_at, items_json, subtotal, shipping, tax, total,
           status, shipping_service, tracking_number, carrier,
           estimated_delivery, shipped_at, address_line1, address_line2, city, state, zip
    FROM orders
    WHERE customer_email = ?
    ORDER BY created_at DESC
    LIMIT 50
');
$stmt->execute([$_SESSION['email']]);
json_out(['ok' => true, 'orders' => $stmt->fetchAll()]);
