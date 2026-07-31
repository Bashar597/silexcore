<?php
require __DIR__ . '/config.php';
session_start();

$uid = $_SESSION['user_id'] ?? null;

// ── GET — return this user's saved cart ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$uid) { json_out(['items' => []]); }
    $stmt = db()->prepare(
        'SELECT product_key, product_name, variant, price, qty, image FROM cart_items WHERE user_id = ?'
    );
    $stmt->execute([$uid]);
    json_out(['items' => $stmt->fetchAll()]);
}

// ── POST — sync or clear ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['error' => 'Method not allowed'], 405);
}
if (!$uid) { json_out(['ok' => false, 'error' => 'Not logged in'], 401); }

$action = $_POST['action'] ?? 'sync';
$pdo    = db();

if ($action === 'clear') {
    $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$uid]);
    json_out(['ok' => true]);
}

if ($action === 'sync') {
    $items = json_decode($_POST['items'] ?? '[]', true);
    if (!is_array($items)) { json_out(['error' => 'Invalid items'], 400); }

    $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$uid]);

    $ins = $pdo->prepare(
        'INSERT INTO cart_items (user_id, product_key, product_name, variant, price, qty, image)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($items as $item) {
        $ins->execute([
            $uid,
            $item['key']          ?? '',
            $item['name']         ?? '',
            $item['variantLabel'] ?? '',
            (float)($item['price'] ?? 0),
            max(1, (int)($item['qty'] ?? 1)),
            $item['image']        ?? '',
        ]);
    }
    json_out(['ok' => true]);
}

json_out(['error' => 'Unknown action'], 400);
