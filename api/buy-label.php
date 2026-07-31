<?php
require __DIR__ . '/config.php';
session_start();

if (empty($_SESSION['is_admin'])) {
    json_out(['error' => 'Forbidden'], 403);
}

$action = trim($_POST['action'] ?? 'rates');
$ref    = trim($_POST['reference'] ?? '');

if (!$ref) json_out(['error' => 'No reference'], 400);

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM orders WHERE reference = ?');
$stmt->execute([$ref]);
$order = $stmt->fetch();
if (!$order) json_out(['error' => 'Order not found'], 404);

// Package dimensions — use values from request if provided, else auto-calculate
$length = (float)($_POST['length'] ?? 0);
$width  = (float)($_POST['width']  ?? 0);
$height = (float)($_POST['height'] ?? 0);
$weight = (float)($_POST['weight'] ?? 0);

// Auto-calculate weight from order items if not provided
if ($weight <= 0) {
    $items = json_decode($order['items_json'], true) ?: [];
    foreach ($items as $i) {
        $variant = strtolower($i['variantLabel'] ?? '');
        $w = str_contains($variant, '4 oz') ? 5.5 : (str_contains($variant, '2 oz') ? 3.5 : 2.3);
        $weight += ($i['qty'] ?? 1) * $w;
    }
}

$shipment = [
    'shipment' => [
        'from_address' => [
            'company' => 'SilexCore Technologies',
            'street1' => '12226 Red Rust Ln',
            'city'    => 'Charlotte',
            'state'   => 'NC',
            'zip'     => '28277',
            'country' => 'US',
            'phone'   => '7868165748',
            'email'   => 'info@silexcore.com',
        ],
        'to_address' => [
            'name'    => $order['customer_name'],
            'street1' => $order['address_line1'],
            'street2' => $order['address_line2'] ?? '',
            'city'    => $order['city'],
            'state'   => $order['state'],
            'zip'     => $order['zip'],
            'country' => 'US',
            'email'   => $order['customer_email'],
            'phone'   => $order['customer_phone'] ?: '0000000000',
        ],
        'parcel' => [
            'length' => $length > 0 ? $length : 6,
            'width'  => $width  > 0 ? $width  : 4,
            'height' => $height > 0 ? $height : 4,
            'weight' => max(1, $weight),
        ],
    ]
];

// Step 1: Get rates
$ch = curl_init('https://api.easypost.com/v2/shipments');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($shipment),
    CURLOPT_USERPWD        => EASYPOST_API_KEY . ':',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($raw, true);
if ($code >= 400 || !$data) {
    json_out(['error' => 'EasyPost error: ' . $raw], 500);
}

$shipmentId = $data['id'] ?? '';

if ($action === 'rates') {
    $rates = array_map(function($r) {
        return [
            'id'       => $r['id'],
            'service'  => $r['carrier'] . ' ' . $r['service'],
            'rate'     => $r['rate'],
            'days'     => $r['delivery_days'] ?? '?',
        ];
    }, $data['rates'] ?? []);
    usort($rates, fn($a,$b) => (float)$a['rate'] <=> (float)$b['rate']);
    json_out(['ok' => true, 'shipment_id' => $shipmentId, 'rates' => $rates]);
}

if ($action === 'buy') {
    $shipmentId   = trim($_POST['shipment_id']   ?? '');
    $rateId       = trim($_POST['rate_id']       ?? '');
    $insureAmount = (float)($_POST['insure_amount'] ?? 0);
    if (!$shipmentId || !$rateId) json_out(['error' => 'Missing shipment_id or rate_id'], 400);

    $buyPayload = ['rate' => ['id' => $rateId]];
    if ($insureAmount > 0) $buyPayload['insurance'] = number_format($insureAmount, 2, '.', '');

    $ch = curl_init("https://api.easypost.com/v2/shipments/{$shipmentId}/buy");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($buyPayload),
        CURLOPT_USERPWD        => EASYPOST_API_KEY . ':',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $raw     = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        json_out(['error' => 'Could not reach EasyPost (no charge made): ' . $curlErr], 500);
    }

    $bought = json_decode($raw, true);
    if ($code >= 400 || empty($bought['tracking_code'])) {
        json_out(['error' => 'Purchase failed: ' . $raw], 500);
    }

    // ── Label is PURCHASED and CHARGED beyond this point ──
    $tracking  = $bought['tracking_code'];
    $carrier   = $bought['selected_rate']['carrier'] ?? '';
    $labelUrl  = $bought['postage_label']['label_url'] ?? '';
    $service   = $carrier . ' ' . ($bought['selected_rate']['service'] ?? '');

    // Persist tracking defensively — a DB error must never hide the
    // successful purchase or prompt the admin to buy (and pay) again.
    $saved = false;
    try {
        $pdo->prepare("UPDATE orders SET tracking_number=?, carrier=?, label_url=?, shipped_at=NOW(), status='paid' WHERE reference=?")
            ->execute([$tracking, $carrier, $labelUrl, $ref]);
        $saved = true;
    } catch (Throwable $e) {
        error_log('buy-label: full UPDATE failed (' . $e->getMessage() . ') — retrying without label_url column');
        // Fallback for servers where the label_url column has not been added yet
        try {
            $pdo->prepare("UPDATE orders SET tracking_number=?, carrier=?, shipped_at=NOW(), status='paid' WHERE reference=?")
                ->execute([$tracking, $carrier, $ref]);
            $saved = true;
        } catch (Throwable $e2) {
            error_log('buy-label: fallback UPDATE also failed: ' . $e2->getMessage());
        }
    }

    // Send shipping email — wrapped so a failure never blocks the tracking response
    try {
        require_once __DIR__ . '/mailer.php';
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE reference = ?');
        $stmt->execute([$ref]);
        $updatedOrder = $stmt->fetch() ?: $order;
        email_shipping($updatedOrder, $labelUrl);
    } catch (Throwable $e) {
        error_log('buy-label email failed: ' . $e->getMessage());
    }

    json_out([
        'ok'        => true,
        'tracking'  => $tracking,
        'label_url' => $labelUrl,
        'service'   => $service,
        'insured'   => $insureAmount > 0,
        'saved'     => $saved,  // false → label bought but tracking not stored; record it manually
    ]);
}
