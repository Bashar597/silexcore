<?php
require __DIR__ . '/api/config.php';
session_start();

// Auth check
if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: account.html');
    exit;
}

$pdo     = db();
$section = $_GET['s'] ?? 'accounts';
$search  = trim($_GET['q'] ?? '');

// ── Data queries ─────────────────────────────────────────────

function revenue_stats($pdo): array {
    $r = [];
    $periods = [
        'Today'      => "DATE(created_at) = CURDATE()",
        'This Week'  => "YEARWEEK(created_at) = YEARWEEK(NOW())",
        'This Month' => "MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())",
        'This Year'  => "YEAR(created_at) = YEAR(NOW())",
        'All Time'   => "1=1",
    ];
    foreach ($periods as $label => $where) {
        $s = $pdo->query("SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as rev FROM orders WHERE status='paid' AND $where")->fetch();
        $r[] = ['label' => $label, 'orders' => (int)$s['cnt'], 'revenue' => (float)$s['rev']];
    }
    return $r;
}

function orders_list($pdo, string $status = ''): array {
    $where = $status ? "WHERE status = " . $pdo->quote($status) : '';
    return $pdo->query("SELECT * FROM orders $where ORDER BY created_at DESC LIMIT 200")->fetchAll();
}

function accounts_list($pdo, string $search = ''): array {
    if ($search) {
        $s = $pdo->prepare("SELECT id, first_name, last_name, email, phone, created_at, is_admin FROM users WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? ORDER BY first_name, last_name");
        $q = "%$search%";
        $s->execute([$q, $q, $q]);
    } else {
        $s = $pdo->query("SELECT id, first_name, last_name, email, phone, created_at, is_admin FROM users ORDER BY first_name, last_name");
    }
    return $s->fetchAll();
}

function top_products($pdo): array {
    $orders = $pdo->query("SELECT items_json FROM orders WHERE status='paid'")->fetchAll();
    $counts = [];
    foreach ($orders as $o) {
        $items = json_decode($o['items_json'], true) ?: [];
        foreach ($items as $item) {
            $key = ($item['name'] ?? '') . ' — ' . ($item['variantLabel'] ?? '');
            $counts[$key] = ($counts[$key] ?? 0) + (int)($item['qty'] ?? 1);
        }
    }
    arsort($counts);
    return array_slice($counts, 0, 20, true);
}

function recent_buyers($pdo): array {
    return $pdo->query("
        SELECT o.reference, o.customer_name, o.customer_email,
               o.total, o.status, o.items_json, o.shipping_service,
               o.address_line1, o.city, o.state, o.zip, o.paid_at, o.created_at
        FROM orders o
        ORDER BY o.created_at DESC
        LIMIT 50
    ")->fetchAll();
}

// Load data for current section
function reviews_list($pdo): array {
    return $pdo->query("SELECT r.*, u.email FROM reviews r LEFT JOIN users u ON u.id=r.user_id ORDER BY r.created_at DESC LIMIT 200")->fetchAll();
}

$data = match($section) {
    'revenue'  => revenue_stats($pdo),
    'orders'   => orders_list($pdo, $_GET['status'] ?? ''),
    'products' => top_products($pdo),
    'buyers'   => recent_buyers($pdo),
    'reviews'  => reviews_list($pdo),
    default    => accounts_list($pdo, $search),
};

// Quick totals for sidebar badges
$totals = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM users WHERE is_admin=0) AS customers,
        (SELECT COUNT(*) FROM orders WHERE status='paid') AS paid_orders,
        (SELECT COUNT(*) FROM orders WHERE status='pending') AS pending_orders,
        (SELECT COALESCE(SUM(total),0) FROM orders WHERE status='paid') AS total_revenue
")->fetch();

function fmt($n): string { return '$' . number_format((float)$n, 2); }
function esc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Admin Dashboard — SilexCore</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --gold: #d4a017; --gold-light: #f5ecd0;
      --bg: #f5f3ee; --card: #ffffff;
      --text: #1a1a1a; --muted: #888; --border: #e8e4db;
      --sidebar: #1c1a17;
    }
    body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

    /* Sidebar */
    .sidebar {
      width: 240px; background: var(--sidebar); color: #fff;
      display: flex; flex-direction: column; flex-shrink: 0;
      position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto;
    }
    .sidebar-brand {
      padding: 28px 24px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .sidebar-brand h1 { font-size: 15px; font-weight: 600; color: var(--gold); letter-spacing: 0.02em; }
    .sidebar-brand p  { font-size: 11px; color: rgba(255,255,255,0.4); margin-top: 3px; }
    .sidebar-stats {
      padding: 20px 24px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
      display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
    }
    .stat-chip { background: rgba(255,255,255,0.06); border-radius: 4px; padding: 10px 12px; }
    .stat-chip .val { font-size: 16px; font-weight: 600; color: var(--gold); }
    .stat-chip .lbl { font-size: 10px; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.08em; margin-top: 2px; }
    .sidebar nav { padding: 16px 12px; flex: 1; }
    .nav-link {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px; border-radius: 4px; font-size: 13px;
      color: rgba(255,255,255,0.6); text-decoration: none;
      transition: background 0.15s, color 0.15s; margin-bottom: 2px;
    }
    .nav-link:hover { background: rgba(255,255,255,0.06); color: #fff; }
    .nav-link.active { background: var(--gold); color: #fff; }
    .nav-link svg { width: 16px; height: 16px; flex-shrink: 0; }
    .sidebar-footer { padding: 16px 24px; border-top: 1px solid rgba(255,255,255,0.08); }
    .sidebar-footer a { font-size: 12px; color: rgba(255,255,255,0.4); text-decoration: none; }
    .sidebar-footer a:hover { color: rgba(255,255,255,0.7); }

    /* Main */
    .main { margin-left: 240px; flex: 1; min-height: 100vh; }
    .topbar {
      background: var(--card); border-bottom: 1px solid var(--border);
      padding: 18px 32px; display: flex; align-items: center;
      justify-content: space-between; position: sticky; top: 0; z-index: 10;
    }
    .topbar h2 { font-size: 17px; font-weight: 600; }
    .topbar-right { display: flex; gap: 12px; align-items: center; }
    .content { padding: 32px; }

    /* Cards */
    .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px,1fr)); gap: 16px; margin-bottom: 32px; }
    .card {
      background: var(--card); border-radius: 4px;
      border: 1px solid var(--border); padding: 24px;
    }
    .card-val { font-size: 26px; font-weight: 700; color: var(--text); }
    .card-lbl { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; margin-top: 4px; }
    .card-gold { border-top: 3px solid var(--gold); }

    /* Table */
    .table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 4px; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    thead th { background: var(--bg); padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); border-bottom: 1px solid var(--border); }
    tbody td { padding: 12px 16px; border-bottom: 1px solid var(--border); vertical-align: top; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover { background: #faf9f6; }

    /* Badges */
    .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    .badge-paid { background: #e6f7ec; color: #1a7a3a; }
    .badge-pending { background: #fff8e6; color: #a67800; }
    .badge-failed { background: #fdecea; color: #b71c1c; }
    .badge-admin { background: var(--gold-light); color: var(--gold); }

    /* Search */
    .search-bar { display: flex; gap: 10px; margin-bottom: 20px; }
    .search-bar input {
      flex: 1; padding: 10px 14px; border: 1px solid var(--border);
      border-radius: 3px; font-size: 14px; background: var(--card);
      font-family: inherit; max-width: 380px;
    }
    .search-bar input:focus { outline: none; border-color: var(--gold); }
    .btn-sm {
      padding: 10px 18px; background: var(--gold); color: #fff;
      border: none; border-radius: 3px; font-size: 13px; cursor: pointer;
      font-family: inherit;
    }

    /* Revenue table */
    .rev-table { width: 100%; border-collapse: collapse; }
    .rev-table td { padding: 16px 20px; border-bottom: 1px solid var(--border); }
    .rev-table td:last-child { text-align: right; font-weight: 600; }
    .rev-table tr:last-child td { border-bottom: none; }

    /* Product bars */
    .prod-bar-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 4px; padding: 8px 0; }
    .prod-row { display: flex; align-items: center; gap: 16px; padding: 10px 20px; }
    .prod-row:hover { background: #faf9f6; }
    .prod-name { flex: 1; font-size: 13px; }
    .prod-count { font-weight: 700; font-size: 14px; min-width: 32px; text-align: right; color: var(--gold); }
    .prod-bar-bg { width: 120px; height: 6px; background: var(--border); border-radius: 3px; flex-shrink: 0; }
    .prod-bar-fill { height: 6px; background: var(--gold); border-radius: 3px; }

    /* Order items list */
    .items-list { font-size: 12px; color: var(--muted); }
    .items-list div { margin-bottom: 2px; }

    @media (max-width: 900px) {
      .sidebar { display: none; }
      .main { margin-left: 0; }
    }
  </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <h1>SilexCore</h1>
    <p>Admin Dashboard</p>
  </div>
  <div class="sidebar-stats">
    <div class="stat-chip"><div class="val"><?= $totals['customers'] ?></div><div class="lbl">Customers</div></div>
    <div class="stat-chip"><div class="val"><?= $totals['paid_orders'] ?></div><div class="lbl">Orders</div></div>
    <div class="stat-chip"><div class="val"><?= $totals['pending_orders'] ?></div><div class="lbl">Pending</div></div>
    <div class="stat-chip"><div class="val"><?= fmt($totals['total_revenue']) ?></div><div class="lbl">Revenue</div></div>
  </div>
  <nav>
    <a href="?s=accounts" class="nav-link <?= $section==='accounts'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Accounts
    </a>
    <a href="?s=revenue" class="nav-link <?= $section==='revenue'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
      Revenue
    </a>
    <a href="?s=orders" class="nav-link <?= $section==='orders'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Orders
    </a>
    <a href="?s=products" class="nav-link <?= $section==='products'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
      Top Products
    </a>
    <a href="?s=reviews" class="nav-link <?= $section==='reviews'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      Reviews
    </a>
    <a href="?s=buyers" class="nav-link <?= $section==='buyers'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>
      Recent Buyers
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="index.html">← Back to Site</a>
  </div>
</aside>

<!-- Main -->
<main class="main">
  <div class="topbar">
    <h2><?= match($section) { 'revenue'=>'Revenue', 'orders'=>'Orders', 'products'=>'Top Products', 'buyers'=>'Recent Buyers', default=>'Customer Accounts' } ?></h2>
    <div class="topbar-right">
      <span style="font-size:13px;color:var(--muted);">Logged in as <?= esc($_SESSION['email']) ?></span>
    </div>
  </div>

  <div class="content">

  <?php if ($section === 'reviews'): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Product</th><th>Customer</th><th>Rating</th><th>Review</th><th>Date</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($data as $r):
          $stars = str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5-(int)$r['rating']);
        ?>
          <tr>
            <td style="font-size:12px;color:var(--muted);"><?= esc(str_replace('-',' ',$r['product_id'])) ?></td>
            <td><strong><?= esc($r['user_name']) ?></strong><br><span style="font-size:11px;color:var(--muted);"><?= esc($r['email']??'') ?></span></td>
            <td style="color:#d4a017;font-size:16px;"><?= $stars ?></td>
            <td><strong><?= esc($r['title']?:'') ?></strong><br><span style="font-size:13px;color:var(--muted);"><?= esc(mb_substr($r['body'],0,80)) ?>…</span></td>
            <td style="font-size:12px;color:var(--muted);"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
            <td><span class="badge <?= $r['status']==='approved'?'badge-paid':'badge-pending' ?>"><?= esc($r['status']) ?></span></td>
            <td><button onclick="deleteReview(<?= $r['id'] ?>)" class="btn-sm" style="background:#c0392b;font-size:11px;padding:4px 8px;">Delete</button></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:32px;">No reviews yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($section === 'accounts'): ?>
    <form method="get" action="" class="search-bar">
      <input type="hidden" name="s" value="accounts" />
      <input type="text" name="q" value="<?= esc($search) ?>" placeholder="Search by name or email…" />
      <button type="submit" class="btn-sm">Search</button>
      <?php if ($search): ?><a href="?s=accounts" style="align-self:center;font-size:13px;color:var(--muted);">Clear</a><?php endif; ?>
    </form>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Joined</th><th>Role</th></tr></thead>
        <tbody>
        <?php foreach ($data as $i => $u): ?>
          <tr>
            <td style="color:var(--muted);"><?= $i+1 ?></td>
            <td><strong><?= esc($u['first_name'] . ' ' . $u['last_name']) ?></strong></td>
            <td><?= esc($u['email']) ?></td>
            <td><?= esc($u['phone'] ?: '—') ?></td>
            <td style="color:var(--muted);"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
            <td><?= $u['is_admin'] ? '<span class="badge badge-admin">Admin</span>' : '<span style="color:var(--muted);font-size:12px;">Customer</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:32px;">No accounts found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($section === 'revenue'): ?>
    <div class="cards-grid">
      <?php foreach ($data as $p): ?>
      <div class="card card-gold">
        <div class="card-val"><?= fmt($p['revenue']) ?></div>
        <div class="card-lbl"><?= esc($p['label']) ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:8px;"><?= $p['orders'] ?> paid order<?= $p['orders']!=1?'s':'' ?></div>
      </div>
      <?php endforeach; ?>
    </div>

  <?php elseif ($section === 'orders'): ?>
    <div style="display:flex;gap:10px;margin-bottom:20px;">
      <a href="?s=orders" class="btn-sm" style="background:<?= !isset($_GET['status'])?'var(--gold)':'var(--border)' ?>;color:<?= !isset($_GET['status'])?'#fff':'var(--text)' ?>;">All</a>
      <a href="?s=orders&status=paid" class="btn-sm" style="background:<?= ($_GET['status']??'')=='paid'?'#1a7a3a':'var(--border)' ?>;color:<?= ($_GET['status']??'')=='paid'?'#fff':'var(--text)' ?>;">Paid</a>
      <a href="?s=orders&status=pending" class="btn-sm" style="background:<?= ($_GET['status']??'')=='pending'?'#a67800':'var(--border)' ?>;color:<?= ($_GET['status']??'')=='pending'?'#fff':'var(--text)' ?>;">Pending</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Reference</th><th>Customer</th><th>Items</th><th>Total</th><th>Tracking</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($data as $o):
          $items = json_decode($o['items_json'], true) ?: [];
          $badge = match($o['status']) { 'paid'=>'badge-paid', 'pending'=>'badge-pending', default=>'badge-failed' };
        ?>
          <tr id="row-<?= esc($o['reference']) ?>" style="cursor:pointer;" onclick="openOrder('<?= esc(addslashes(json_encode($o))) ?>')" title="Click for full details">
            <td><span style="font-family:monospace;font-size:12px;"><?= esc($o['reference']) ?></span></td>
            <td><strong><?= esc($o['customer_name']) ?></strong><br><span style="font-size:11px;color:var(--muted);"><?= esc($o['customer_email']) ?></span><br><span style="font-size:11px;color:var(--muted);"><?= esc($o['city']) ?>, <?= esc($o['state']) ?></span></td>
            <td class="items-list"><?php foreach($items as $it): ?><div><?= esc($it['name']) ?> ×<?= (int)$it['qty'] ?></div><?php endforeach; ?></td>
            <td><strong><?= fmt($o['total']) ?></strong><br><span style="font-size:11px;color:var(--muted);"><?= esc($o['shipping_service'] ?: '') ?></span></td>
            <td style="font-size:12px;">
              <?php if ($o['tracking_number']): ?>
                <span style="font-family:monospace;"><?= esc($o['tracking_number']) ?></span><br>
                <?php if ($o['estimated_delivery']): ?>
                  <span style="color:var(--muted);">ETA: <?= date('M j', strtotime($o['estimated_delivery'])) ?></span>
                <?php endif; ?>
              <?php else: ?>
                <span style="color:var(--muted);">—</span>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= $badge ?>"><?= esc($o['status']) ?></span></td>
            <td style="font-size:12px;color:var(--muted);"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
            <td style="white-space:nowrap;" onclick="event.stopPropagation()">
              <?php if ($o['status'] === 'pending'): ?>
              <button onclick="adminAction('<?= esc($o['reference']) ?>','markpaid')" class="btn-sm" style="font-size:11px;padding:6px 10px;background:#1a7a3a;margin-bottom:4px;display:block;">Mark Paid + Email</button>
              <?php endif; ?>
              <?php if ($o['status'] === 'paid'): ?>
              <button onclick="adminAction('<?= esc($o['reference']) ?>','email')" class="btn-sm" style="font-size:11px;padding:6px 10px;background:#555;margin-bottom:4px;display:block;">Resend Email</button>
              <?php if (!$o['tracking_number']): ?>
              <button onclick="openShipForm('<?= esc($o['reference']) ?>')" class="btn-sm" style="font-size:11px;padding:6px 10px;">Mark Shipped</button>
              <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:32px;">No orders yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

  <?php elseif ($section === 'products'): ?>
    <?php $max = $data ? max($data) : 1; ?>
    <div class="prod-bar-wrap">
      <?php foreach ($data as $name => $count): ?>
      <div class="prod-row">
        <div class="prod-name"><?= esc($name) ?></div>
        <div class="prod-bar-bg"><div class="prod-bar-fill" style="width:<?= round($count/$max*100) ?>%"></div></div>
        <div class="prod-count"><?= $count ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$data): ?><p style="padding:32px;text-align:center;color:var(--muted);">No sales data yet.</p><?php endif; ?>
    </div>

  <?php elseif ($section === 'buyers'): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Ref</th><th>Customer</th><th>Address</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($data as $o):
          $items = json_decode($o['items_json'], true) ?: [];
          $badge = match($o['status']) { 'paid'=>'badge-paid', 'pending'=>'badge-pending', default=>'badge-failed' };
        ?>
          <tr>
            <td style="font-family:monospace;font-size:11px;"><?= esc($o['reference']) ?></td>
            <td><strong><?= esc($o['customer_name']) ?></strong><br><span style="font-size:11px;color:var(--muted);"><?= esc($o['customer_email']) ?></span></td>
            <td style="font-size:12px;color:var(--muted);"><?= esc($o['city']) ?>, <?= esc($o['state']) ?> <?= esc($o['zip']) ?></td>
            <td class="items-list"><?php foreach($items as $it): ?><div><?= esc($it['name']) ?> ×<?= (int)$it['qty'] ?></div><?php endforeach; ?></td>
            <td><strong><?= fmt($o['total']) ?></strong></td>
            <td><span class="badge <?= $badge ?>"><?= esc($o['status']) ?></span></td>
            <td style="font-size:12px;color:var(--muted);"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:32px;">No orders yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  </div>
</main>
<!-- Order Detail Modal -->
<div id="order-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;overflow-y:auto;padding:20px;">
  <div style="background:#fff;border-radius:4px;width:640px;max-width:95vw;max-height:90vh;overflow-y:auto;">
    <div style="padding:20px 28px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:#fff;z-index:1;">
      <h3 id="od-title" style="margin:0;font-size:17px;"></h3>
      <button onclick="document.getElementById('order-modal').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#888;">&times;</button>
    </div>
    <div style="padding:24px 28px;display:grid;grid-template-columns:1fr 1fr;gap:24px;">
      <div>
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:8px;">Customer</div>
        <div id="od-name"  style="font-weight:600;font-size:15px;"></div>
        <div id="od-email" style="font-size:13px;color:var(--muted);margin-top:3px;"></div>
        <div id="od-phone" style="font-size:13px;color:var(--muted);margin-top:2px;"></div>
      </div>
      <div>
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:8px;">Ship To</div>
        <div id="od-addr" style="font-size:14px;line-height:1.6;"></div>
      </div>
    </div>
    <div style="padding:0 28px 20px;">
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:10px;">Items Ordered</div>
      <table style="width:100%;border-collapse:collapse;font-size:13px;" id="od-items"></table>
      <div style="margin-top:16px;padding:14px;background:#f9f9f9;border-radius:3px;" id="od-totals"></div>
    </div>
    <div style="padding:0 28px 20px;display:none;" id="od-notes-section">
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:8px;">Customer Notes / Special Instructions</div>
      <div id="od-notes" style="font-size:14px;line-height:1.5;background:#fffbe9;border:1px solid #f0e2b0;border-radius:3px;padding:12px 14px;white-space:pre-wrap;"></div>
    </div>
    <div style="padding:0 28px 20px;" id="od-tracking-section"></div>
    <div style="padding:16px 28px;border-top:1px solid var(--border);display:flex;gap:10px;flex-wrap:wrap;" id="od-actions"></div>
  </div>
</div>

<!-- Buy Label Modal -->
<div id="label-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1001;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:4px;width:540px;max-width:95vw;max-height:90vh;overflow-y:auto;">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:#fff;z-index:1;">
      <h3 style="margin:0;font-size:16px;">Buy Shipping Label — <span id="label-ref" style="font-family:monospace;color:var(--gold);"></span></h3>
      <button onclick="document.getElementById('label-modal').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#888;">&times;</button>
    </div>
    <div style="padding:20px 24px;">

      <!-- Step 1: Package details -->
      <div id="label-pkg-form">
        <p style="font-size:13px;color:#666;margin-bottom:18px;">Confirm package details before fetching rates.</p>

        <!-- Dimensions -->
        <div style="margin-bottom:16px;">
          <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:8px;">Dimensions (inches)</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
            <div>
              <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:3px;">Length</label>
              <input id="pkg-length" type="number" value="9" min="1" step="0.5" style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:3px;font-size:14px;font-family:monospace;" />
            </div>
            <div>
              <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:3px;">Width</label>
              <input id="pkg-width" type="number" value="6" min="1" step="0.5" style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:3px;font-size:14px;font-family:monospace;" />
            </div>
            <div>
              <label style="font-size:11px;color:var(--muted);display:block;margin-bottom:3px;">Height</label>
              <input id="pkg-height" type="number" value="2" min="1" step="0.5" style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:3px;font-size:14px;font-family:monospace;" />
            </div>
          </div>
        </div>

        <!-- Weight -->
        <div style="margin-bottom:16px;">
          <label style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);display:block;margin-bottom:6px;">Weight (oz)</label>
          <input id="pkg-weight" type="number" min="1" step="0.5" style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:3px;font-size:14px;font-family:monospace;" />
          <div id="pkg-weight-note" style="font-size:11px;color:var(--muted);margin-top:4px;"></div>
        </div>

        <!-- Insurance -->
        <div style="margin-bottom:20px;padding:14px;border:1px solid var(--border);border-radius:3px;background:var(--bg);">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;font-weight:500;">
            <input type="checkbox" id="pkg-insure" onchange="document.getElementById('pkg-insure-wrap').style.display=this.checked?'block':'none'" style="width:16px;height:16px;accent-color:var(--gold);cursor:pointer;" />
            Add Shipping Insurance
          </label>
          <div id="pkg-insure-wrap" style="display:none;margin-top:12px;">
            <label style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);display:block;margin-bottom:6px;">Declared Value ($)</label>
            <input id="pkg-insure-amount" type="number" min="1" step="0.01" placeholder="e.g. 29.98" style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:3px;font-size:14px;font-family:monospace;" />
            <div style="font-size:11px;color:var(--muted);margin-top:4px;">EasyPost will insure the shipment for this amount.</div>
          </div>
        </div>

        <button onclick="getRates()" class="btn-sm" style="background:var(--gold);width:100%;text-align:center;">Get Rates →</button>
      </div>

      <!-- Step 2: Rates (shown after Get Rates) -->
      <div id="label-rates-section" style="display:none;margin-top:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
          <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);">Available Rates</div>
          <button onclick="document.getElementById('label-rates-section').style.display='none';document.getElementById('label-pkg-form').style.display='block';" style="font-size:12px;background:none;border:none;color:var(--gold);cursor:pointer;text-decoration:underline;">← Edit package</button>
        </div>
        <div id="label-rates"></div>
        <div style="display:flex;gap:10px;margin-top:16px;">
          <button id="label-buy-btn" class="btn-sm" style="background:var(--gold);" onclick="confirmBuyLabel()">Purchase Label</button>
          <button onclick="document.getElementById('label-modal').style.display='none'" style="padding:10px 16px;border:1px solid var(--border);background:none;border-radius:3px;cursor:pointer;font-size:13px;">Cancel</button>
        </div>
      </div>

      <!-- Step 3: Result -->
      <div id="label-result-section" style="display:none;text-align:center;padding:24px 8px;">
        <div style="font-size:28px;color:#1a7a3a;margin-bottom:8px;">✓</div>
        <div style="font-size:16px;font-weight:700;margin-bottom:6px;">Label Purchased!</div>
        <div id="label-result-info" style="font-size:13px;color:#888;margin-bottom:20px;line-height:1.6;"></div>
        <a id="label-result-link" href="#" target="_blank" class="btn-sm" style="background:var(--gold);display:inline-block;text-decoration:none;margin-bottom:10px;font-size:13px;">Download &amp; Print Label →</a>
        <div style="margin-top:12px;">
          <button onclick="document.getElementById('label-modal').style.display='none';location.reload();" style="padding:9px 20px;border:1px solid var(--border);background:none;border-radius:3px;cursor:pointer;font-size:13px;">Close</button>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Ship Order Modal -->
<div id="ship-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:4px;padding:32px;width:440px;max-width:90vw;">
    <h3 style="margin:0 0 20px;font-size:17px;">Mark Order as Shipped</h3>
    <p id="ship-ref-label" style="font-family:monospace;font-size:14px;color:var(--gold);margin-bottom:20px;"></p>
    <div style="display:flex;flex-direction:column;gap:14px;">
      <div>
        <label style="font-size:12px;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);display:block;margin-bottom:4px;">Tracking Number *</label>
        <input id="ship-tracking" type="text" placeholder="e.g. 9400111899223456789012" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:3px;font-size:14px;font-family:monospace;" />
      </div>
      <div>
        <label style="font-size:12px;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);display:block;margin-bottom:4px;">Carrier</label>
        <select id="ship-carrier" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:3px;font-size:14px;">
          <option value="USPS">USPS</option>
          <option value="UPS">UPS</option>
          <option value="FedEx">FedEx</option>
          <option value="DHL">DHL</option>
          <option value="Other">Other</option>
        </select>
      </div>
      <div>
        <label style="font-size:12px;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);display:block;margin-bottom:4px;">Estimated Delivery Date</label>
        <input id="ship-eta" type="date" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:3px;font-size:14px;" />
      </div>
      <p id="ship-msg" style="font-size:13px;color:#e05c5c;display:none;"></p>
      <div style="display:flex;gap:10px;margin-top:4px;">
        <button onclick="submitShip()" class="btn-sm" style="flex:1;">Send Shipping Email</button>
        <button onclick="closeShipModal()" style="flex:1;padding:10px;border:1px solid var(--border);background:none;border-radius:3px;cursor:pointer;font-size:13px;">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script>
function openOrder(jsonStr) {
  var o = JSON.parse(jsonStr);
  var items = [];
  try { items = JSON.parse(o.items_json); } catch(e) {}

  document.getElementById('od-title').textContent = o.reference + ' — ' + (o.status === 'paid' ? '✓ Paid' : 'Pending');
  document.getElementById('od-name').textContent  = o.customer_name;
  document.getElementById('od-email').textContent = o.customer_email;
  document.getElementById('od-phone').textContent = o.customer_phone || '';
  document.getElementById('od-addr').innerHTML    =
    (o.address_line1 || '—') + '<br>' + (o.address_line2 ? o.address_line2 + '<br>' : '') + (o.city||'') + ', ' + (o.state||'') + ' ' + (o.zip||'') + '<br>' + (o.country||'United States');

  var notesText = (o.notes || '').trim();
  document.getElementById('od-notes-section').style.display = notesText ? 'block' : 'none';
  document.getElementById('od-notes').textContent = notesText;

  var itemRows = items.map(function(i){
    return '<tr><td style="padding:8px 0;border-bottom:1px solid #eee;">' + i.name + '<br><span style="color:#888;font-size:11px;">' + (i.variantLabel||'') + '</span></td>' +
      '<td style="padding:8px 8px;border-bottom:1px solid #eee;text-align:center;color:#888;">×' + i.qty + '</td>' +
      '<td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;font-weight:600;">$' + ((parseFloat(i.price)||0)*(parseInt(i.qty,10)||1)).toFixed(2) + '</td></tr>';
  }).join('');
  document.getElementById('od-items').innerHTML = '<tr><th style="text-align:left;font-size:11px;color:#888;padding-bottom:8px;">Product</th><th style="text-align:center;font-size:11px;color:#888;padding-bottom:8px;padding:0 8px 8px;">Qty</th><th style="text-align:right;font-size:11px;color:#888;padding-bottom:8px;">Price</th></tr>' + itemRows;

  document.getElementById('od-totals').innerHTML =
    '<div style="display:flex;justify-content:space-between;font-size:13px;color:#666;padding:3px 0;">Subtotal <span>$' + parseFloat(o.subtotal||0).toFixed(2) + '</span></div>' +
    '<div style="display:flex;justify-content:space-between;font-size:13px;color:#666;padding:3px 0;">Shipping (' + (o.shipping_service||'—') + ') <span>$' + parseFloat(o.shipping||0).toFixed(2) + '</span></div>' +
    (parseFloat(o.tax||0)>0 ? '<div style="display:flex;justify-content:space-between;font-size:13px;color:#666;padding:3px 0;">Tax <span>$' + parseFloat(o.tax).toFixed(2) + '</span></div>' : '') +
    '<div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700;padding:8px 0 0;border-top:1px solid #ddd;margin-top:6px;">Total <span>$' + parseFloat(o.total||0).toFixed(2) + '</span></div>';

  var trackHtml = '';
  if (o.tracking_number) {
    trackHtml = '<div style="background:#f9f7f2;border-left:3px solid #d4a017;padding:14px 16px;border-radius:0 3px 3px 0;">' +
      '<div style="font-size:11px;color:#888;text-transform:uppercase;margin-bottom:4px;">Tracking</div>' +
      '<div style="font-family:monospace;font-size:16px;font-weight:700;">' + o.tracking_number + '</div>' +
      '<div style="font-size:12px;color:#888;margin-top:2px;">' + (o.carrier||'') + (o.estimated_delivery ? ' · ETA: ' + o.estimated_delivery : '') + '</div>' +
      '<a href="https://parcelsapp.com/en/tracking/' + encodeURIComponent(o.tracking_number) + '" target="_blank" style="display:inline-block;margin-top:8px;font-size:12px;color:#d4a017;">Track →</a>' +
      (o.label_url ? '<a href="' + o.label_url + '" target="_blank" style="display:inline-block;margin-top:8px;margin-left:12px;font-size:12px;color:#d4a017;">Print Label →</a>' : '') +
      '</div>';
  } else if (o.address_line1) {
    trackHtml = '<button class="btn-sm" style="background:#d4a017;" onclick="buyLabel(\'' + o.reference + '\')">🏷 Buy Shipping Label</button>';
  }
  document.getElementById('od-tracking-section').innerHTML = trackHtml;

  var actions = '';
  if (o.status === 'pending') actions += '<button onclick="adminAction(\'' + o.reference + '\',\'markpaid\');document.getElementById(\'order-modal\').style.display=\'none\';" class="btn-sm" style="background:#1a7a3a;">Mark Paid + Email</button>';
  if (o.status === 'pending') actions += '<button onclick="deleteOrder(\'' + o.reference + '\')" class="btn-sm" style="background:#c0392b;">Delete Order</button>';
  if (o.status === 'paid')    actions += '<button onclick="adminAction(\'' + o.reference + '\',\'email\');document.getElementById(\'order-modal\').style.display=\'none\';" class="btn-sm" style="background:#555;">Resend Order Email</button>';
  if (o.status === 'paid' && o.tracking_number) actions += '<button onclick="adminAction(\'' + o.reference + '\',\'shipping\');document.getElementById(\'order-modal\').style.display=\'none\';" class="btn-sm" style="background:#2a6496;">Resend Shipping Email</button>';
  if (o.status === 'paid' && !o.tracking_number) actions += '<button onclick="buyLabel(\'' + o.reference + '\')" class="btn-sm" style="background:#d4a017;">🏷 Buy Shipping Label</button>';
  if (o.status === 'paid' && !o.tracking_number) actions += '<button onclick="openShipForm(\'' + o.reference + '\');document.getElementById(\'order-modal\').style.display=\'none\';" class="btn-sm">Enter Tracking Manually</button>';
  _slipOrder = o;
  actions += '<button onclick="printSlip()" class="btn-sm" style="background:#fff;color:#333;border:1px solid #ccc;">Print Packing Slip</button>';
  document.getElementById('od-actions').innerHTML = actions;

  document.getElementById('order-modal').style.display = 'flex';
}

function deleteOrder(ref) {
  if (!confirm('Delete order ' + ref + '?\nThis cannot be undone.')) return;
  var body = new FormData();
  body.append('reference', ref);
  fetch('api/delete-order.php', { method: 'POST', body: body })
    .then(function(r){
      return r.text().then(function(txt){
        try { return JSON.parse(txt); }
        catch(e) { throw new Error('HTTP ' + r.status + ': ' + txt.substring(0, 300)); }
      });
    })
    .then(function(d){
      if (!d.ok) { alert('Error: ' + (d.error || 'Unknown error')); return; }
      document.getElementById('order-modal').style.display = 'none';
      location.reload();
    })
    .catch(function(e){ alert('Delete failed: ' + (e.message || e)); });
}

var labelShipmentId = '', labelRef = '';

function buyLabel(ref) {
  labelRef = ref;
  labelShipmentId = '';
  document.getElementById('order-modal').style.display = 'none';
  document.getElementById('label-modal').style.display = 'flex';
  document.getElementById('label-ref').textContent = ref;

  // Reset to step 1
  document.getElementById('label-pkg-form').style.display = 'block';
  document.getElementById('label-rates-section').style.display = 'none';
  document.getElementById('label-rates').innerHTML = '';
  document.getElementById('pkg-insure').checked = false;
  document.getElementById('pkg-insure-wrap').style.display = 'none';
  document.getElementById('pkg-insure-amount').value = '';

  // Pre-fill weight from order items if available
  var totalOz = 0;
  var noteText = '';
  if (_slipOrder && _slipOrder.reference === ref) {
    var items = [];
    try { items = JSON.parse(_slipOrder.items_json); } catch(e) {}
    items.forEach(function(i) {
      var v = (i.variantLabel||'').toLowerCase();
      var w = v.indexOf('4 oz') !== -1 ? 5.5 : v.indexOf('2 oz') !== -1 ? 3.5 : 2.3;
      totalOz += (i.qty||1) * w;
    });
    if (totalOz > 0) noteText = 'Auto-calculated from order items (' + items.map(function(i){ return i.name + ' ×' + (i.qty||1); }).join(', ') + ')';
  }
  document.getElementById('pkg-weight').value = totalOz > 0 ? Math.round(totalOz * 10) / 10 : 2.3;
  document.getElementById('pkg-weight-note').textContent = noteText;
}

function getRates() {
  var length = parseFloat(document.getElementById('pkg-length').value) || 6;
  var width  = parseFloat(document.getElementById('pkg-width').value)  || 4;
  var height = parseFloat(document.getElementById('pkg-height').value) || 4;
  var weight = parseFloat(document.getElementById('pkg-weight').value) || 4;
  if (weight < 1) { alert('Please enter a valid weight.'); return; }

  document.getElementById('label-pkg-form').style.display = 'none';
  document.getElementById('label-rates-section').style.display = 'block';
  document.getElementById('label-rates').innerHTML = '<p style="color:#888;font-size:13px;padding:8px 0;">Fetching rates…</p>';
  document.getElementById('label-buy-btn').disabled = false;
  document.getElementById('label-buy-btn').textContent = 'Purchase Label';

  var body = new FormData();
  body.append('reference', labelRef);
  body.append('action',    'rates');
  body.append('length',    length);
  body.append('width',     width);
  body.append('height',    height);
  body.append('weight',    weight);

  fetch('api/buy-label.php', { method:'POST', body:body })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d.ok) {
        document.getElementById('label-rates').innerHTML = '<p style="color:red;font-size:13px;">' + (d.error||'Failed to fetch rates.') + '</p>';
        return;
      }
      labelShipmentId = d.shipment_id;
      if (!d.rates || !d.rates.length) {
        document.getElementById('label-rates').innerHTML = '<p style="color:#888;font-size:13px;">No rates available for this address.</p>';
        return;
      }
      var html = '<div style="display:flex;flex-direction:column;gap:8px;">';
      d.rates.forEach(function(r) {
        html += '<label style="display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--border);border-radius:3px;cursor:pointer;">' +
          '<input type="radio" name="label_rate" value="' + r.id + '" style="accent-color:#d4a017;flex-shrink:0;">' +
          '<span style="flex:1;font-size:13px;">' + r.service + (r.days && r.days !== '?' ? ' <span style="color:var(--muted);font-size:11px;">(' + r.days + ' days)</span>' : '') + '</span>' +
          '<strong style="white-space:nowrap;">$' + r.rate + '</strong></label>';
      });
      html += '</div>';
      document.getElementById('label-rates').innerHTML = html;
    })
    .catch(function() {
      document.getElementById('label-rates').innerHTML = '<p style="color:red;font-size:13px;">Connection error. Please try again.</p>';
    });
}

function confirmBuyLabel() {
  var selected = document.querySelector('input[name="label_rate"]:checked');
  if (!selected) { alert('Please select a shipping rate first.'); return; }

  var insure       = document.getElementById('pkg-insure').checked;
  var insureAmount = insure ? (parseFloat(document.getElementById('pkg-insure-amount').value) || 0) : 0;
  if (insure && insureAmount <= 0) { alert('Please enter a declared value for insurance.'); return; }

  var btn = document.getElementById('label-buy-btn');
  btn.disabled = true; btn.textContent = 'Purchasing…';

  var body = new FormData();
  body.append('reference',    labelRef);
  body.append('action',       'buy');
  body.append('shipment_id',  labelShipmentId);
  body.append('rate_id',      selected.value);
  if (insureAmount > 0) body.append('insure_amount', insureAmount.toFixed(2));

  fetch('api/buy-label.php', { method:'POST', body:body })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (!d.ok) { alert('Error: ' + (d.error||'Unknown error')); btn.disabled=false; btn.textContent='Purchase Label'; return; }
      document.getElementById('label-rates-section').style.display = 'none';
      var info = 'Tracking: ' + d.tracking + '\n' + (d.service || '');
      if (d.insured) info += '\nInsured for $' + insureAmount.toFixed(2);
      info += '\nShipping email sent to customer.';
      if (d.saved === false) info += '\n\n⚠ Label was purchased, but tracking could NOT be saved to the order. Record this tracking number manually: ' + d.tracking;
      document.getElementById('label-result-info').style.whiteSpace = 'pre-line';
      document.getElementById('label-result-info').textContent = info;
      if (d.label_url) {
        document.getElementById('label-result-link').href = d.label_url;
        document.getElementById('label-result-link').style.display = 'inline-block';
      } else {
        document.getElementById('label-result-link').style.display = 'none';
      }
      document.getElementById('label-result-section').style.display = 'block';
    })
    .catch(function() {
      alert('Connection error. Please try again.');
      btn.disabled=false; btn.textContent='Purchase Label';
    });
}

function deleteReview(id) {
  if (!confirm('Delete this review permanently?')) return;
  var body = new FormData();
  body.append('action', 'delete');
  body.append('id', id);
  fetch('api/reviews.php', { method:'POST', body:body })
    .then(function(r){ return r.json(); })
    .then(function(d){ if (d.ok) location.reload(); });
}

function adminAction(ref, action) {
  var labels = {
    markpaid: 'Mark as Paid and send confirmation email?',
    shipping:  'Resend shipping email with tracking number?',
    email:     'Resend confirmation email to customer?'
  };
  if (!confirm(labels[action] || 'Continue?')) return;
  var body = new FormData();
  body.append('reference', ref);
  body.append('action', action);
  fetch('api/resend-email.php', { method:'POST', body:body })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (d.ok) { alert('Done! Email sent successfully.'); location.reload(); }
      else alert('Error: ' + (d.error || 'Unknown'));
    })
    .catch(function(e){
      alert('Server error — ' + e.message);
    });
}

var currentRef = '';
function openShipForm(ref) {
  currentRef = ref;
  document.getElementById('ship-ref-label').textContent = 'Order: ' + ref;
  document.getElementById('ship-tracking').value = '';
  document.getElementById('ship-eta').value = '';
  document.getElementById('ship-msg').style.display = 'none';
  var m = document.getElementById('ship-modal');
  m.style.display = 'flex';
}
function closeShipModal() {
  document.getElementById('ship-modal').style.display = 'none';
}
function submitShip() {
  var tracking = document.getElementById('ship-tracking').value.trim();
  var carrier  = document.getElementById('ship-carrier').value;
  var eta      = document.getElementById('ship-eta').value;
  var msg      = document.getElementById('ship-msg');
  if (!tracking) { msg.textContent = 'Please enter a tracking number.'; msg.style.display='block'; return; }

  var body = new FormData();
  body.append('reference',         currentRef);
  body.append('tracking_number',   tracking);
  body.append('carrier',           carrier);
  body.append('estimated_delivery', eta);

  var submitBtn = document.querySelector('#ship-modal button[onclick="submitShip()"]');
  if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Sending…'; }

  fetch('api/ship-order.php', { method:'POST', body:body })
    .then(function(r){ return r.json(); })
    .then(function(d){
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Send Shipping Email'; }
      if (d.ok) {
        closeShipModal();
        window.location.reload();
      } else {
        msg.textContent = d.error || 'Error sending.';
        msg.style.display = 'block';
      }
    })
    .catch(function(e){
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Send Shipping Email'; }
      msg.textContent = 'Server error — ' + e.message + '. Check server logs.';
      msg.style.display = 'block';
    });
}
document.getElementById('ship-modal').addEventListener('click', function(e) {
  if (e.target === this) closeShipModal();
});

var _slipOrder = null;

function printSlip() {
  var o = _slipOrder;
  if (!o) return;
  var items = [];
  try { items = typeof o.items_json === 'string' ? JSON.parse(o.items_json) : (o.items_json || []); } catch(e) {}

  function generate(logoData) {
    var jsPDF = window.jspdf.jsPDF;
    var doc = new jsPDF({ unit: 'mm', format: 'letter', orientation: 'portrait' });
    var lm = 15, rm = 196, cw = 181, y = 15;

    var sub     = parseFloat(o.subtotal||0);
    var shipAmt = parseFloat(o.shipping||0);
    var taxAmt  = parseFloat(o.tax||0);
    var total   = parseFloat(o.total||0);
    var dateStr = '';
    try { dateStr = new Date(o.created_at.replace(' ','T')).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'}); } catch(e){}
    var firstName = (o.customer_name||'').split(' ')[0];

    function dashedLine(yy) { doc.setLineDashPattern([1,1],0); doc.setLineWidth(0.2); doc.line(lm,yy,rm,yy); doc.setLineDashPattern([],0); }
    function solidLine(yy, w) { doc.setLineWidth(0.3); doc.line(lm,yy,w||rm,yy); }
    var pageH = doc.internal.pageSize.getHeight();
    var bottomY = pageH - 15;  // 15mm bottom margin
    function ensureSpace(needed) { if (y + needed > bottomY) { doc.addPage(); y = 15; } }

    // ── Logo top-right ────────────────────────────────────
    if (logoData) {
      doc.addImage(logoData, 'PNG', rm - 44, y - 2, 44, 14);
    }

    // ── Ship To (large header) ────────────────────────────
    doc.setFont('helvetica','normal'); doc.setFontSize(12); doc.setTextColor(100);
    doc.text('Ship To:', lm, y); y += 9;
    doc.setFont('helvetica','bold'); doc.setFontSize(23); doc.setTextColor(0);
    doc.text(o.customer_name||'', lm, y); y += 9;
    doc.setFontSize(18);
    doc.text(o.address_line1||'', lm, y); y += 7;
    if (o.address_line2) { doc.text(o.address_line2, lm, y); y += 7; }
    doc.text(((o.city||'')+',  '+(o.state||'')+'  '+(o.zip||'')).toUpperCase(), lm, y); y += 10;

    dashedLine(y); y += 7;

    // ── Order ID + thank you ─────────────────────────────
    doc.setFont('helvetica','bold'); doc.setFontSize(16);
    doc.text('Order ID: ' + (o.reference||''), lm, y); y += 7;
    doc.setFont('helvetica','normal'); doc.setFontSize(13); doc.setTextColor(80);
    doc.text('Thank you for buying from SilexCore Technologies.', lm, y); y += 9;
    doc.setTextColor(0);

    // ── Info box (Shipping Address | Order details) ───────
    var col2 = lm + cw*0.45, col3 = lm + cw*0.65;
    var rightValW = rm - col3 - 4; // max width for right-column values before wrapping

    // Pre-calculate right-column row heights based on wrapped text
    var metaRows = [
      ['Order Date:',      dateStr],
      ['Shipping Service:', o.shipping_service||'Standard'],
      ['Buyer Name:',      firstName],
      ['Seller Name:',     'SilexCore Technologies']
    ];
    doc.setFontSize(11);
    var rowHeights = metaRows.map(function(r) {
      var lines = doc.splitTextToSize(r[1], rightValW);
      return Math.max(10, lines.length * 6 + 4);
    });
    // left cell content lines (suite/unit inserted when present)
    var leftBody = [o.customer_name||'', o.address_line1||''];
    if (o.address_line2) leftBody.push(o.address_line2);
    leftBody.push((o.city||'')+', '+(o.state||'')+' '+(o.zip||''));
    leftBody.push(o.country||'United States');
    var leftCellH = 7 + leftBody.length*7 + 3;

    var boxH = Math.max(42, rowHeights.reduce(function(s,h){return s+h;},0) + 6, leftCellH);

    doc.setDrawColor(150); doc.setLineWidth(0.3);
    // left cell — shipping address
    doc.setFont('helvetica','bold'); doc.setFontSize(12);
    doc.text('Shipping Address:', lm+3, y+7);
    doc.setFont('helvetica','normal'); doc.setFontSize(12);
    var ly = y + 14;
    leftBody.forEach(function(line){ doc.text(line, lm+3, ly); ly += 7; });

    // right cells — with wrapping
    var ry = y + 7;
    metaRows.forEach(function(r, idx) {
      doc.setFont('helvetica','normal'); doc.setFontSize(11); doc.setTextColor(100);
      doc.text(r[0], col2, ry);
      var lines = doc.splitTextToSize(r[1], rightValW);
      doc.setFont('helvetica','bold'); doc.setFontSize(11); doc.setTextColor(0);
      doc.text(lines, col3, ry);
      ry += rowHeights[idx];
    });

    doc.rect(lm, y, cw, boxH);
    doc.line(col2-2, y, col2-2, y+boxH);
    doc.setDrawColor(0);
    y += boxH + 7;

    // ── Items table ───────────────────────────────────────
    var qW=18, prW=30, totW=62, pdW=cw-qW-prW-totW;
    var totX = rm - totW;
    var numItems = items.length || 1;
    var shipPerItem = shipAmt / numItems;
    var taxPerItem  = taxAmt  / numItems;
    // min height needed for the Order Totals column per row (always 4 lines)
    var totColH = 5 + 6 + 6 + 6 + 4 + 7 + 5;

    function drawItemsHeader() {
      doc.setFillColor(230,230,230); doc.rect(lm, y, cw, 10, 'F');
      doc.setFont('helvetica','bold'); doc.setFontSize(11); doc.setTextColor(0);
      doc.text('Quantity',     lm+3,        y+7);
      doc.text('Product',      lm+qW+3,     y+7);
      doc.text('Unit price',   lm+qW+pdW+3, y+7);
      doc.text('Order Totals', totX+3,      y+7);
      solidLine(y+10, rm); y += 10;
    }

    drawItemsHeader();
    var tableTop = y;
    items.forEach(function(i) {
      var itemSub   = (parseFloat(i.price)||0) * (parseInt(i.qty,10)||1);
      var itemTotal = itemSub + shipPerItem + taxPerItem;

      // Wrap long product names
      doc.setFont('helvetica','bold'); doc.setFontSize(12);
      var nameLines = doc.splitTextToSize(i.name||'', pdW-4);
      var leftH = 6 + nameLines.length*6 + (i.variantLabel ? 6 : 0) + 4;
      var rh = Math.max(totColH, leftH);

      // If this row would overflow the page, close the current table border and start a new page
      if (y + rh > bottomY) {
        doc.setDrawColor(150); doc.rect(lm, tableTop, cw, y - tableTop); doc.setDrawColor(0);
        doc.addPage(); y = 15;
        drawItemsHeader();
        tableTop = y;
      }

      // Qty
      doc.setFont('helvetica','normal'); doc.setFontSize(12); doc.setTextColor(0);
      doc.text(String(i.qty||1), lm+3, y+8);

      // Product name (wrapped) + variant
      doc.setFont('helvetica','bold'); doc.setFontSize(12);
      doc.text(nameLines, lm+qW+3, y+8);
      if (i.variantLabel) {
        doc.setFont('helvetica','normal'); doc.setFontSize(10);
        doc.text(i.variantLabel, lm+qW+3, y+8+nameLines.length*6);
      }

      // Unit price
      doc.setFont('helvetica','normal'); doc.setFontSize(12);
      doc.text('$'+parseFloat(i.price||0).toFixed(2), lm+qW+pdW+3, y+8);

      // Per-item Order Totals column
      var tx = totX+3, ty = y+5;
      doc.setFont('helvetica','normal'); doc.setFontSize(11);
      doc.text('Item subtotal',  tx, ty); doc.text('$'+itemSub.toFixed(2),     rm-2, ty, {align:'right'}); ty+=6;
      doc.text('Shipping total', tx, ty); doc.text('$'+shipPerItem.toFixed(2), rm-2, ty, {align:'right'}); ty+=6;
      doc.text('Tax',            tx, ty); doc.text('$'+taxPerItem.toFixed(2),  rm-2, ty, {align:'right'}); ty+=6;
      // separator only within the Order Totals cell
      doc.setLineWidth(0.2); doc.line(totX+1, ty, rm, ty); ty+=4;
      doc.setFont('helvetica','bold'); doc.setFontSize(11);
      doc.text('Item total', tx, ty); doc.text('$'+itemTotal.toFixed(2), rm-2, ty, {align:'right'});

      doc.setDrawColor(180);
      doc.line(lm, y+rh, rm, y+rh);
      doc.line(totX, y, totX, y+rh);
      y += rh;
    });
    // Outer border around item rows on the current page segment
    doc.setDrawColor(150); doc.rect(lm, tableTop, cw, y-tableTop);
    doc.setDrawColor(0);

    // ── Grand total ───────────────────────────────────────
    ensureSpace(20);
    y += 6;
    doc.setFont('helvetica','bold'); doc.setFontSize(15); doc.setTextColor(0);
    doc.text('Grand total: $'+total.toFixed(2), rm, y, {align:'right'}); y += 13;

    dashedLine(y); y += 7;

    // ── Customer notes / special instructions ─────────────
    var slipNotes = (o.notes || '').trim();
    if (slipNotes) {
      var noteLines = doc.splitTextToSize(slipNotes, cw);
      ensureSpace(12 + noteLines.length*6);
      doc.setFont('helvetica','bold'); doc.setFontSize(13); doc.setTextColor(0);
      doc.text('Special instructions:', lm, y); y += 6;
      doc.setFont('helvetica','normal'); doc.setFontSize(12); doc.setTextColor(50);
      doc.text(noteLines, lm, y); y += noteLines.length*6 + 5;
      doc.setTextColor(0);
      dashedLine(y); y += 7;
    }

    // ── Return policy ─────────────────────────────────────
    ensureSpace(50);
    doc.setFont('helvetica','bold'); doc.setFontSize(13);
    doc.text('Returning your item:', lm, y); y += 6;
    doc.setFont('helvetica','normal'); doc.setFontSize(12); doc.setTextColor(50);
    var returnText = 'To return an item, please visit silexcore.com/returns within 30 days of delivery and complete the return request form. Please have your order reference number ready. For assistance, contact us at info@silexcore.com or call +1 (786) 816-5748.';
    var returnLines = doc.splitTextToSize(returnText, cw);
    doc.text(returnLines, lm, y); y += returnLines.length*6 + 5;

    doc.setTextColor(0);
    doc.setFont('helvetica','bold'); doc.setFontSize(12);
    doc.text('Thanks for your purchase from SilexCore Technologies.', lm, y); y += 6;
    doc.setFont('helvetica','normal');
    doc.text('Questions: info@silexcore.com  |  silexcore.com  |  +1 (786) 816-5748', lm, y); y += 7;

    dashedLine(y);

    window.open(URL.createObjectURL(doc.output('blob')), '_blank');
  }

  function loadAndGenerate(logoData) {
    if (window.jspdf && window.jspdf.jsPDF) {
      generate(logoData);
    } else {
      var s = document.createElement('script');
      s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
      s.onload = function(){ generate(logoData); };
      s.onerror = function(){ alert('Could not load PDF library.'); };
      document.head.appendChild(s);
    }
  }

  // Load logo as base64 first, then generate
  var img = new Image();
  img.crossOrigin = 'Anonymous';
  img.onload = function() {
    var logoData = null;
    try {
      var c = document.createElement('canvas');
      c.width = img.width; c.height = img.height;
      var ctx = c.getContext('2d');
      ctx.drawImage(img, 0, 0);
      // Convert to grayscale for thermal printing
      var px = ctx.getImageData(0, 0, c.width, c.height);
      for (var i = 0; i < px.data.length; i += 4) {
        var gray = 0.299*px.data[i] + 0.587*px.data[i+1] + 0.114*px.data[i+2];
        px.data[i] = px.data[i+1] = px.data[i+2] = gray;
      }
      ctx.putImageData(px, 0, 0);
      logoData = c.toDataURL('image/png');
    } catch(e) {}
    loadAndGenerate(logoData);
  };
  img.onerror = function(){ loadAndGenerate(null); };
  img.src = '/assets/img/logo/silexcore-logo.png';
}
</script>
</body>
</html>
