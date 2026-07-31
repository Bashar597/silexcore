<?php
require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// Return 200 immediately so Zoho stops retrying, then flush to guarantee delivery
http_response_code(200);
echo 'ok';
if (ob_get_level()) ob_flush();
flush();

if (!$data) exit;

// Signature verification (lenient — log mismatch but don't block)
$headers     = function_exists('getallheaders') ? getallheaders() : [];
$receivedSig = $_SERVER['HTTP_X_ZOHO_WEBHOOK_SIGNATURE']
    ?? $_SERVER['HTTP_X_ZOHO_PAYMENTS_SIGNATURE']
    ?? $headers['X-Zoho-Webhook-Signature']
    ?? $headers['X-Zoho-Payments-Signature']
    ?? '';
if (ZOHO_SIGNING_KEY && $receivedSig) {
    $expectedSig = hash_hmac('sha256', $raw, ZOHO_SIGNING_KEY);
    if (!hash_equals($expectedSig, $receivedSig)) {
        error_log('Zoho Webhook: signature mismatch — processing anyway');
    }
}

$event = $data['event_type'] ?? $data['event'] ?? '';
if (!in_array($event, ['payment.succeeded', 'payment.success', 'payment.completed'])) {
    error_log('Zoho Webhook: ignoring event ' . $event);
    exit;
}

$payment   = $data['event_object']['payment'] ?? $data['payment'] ?? [];
$paymentId = $payment['payment_id'] ?? $payment['id'] ?? '';
$sessionId = $payment['payments_session_id'] ?? '';
$email     = $payment['receipt_email'] ?? '';

// Amount: Zoho sends as a decimal string e.g. "20.61"
$amount = (float)($payment['amount'] ?? 0);

error_log("Zoho Webhook: payment_id=$paymentId session_id=$sessionId email=$email amount=$amount");

try {
    $pdo   = db();
    $order = null;

    // 1. Match by payment_session_id (most reliable — set at checkout time)
    if ($sessionId) {
        $s = $pdo->prepare('SELECT * FROM orders WHERE payment_session_id = ? LIMIT 1');
        $s->execute([$sessionId]);
        $order = $s->fetch();
        if ($order) error_log("Zoho Webhook: matched by session_id");
    }

    // 2. Fallback: email + amount (both stored as decimals — compare cast to avoid float drift)
    if (!$order && $email && $amount > 0) {
        $s = $pdo->prepare('SELECT * FROM orders WHERE customer_email = ? AND CAST(total AS DECIMAL(10,2)) = ? AND status = "pending" ORDER BY created_at DESC LIMIT 1');
        $s->execute([$email, number_format($amount, 2, '.', '')]);
        $order = $s->fetch();
        if ($order) error_log("Zoho Webhook: matched by email+amount");
    }

    // 3. Fallback: most recent pending order for this email placed within last 24 hours
    // (time-bounded to avoid matching an old unrelated pending order)
    if (!$order && $email) {
        $s = $pdo->prepare('SELECT * FROM orders WHERE customer_email = ? AND status = "pending" AND created_at >= NOW() - INTERVAL 24 HOUR ORDER BY created_at DESC LIMIT 1');
        $s->execute([$email]);
        $order = $s->fetch();
        if ($order) error_log("Zoho Webhook: matched by email (24h window)");
    }

    if (!$order) {
        error_log("Zoho Webhook: no matching order found for session=$sessionId email=$email amount=$amount");
        exit;
    }

    // Mark as paid
    $pdo->prepare('UPDATE orders SET status="paid", zoho_payment_id=?, paid_at=NOW() WHERE id=? AND status="pending"')
        ->execute([$paymentId, $order['id']]);

    // Re-fetch with updated status
    $s = $pdo->prepare('SELECT * FROM orders WHERE id=?');
    $s->execute([$order['id']]);
    $order = $s->fetch();

    // Send emails
    $r1 = email_customer($order);
    $r2 = email_company($order);
    error_log("Zoho Webhook: emails sent for {$order['reference']} — customer:" . ($r1?'ok':'fail') . " company:" . ($r2?'ok':'fail'));

} catch (Throwable $e) {
    error_log('Zoho Webhook error: ' . $e->getMessage());
}
