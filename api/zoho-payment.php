<?php
require __DIR__ . '/config.php';
require_post();

$amount    = (float)($_POST['amount'] ?? 0);
$ref       = trim($_POST['reference']     ?? '');
$email     = trim($_POST['email']         ?? '');
$name      = trim($_POST['customer_name'] ?? '');
$phone     = trim($_POST['phone']         ?? '');
$itemsJson = trim($_POST['items']         ?? '[]');

// Build description: "Silexcore Purchase\nProduct: Name x2, Name2 x1"
$items        = json_decode($itemsJson, true) ?: [];
$productParts = [];
foreach ($items as $i) {
    $n = trim($i['name'] ?? '');
    $q = (int)($i['qty'] ?? 1);
    if ($n) $productParts[] = $n . ' x' . $q;
}
$description = count($productParts)
    ? 'Silexcore Purchase' . "\nProduct: " . implode(', ', $productParts)
    : 'Silexcore Purchase';

if ($amount <= 0) json_out(['error' => 'Invalid order amount.'], 400);
if (!$ref)        json_out(['error' => 'Missing order reference.'], 400);
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
    json_out(['error' => 'Valid customer email is required.'], 400);

$hostedParams = [
    'description' => $description,
    'success_url' => 'https://silexcore.com/order-confirmed.html?ref=' . urlencode($ref),
    'failure_url' => 'https://silexcore.com/checkout.html?failed=1',
];
if ($email) $hostedParams['email'] = $email;
if ($phone) $hostedParams['phone'] = $phone;
if ($ref)   $hostedParams['udf1']  = $ref;
if ($name)  $hostedParams['udf2']  = $name;

$payload = [
    'amount'   => (float) number_format($amount, 2, '.', ''),
    'currency' => 'USD',
    'configurations' => [
        'allowed_payment_methods' => ['card'],
        'hosted_page_parameters'  => $hostedParams,
    ],
];

// Call Zoho Payments API — returns [raw_response, http_code]
function call_zoho_api(string $token, array $payload): array {
    $ch = curl_init('https://payments.zoho.com/api/v1/paymentsessions?account_id=' . ZOHO_ACCOUNT_ID);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Zoho-oauthtoken ' . $token,
            'Content-Type: application/json',
        ],
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$raw, $code];
}

function is_auth_error(string $raw, int $code): bool {
    if ($code === 401) return true;
    $lower = strtolower($raw);
    return str_contains($lower, 'not an authorized user')
        || str_contains($lower, 'not authorised')
        || str_contains($lower, 'unauthorized');
}

// First attempt with cached token
$token = zoho_access_token();
if (!$token) json_out(['error' => 'Could not authenticate with Zoho Payments.'], 500);

[$raw, $code] = call_zoho_api($token, $payload);
error_log('Zoho paymentsessions HTTP ' . $code . ': ' . $raw);

// If auth error — clear cache and retry once with a fresh token
if ($raw && is_auth_error($raw, $code)) {
    error_log('Zoho auth error — clearing token cache and retrying with fresh token');
    @unlink(__DIR__ . '/.zoho_token');
    $token = zoho_access_token();
    if ($token) {
        [$raw, $code] = call_zoho_api($token, $payload);
        error_log('Zoho retry HTTP ' . $code . ': ' . $raw);
    }
}

if (!$raw) json_out(['error' => 'No response from Zoho Payments.'], 500);

$data      = json_decode($raw, true);
$accessKey = $data['payments_session']['access_key']          ?? null;
$sessionId = $data['payments_session']['payments_session_id'] ?? '';

if ($accessKey) {
    json_out(['ok' => true, 'payment_url' => 'https://payments.zoho.com/hostedpages/' . $accessKey, 'session_id' => $sessionId]);
}

$errMsg = $data['message'] ?? $raw;
json_out(['error' => 'Payment error: ' . $errMsg], 500);
