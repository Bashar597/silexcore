<?php
require __DIR__ . '/config.php';
require_post();

$ref       = trim($_POST['reference']          ?? '');
$name      = trim($_POST['customer_name']      ?? '');
$email     = trim($_POST['email']              ?? '');
$phone     = trim($_POST['phone']              ?? '');
$address   = trim($_POST['address']            ?? '');
$address2  = trim($_POST['address2']           ?? '');
$city      = trim($_POST['city']              ?? '');
$state     = trim($_POST['state']             ?? '');
$zip       = trim($_POST['zip']               ?? '');
$country   = trim($_POST['country']           ?? 'United States');
$items     = trim($_POST['items']             ?? '[]');
$subtotal  = (float)($_POST['subtotal']       ?? 0);
$shipping  = (float)($_POST['shipping']       ?? 0);
$tax       = (float)($_POST['tax']            ?? 0);
$total     = (float)($_POST['total']          ?? 0);
$service   = trim($_POST['shipping_service']  ?? '');
$notes     = trim($_POST['notes']             ?? '');
$sessionId = trim($_POST['payment_session_id'] ?? '');

// All required fields must be present
if (!$ref) {
    json_out(['error' => 'Missing order reference.'], 400);
}
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['error' => 'Valid customer email is required.'], 400);
}
if ($total <= 0) {
    json_out(['error' => 'Order total must be greater than zero.'], 400);
}
if (!$name) {
    json_out(['error' => 'Customer name is required.'], 400);
}
if (!$address || !$city || !$state || !$zip) {
    json_out(['error' => 'Complete delivery address is required.'], 400);
}

try {
    $pdo = db();

    $pdo->prepare('
        INSERT INTO orders
          (reference, customer_name, customer_email, customer_phone,
           address_line1, address_line2, city, state, zip, country,
           items_json, subtotal, shipping, tax, total, shipping_service, notes, payment_session_id)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
          payment_session_id = VALUES(payment_session_id),
          customer_name      = VALUES(customer_name),
          customer_email     = VALUES(customer_email),
          address_line1      = VALUES(address_line1),
          address_line2      = VALUES(address_line2),
          city               = VALUES(city),
          state              = VALUES(state),
          zip                = VALUES(zip),
          total              = VALUES(total),
          shipping_service   = VALUES(shipping_service)
    ')->execute([
        $ref, $name, $email, $phone,
        $address, $address2, $city, $state, $zip, $country,
        $items, $subtotal, $shipping, $tax, $total, $service, $notes, $sessionId
    ]);

    // Send company notification email so team knows an order has been initiated
    $subject  = "New Order Initiated — {$ref}";
    $itemList = '';
    $decoded  = json_decode($items, true) ?: [];
    foreach ($decoded as $i) {
        $itemList .= htmlspecialchars($i['name'] . ' — ' . ($i['variantLabel']??'') . ' ×' . ($i['qty']??1)) . "<br>";
    }
    $body = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;padding:20px;color:#222;'>
        <h2 style='color:#d4a017;'>New Order — {$ref}</h2>
        <p><strong>Status:</strong> Awaiting Payment</p>
        <p><strong>Customer:</strong> " . htmlspecialchars($name) . " &lt;" . htmlspecialchars($email) . "&gt;</p>
        <p><strong>Phone:</strong> " . htmlspecialchars($phone ?: '—') . "</p>
        <p><strong>Ship to:</strong><br>" . htmlspecialchars(($address2 ? "{$address}, {$address2}" : $address) . ", {$city}, {$state} {$zip}") . "</p>
        <p><strong>Items:</strong><br>{$itemList}</p>
        <p><strong>Subtotal:</strong> \${$subtotal} &nbsp;|&nbsp; <strong>Shipping:</strong> \${$shipping} (" . htmlspecialchars($service) . ") &nbsp;|&nbsp; <strong>Tax:</strong> \${$tax}</p>
        <p><strong>Total:</strong> \${$total}</p>
        <hr>
        <p style='font-size:12px;color:#888;'>This order is pending payment confirmation. A second email will arrive once Zoho confirms payment.</p>
        </body></html>";

    $headers  = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    @mail(MAIL_COMPANY, $subject, $body, $headers, '-f' . MAIL_FROM);

    json_out(['ok' => true]);

} catch (Exception $e) {
    error_log('save-order: ' . $e->getMessage());
    json_out(['error' => 'Database error — could not save order. Please try again.'], 500);
}
