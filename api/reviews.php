<?php
require __DIR__ . '/config.php';
session_start();

function handle_review_upload(): string {
    if (empty($_FILES['photo']['tmp_name'])) return '';
    $file = $_FILES['photo'];
    $allowed = ['image/jpeg','image/jpg','image/png','image/webp','image/gif'];
    if (!in_array($file['type'], $allowed)) return '';
    if ($file['size'] > 5 * 1024 * 1024) return ''; // 5MB max
    $dir = __DIR__ . '/../assets/img/reviews/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = 'review_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return 'assets/img/reviews/' . $name;
    }
    return '';
}

$method    = $_SERVER['REQUEST_METHOD'];
$productId = trim($_GET['product_id'] ?? $_POST['product_id'] ?? '');
$isAdmin   = !empty($_SESSION['is_admin']);
$userId    = (int)($_SESSION['user_id'] ?? 0);

// ── GET: fetch approved reviews ─────────────────────────────
if ($method === 'GET') {
    if (!$productId) json_out(['error' => 'product_id required'], 400);
    $pdo = db();

    if ($isAdmin) {
        // Admin gets full info: real name, email, all reviews
        $stmt = $pdo->prepare('SELECT r.id, r.user_name, r.rating, r.title, r.body, r.created_at, r.status, u.first_name, u.last_name, u.email as user_email FROM reviews r LEFT JOIN users u ON u.id=r.user_id WHERE r.product_id = ? ORDER BY r.created_at DESC');
    } else {
        $stmt = $pdo->prepare('SELECT id, user_name, rating, title, body, created_at FROM reviews WHERE product_id = ? AND status = "approved" ORDER BY created_at DESC');
    }
    $stmt->execute([$productId]);
    $reviews = $stmt->fetchAll();

    // Non-admin: show Anonymous
    if (!$isAdmin) {
        foreach ($reviews as &$r) {
            $r['user_name'] = 'Anonymous';
        }
        unset($r);
    }

    // If logged in non-admin: check if can review
    $canReview = false; $alreadyDone = false;
    if ($userId && !$isAdmin) {
        $s = $pdo->prepare('SELECT id FROM reviews WHERE product_id = ? AND user_id = ?');
        $s->execute([$productId, $userId]);
        $alreadyDone = (bool)$s->fetch();
        if (!$alreadyDone) {
            $s2 = $pdo->prepare('SELECT items_json FROM orders WHERE customer_email = ? AND status = "paid"');
            $s2->execute([$_SESSION['email']]);
            foreach ($s2->fetchAll() as $order) {
                $items = json_decode($order['items_json'], true) ?: [];
                foreach ($items as $item) {
                    if (($item['productId'] ?? '') === $productId) { $canReview = true; break 2; }
                }
            }
        }
    }

    $avg = count($reviews) ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
    json_out([
        'ok'           => true,
        'reviews'      => $reviews,
        'avg'          => round($avg, 1),
        'count'        => count($reviews),
        'can_review'   => $canReview,
        'already_done' => $alreadyDone,
        'logged_in'    => $userId > 0,
        'is_admin'     => $isAdmin,
    ]);
}

// ── POST: submit review ─────────────────────────────────────
if ($method === 'POST') {
    $action = trim($_POST['action'] ?? 'submit');

    // Admin: delete review
    if ($action === 'delete' && $isAdmin) {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
        json_out(['ok' => true]);
    }

    // Admin: add review on behalf of any product
    if ($action === 'admin_add' && $isAdmin) {
        $rating = max(0, min(5, (int)($_POST['rating'] ?? 5)));
        $title  = trim($_POST['title'] ?? '');
        $body   = trim($_POST['body']  ?? '');
        $name   = trim($_POST['name']  ?? 'Verified Customer');
        if (!$productId || !$body) json_out(['error' => 'Missing fields'], 400);
        $imgPath = handle_review_upload();
        db()->prepare('INSERT INTO reviews (product_id, user_id, user_name, rating, title, body, image_path, status) VALUES (?,?,?,?,?,?,?,"approved")')
            ->execute([$productId, $userId, $name, $rating, $title, $body, $imgPath]);
        json_out(['ok' => true]);
    }

    // Customer: submit review
    if ($action === 'submit') {
        if (!$userId) json_out(['error' => 'Please sign in to leave a review.'], 401);

        $pdo    = db();
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $title  = trim($_POST['title'] ?? '');
        $body   = trim($_POST['body']  ?? '');

        if (!$productId || !$body) json_out(['error' => 'Please write your review.'], 400);

        // Check already reviewed
        $s = $pdo->prepare('SELECT id FROM reviews WHERE product_id = ? AND user_id = ?');
        $s->execute([$productId, $userId]);
        if ($s->fetch()) json_out(['error' => 'You have already reviewed this product.'], 409);

        // Verify purchase
        $bought = false;
        $s2 = $pdo->prepare('SELECT items_json FROM orders WHERE customer_email = ? AND status = "paid"');
        $s2->execute([$_SESSION['email']]);
        foreach ($s2->fetchAll() as $order) {
            $items = json_decode($order['items_json'], true) ?: [];
            foreach ($items as $item) {
                if (($item['productId'] ?? '') === $productId) { $bought = true; break 2; }
            }
        }
        if (!$bought) json_out(['error' => 'You can only review products you have purchased.'], 403);

        $name = trim($_SESSION['first_name'] . ' ' . ($_SESSION['last_name'] ?? ''));
        $parts = explode(' ', $name);
        $displayName = $parts[0][0] . str_repeat('*', max(1, strlen($parts[0]) - 1)) . (isset($parts[1]) ? ' ' . $parts[1][0] . '.' : '');

        $rating  = max(0, min(5, (int)($_POST['rating'] ?? 5)));
        $imgPath = handle_review_upload();

        $pdo->prepare('INSERT INTO reviews (product_id, user_id, user_name, rating, title, body, image_path) VALUES (?,?,?,?,?,?,?)')
            ->execute([$productId, $userId, $displayName, $rating, $title, $body, $imgPath]);
        json_out(['ok' => true, 'message' => 'Thank you for your review!']);
    }
}
