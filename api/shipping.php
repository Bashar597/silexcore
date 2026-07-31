<?php
// EASYPOST_API_KEY (and other secrets) live in config.php, which is
// kept out of source control. Same key/pattern used by buy-label.php.
require __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$dest_zip = trim($_POST['zip']       ?? '');
$weight   = (int)($_POST['weight_oz'] ?? 4);  // total weight in ounces

if (!preg_match('/^\d{5}/', $dest_zip)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid 5-digit ZIP code.']);
    exit;
}

// ── Create shipment via EasyPost ───────────────────────────────
$payload = [
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
            'name'    => 'Customer',
            'street1' => '1 Main St',
            'city'    => 'Anywhere',
            'state'   => 'XX',
            'zip'     => substr($dest_zip, 0, 5),
            'country' => 'US',
        ],
        'parcel' => [
            'length' => 9,
            'width'  => 6,
            'height' => 2,
            'weight' => max(1, $weight),  // oz
        ],
    ]
];

$ch = curl_init('https://api.easypost.com/v2/shipments');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_USERPWD        => EASYPOST_API_KEY . ':',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$raw || $code >= 400) {
    echo json_encode(['ok' => false, 'error' => 'Could not fetch shipping rates. Please try again.']);
    exit;
}

$data = json_decode($raw, true);
$rates = [];

foreach ($data['rates'] ?? [] as $r) {
    $carrier = $r['carrier']       ?? '';
    $service = $r['service']       ?? '';
    $rate    = (float)($r['rate']  ?? 0);
    $days    = $r['delivery_days'] ?? null;
    if ($rate <= 0) continue;
    $label = $carrier . ' ' . $service;
    if ($days) $label .= ' (' . $days . ' day' . ($days > 1 ? 's' : '') . ')';
    $rates[] = ['service' => $label, 'rate' => number_format($rate, 2), 'carrier' => $carrier, 'service_code' => $service];
}

// Sort cheapest first
usort($rates, fn($a, $b) => (float)$a['rate'] <=> (float)$b['rate']);

echo json_encode(['ok' => true, 'rates' => array_values($rates)]);
