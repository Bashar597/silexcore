<?php
require __DIR__ . '/config.php';
session_start();

if (empty($_SESSION['is_admin'])) {
    json_out(['error' => 'Forbidden'], 403);
}

require_post();
$ref = trim($_POST['reference'] ?? '');
if (!$ref) json_out(['error' => 'No reference'], 400);

$pdo  = db();
$stmt = $pdo->prepare('SELECT status FROM orders WHERE reference = ?');
$stmt->execute([$ref]);
$order = $stmt->fetch();

if (!$order) json_out(['error' => 'Order not found'], 404);
if ($order['status'] !== 'pending') json_out(['error' => 'Only pending orders can be deleted'], 400);

$pdo->prepare('DELETE FROM orders WHERE reference = ? AND status = ?')
    ->execute([$ref, 'pending']);

json_out(['ok' => true]);
