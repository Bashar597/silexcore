<?php
// Native PHP mailer v2 — invoice style
function smtp_send(string $to, string $to_name, string $subject, string $html, string $attachUrl = '', string $attachName = 'label.pdf'): bool {
    $from      = MAIL_FROM;
    $from_name = MAIL_FROM_NAME;

    if ($attachUrl) {
        // Download the attachment
        $pdfData = @file_get_contents($attachUrl);
        if ($pdfData) {
            $boundary = md5(uniqid());
            $headers  = "From: {$from_name} <{$from}>\r\n";
            $headers .= "Reply-To: {$from}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
            $headers .= "X-Mailer: PHP\r\n";

            $body  = "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($html)) . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/pdf; name=\"{$attachName}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$attachName}\"\r\n\r\n";
            $body .= chunk_split(base64_encode($pdfData)) . "\r\n";
            $body .= "--{$boundary}--";

            $ok = mail($to, $subject, $body, $headers, "-f{$from}");
            if (!$ok) error_log("mail() with attachment failed sending to $to");
            return $ok;
        }
    }

    // Plain HTML email (no attachment)
    $headers  = "From: {$from_name} <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "Bcc: " . MAIL_COMPANY . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP\r\n";

    $ok = mail($to, $subject, $html, $headers, "-f{$from}");
    if (!$ok) error_log("mail() failed sending to $to");
    return $ok;
}

// ── Email templates ──────────────────────────────────────────

function email_customer(array $order): bool {
    $items = json_decode($order['items_json'], true) ?: [];

    // Pre-compute all formatted values (no concatenation inside heredoc)
    $safeName    = htmlspecialchars($order['customer_name']);
    $safeRef     = htmlspecialchars($order['reference']);
    $safeEmail   = htmlspecialchars($order['customer_email']);
    $safeAddr1   = htmlspecialchars($order['address_line1'] ?? '');
    $safeAddr2   = htmlspecialchars($order['address_line2'] ?? '');
    $addr2LineInvoice = $safeAddr2 !== '' ? "<div style=\"font-size:13px;color:#555;\">{$safeAddr2}</div>" : '';
    $safeCity    = htmlspecialchars($order['city']  ?? '');
    $safeState   = htmlspecialchars($order['state'] ?? '');
    $safeZip     = htmlspecialchars($order['zip']   ?? '');
    $safeCountry = htmlspecialchars($order['country'] ?? 'United States');
    $fmtDate     = !empty($order['created_at']) ? date('F j, Y', strtotime($order['created_at'])) : date('F j, Y');
    $fmtSubtotal = '$' . number_format((float)$order['subtotal'], 2);
    $fmtShipping = '$' . number_format((float)$order['shipping'], 2);
    $fmtTax      = '$' . number_format((float)$order['tax'], 2);
    $fmtTotal    = '$' . number_format((float)$order['total'], 2);
    $safeService = htmlspecialchars($order['shipping_service'] ?? 'Standard');

    $rows = '';
    foreach ($items as $item) {
        $unitPrice = '$' . number_format((float)$item['price'], 2);
        $lineTotal = '$' . number_format((float)$item['price'] * (int)($item['qty'] ?? 1), 2);
        $itemName  = htmlspecialchars($item['name'] ?? '');
        $itemVar   = htmlspecialchars($item['variantLabel'] ?? '');
        $itemQty   = (int)($item['qty'] ?? 1);
        $rows .= "<tr>
            <td style='padding:12px 0;border-bottom:1px solid #eee;font-size:14px;'>
              <strong>{$itemName}</strong>
              <div style='font-size:12px;color:#999;margin-top:2px;'>{$itemVar}</div>
            </td>
            <td style='padding:12px 8px;border-bottom:1px solid #eee;text-align:center;font-size:14px;color:#555;'>{$itemQty}</td>
            <td style='padding:12px 0;border-bottom:1px solid #eee;text-align:right;font-size:14px;color:#555;'>{$unitPrice}</td>
            <td style='padding:12px 0;border-bottom:1px solid #eee;text-align:right;font-size:14px;font-weight:600;'>{$lineTotal}</td>
        </tr>";
    }

    $taxRow = (float)$order['tax'] > 0
        ? "<tr><td colspan='3' style='padding:5px 0;text-align:right;font-size:13px;color:#888;'>Tax</td><td style='padding:5px 0;text-align:right;font-size:13px;color:#888;'>{$fmtTax}</td></tr>"
        : '';

    $html = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>body{margin:0;padding:0;background:#f4f4f4;font-family:'Helvetica Neue',Arial,sans-serif;}</style>
</head>
<body>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:32px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;max-width:600px;border:1px solid #e0e0e0;">

  <!-- Invoice Header -->
  <tr>
    <td style="padding:32px 40px 24px;border-bottom:3px solid #d4a017;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td>
            <div style="font-size:20px;font-weight:700;color:#111;letter-spacing:-0.02em;">SilexCore Technologies</div>
            <div style="font-size:12px;color:#999;margin-top:4px;">Charlotte, NC 28277 &nbsp;·&nbsp; info@silexcore.com</div>
          </td>
          <td align="right">
            <div style="font-size:28px;font-weight:800;color:#d4a017;letter-spacing:0.04em;">INVOICE</div>
            <div style="font-size:12px;color:#999;margin-top:4px;">Payment Confirmed &nbsp;&#10003;</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Invoice Meta -->
  <tr>
    <td style="padding:24px 40px;background:#fafafa;border-bottom:1px solid #eee;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="width:50%;vertical-align:top;">
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.1em;color:#aaa;margin-bottom:6px;">Bill To</div>
            <div style="font-size:14px;font-weight:600;color:#111;">{$safeName}</div>
            <div style="font-size:13px;color:#555;margin-top:2px;">{$safeEmail}</div>
            <div style="font-size:13px;color:#555;margin-top:2px;">{$safeAddr1}</div>
            {$addr2LineInvoice}
            <div style="font-size:13px;color:#555;">{$safeCity}, {$safeState} {$safeZip}</div>
            <div style="font-size:13px;color:#555;">{$safeCountry}</div>
          </td>
          <td style="width:50%;vertical-align:top;" align="right">
            <table cellpadding="0" cellspacing="0" align="right">
              <tr><td style="font-size:12px;color:#aaa;padding:2px 0;text-align:right;">Invoice&nbsp;#</td><td style="font-size:12px;font-weight:700;color:#111;padding:2px 0 2px 12px;font-family:monospace;">{$safeRef}</td></tr>
              <tr><td style="font-size:12px;color:#aaa;padding:2px 0;text-align:right;">Date</td><td style="font-size:12px;color:#555;padding:2px 0 2px 12px;">{$fmtDate}</td></tr>
              <tr><td style="font-size:12px;color:#aaa;padding:2px 0;text-align:right;">Status</td><td style="padding:2px 0 2px 12px;"><span style="font-size:11px;font-weight:700;background:#e6f7ec;color:#1a7a3a;padding:2px 8px;border-radius:10px;">PAID</span></td></tr>
              <tr><td style="font-size:12px;color:#aaa;padding:2px 0;text-align:right;">Shipping</td><td style="font-size:12px;color:#555;padding:2px 0 2px 12px;">{$safeService}</td></tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Items Table -->
  <tr>
    <td style="padding:0 40px;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <thead>
          <tr style="background:#f9f9f9;">
            <th style="padding:10px 0;font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#aaa;text-align:left;border-bottom:2px solid #eee;">Product</th>
            <th style="padding:10px 8px;font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#aaa;text-align:center;border-bottom:2px solid #eee;">Qty</th>
            <th style="padding:10px 0;font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#aaa;text-align:right;border-bottom:2px solid #eee;">Unit Price</th>
            <th style="padding:10px 0;font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#aaa;text-align:right;border-bottom:2px solid #eee;">Amount</th>
          </tr>
        </thead>
        <tbody>
          {$rows}
        </tbody>
      </table>
    </td>
  </tr>

  <!-- Totals -->
  <tr>
    <td style="padding:16px 40px 24px;">
      <table cellpadding="0" cellspacing="0" align="right">
        <tr><td style="padding:4px 0;font-size:13px;color:#888;text-align:right;padding-right:24px;">Subtotal</td><td style="padding:4px 0;font-size:13px;color:#555;text-align:right;">{$fmtSubtotal}</td></tr>
        <tr><td style="padding:4px 0;font-size:13px;color:#888;text-align:right;padding-right:24px;">Shipping</td><td style="padding:4px 0;font-size:13px;color:#555;text-align:right;">{$fmtShipping}</td></tr>
        {$taxRow}
        <tr>
          <td colspan="2"><hr style="border:none;border-top:2px solid #111;margin:8px 0;"></td>
        </tr>
        <tr>
          <td style="padding:4px 0;font-size:16px;font-weight:700;color:#111;text-align:right;padding-right:24px;">Total</td>
          <td style="padding:4px 0;font-size:20px;font-weight:800;color:#d4a017;text-align:right;">{$fmtTotal}</td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Notice -->
  <tr>
    <td style="padding:20px 40px;background:#fffbf0;border-top:1px solid #f0e8cc;border-bottom:1px solid #f0e8cc;">
      <p style="margin:0;font-size:13px;color:#888;line-height:1.7;">
        Your order is being prepared and you'll receive a shipping confirmation with tracking details once it's on its way.<br>
        Questions? Reply to this email or reach us at <a href="mailto:info@silexcore.com" style="color:#d4a017;">info@silexcore.com</a> or <a href="tel:+17868165748" style="color:#d4a017;">+1 (786) 816-5748</a>.
      </p>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="padding:20px 40px;text-align:center;">
      <p style="margin:0;font-size:12px;color:#bbb;">
        SilexCore Technologies &nbsp;·&nbsp; Charlotte, NC 28277 &nbsp;·&nbsp;
        <a href="https://silexcore.com" style="color:#bbb;">silexcore.com</a>
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body></html>
HTML;

    return smtp_send($order['customer_email'], $order['customer_name'], "Invoice {$order['reference']} — SilexCore Technologies", $html);
}

function email_company(array $order): bool {
    $items = json_decode($order['items_json'], true) ?: [];
    $rows = '';
    foreach ($items as $item) {
        $price = number_format((float)$item['price'] * (int)$item['qty'], 2);
        $rows .= "<tr><td style='padding:8px 0;border-bottom:1px solid #eee;'>{$item['name']} — {$item['variantLabel']}</td><td style='text-align:center;padding:8px 0;border-bottom:1px solid #eee;'>{$item['qty']}</td><td style='text-align:right;padding:8px 0;border-bottom:1px solid #eee;'>\${$price}</td></tr>";
    }

    $notes_line = $order['notes'] ? "<p><strong>Notes:</strong> " . htmlspecialchars($order['notes']) . "</p>" : '';
    $addr2Br = !empty($order['address_line2']) ? htmlspecialchars($order['address_line2']) . "<br>" : '';

    $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;padding:20px;color:#222;">
<h2 style="color:#d4a017;">New Order Received — {$order['reference']}</h2>
<p><strong>Status:</strong> PAID ✓</p>

<h3>Customer</h3>
<p>
  Name: {$order['customer_name']}<br>
  Email: <a href="mailto:{$order['customer_email']}">{$order['customer_email']}</a><br>
  Phone: {$order['customer_phone']}
</p>

<h3>Ship To</h3>
<p>
  {$order['address_line1']}<br>
  {$addr2Br}{$order['city']}, {$order['state']} {$order['zip']}<br>
  {$order['country']}
</p>

<h3>Items</h3>
<table width="100%" cellpadding="4" cellspacing="0" border="0">
  <tr style="background:#f5f5f5;">
    <th style="text-align:left;">Product</th>
    <th style="text-align:center;">Qty</th>
    <th style="text-align:right;">Price</th>
  </tr>
  {$rows}
</table>
<p>
  Subtotal: \${$order['subtotal']}<br>
  Shipping ({$order['shipping_service']}): \${$order['shipping']}<br>
  Tax: \${$order['tax']}<br>
  <strong>Total: \${$order['total']}</strong>
</p>
{$notes_line}
<hr>
<p style="color:#888;font-size:12px;">Zoho Payment ID: {$order['zoho_payment_id']}</p>
</body></html>
HTML;

    return smtp_send(MAIL_COMPANY, 'SilexCore Team', "NEW ORDER {$order['reference']} — \${$order['total']}", $html);
}

function email_shipping(array $order, string $labelUrl = ''): bool {
    $tracking = htmlspecialchars($order['tracking_number']);
    $carrier  = htmlspecialchars($order['carrier'] ?: 'Your carrier');
    $etaTs    = !empty($order['estimated_delivery']) ? strtotime($order['estimated_delivery']) : false;
    $eta      = ($etaTs !== false) ? date('l, F j, Y', $etaTs) : 'See tracking link for updates';

    // Universal tracking URL
    $trackUrl = 'https://parcelsapp.com/en/tracking/' . urlencode($order['tracking_number']);

    $items = json_decode($order['items_json'], true) ?: [];
    $itemList = implode('', array_map(fn($i) =>
        "<li style='padding:4px 0;'>{$i['name']} — {$i['variantLabel']} &times; {$i['qty']}</li>", $items));

    $shipAddr2 = !empty($order['address_line2']) ? htmlspecialchars($order['address_line2']) . ', ' : '';

    $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f5f3ee;font-family:'Helvetica Neue',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f3ee;padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:4px;overflow:hidden;max-width:600px;">

  <tr><td style="background:#d4a017;padding:28px 40px;">
    <h1 style="margin:0;color:#fff;font-size:22px;font-weight:600;">Your order is on its way!</h1>
    <p style="margin:6px 0 0;color:rgba(255,255,255,0.85);font-size:13px;">SilexCore Technologies</p>
  </td></tr>

  <tr><td style="padding:36px 40px;">
    <p style="font-size:16px;color:#222;margin:0 0 8px;">Hi {$order['customer_name']},</p>
    <p style="font-size:14px;color:#666;margin:0 0 28px;line-height:1.6;">
      Great news — your order <strong style="color:#222;">{$order['reference']}</strong> has shipped!
    </p>

    <!-- Tracking box -->
    <div style="background:#f9f7f2;border-left:4px solid #d4a017;padding:20px 24px;margin-bottom:28px;border-radius:0 3px 3px 0;">
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.1em;color:#aaa;margin-bottom:6px;">Tracking Number</div>
      <div style="font-size:20px;font-weight:700;color:#222;font-family:monospace;margin-bottom:10px;">{$tracking}</div>
      <div style="font-size:13px;color:#666;margin-bottom:14px;">Carrier: {$carrier}</div>
      <a href="{$trackUrl}" style="display:inline-block;background:#d4a017;color:#fff;padding:10px 22px;text-decoration:none;font-size:13px;font-weight:600;border-radius:2px;">
        Track Your Package →
      </a>
    </div>

    <!-- ETA -->
    <div style="background:#e6f7ec;border-radius:3px;padding:14px 20px;margin-bottom:28px;">
      <span style="font-size:12px;text-transform:uppercase;letter-spacing:0.08em;color:#1a7a3a;font-weight:600;">Estimated Delivery: </span>
      <span style="font-size:14px;color:#1a7a3a;font-weight:700;">{$eta}</span>
    </div>

    <!-- Items -->
    <div style="margin-bottom:24px;">
      <div style="font-size:11px;text-transform:uppercase;letter-spacing:0.08em;color:#aaa;margin-bottom:10px;">Items Shipped</div>
      <ul style="margin:0;padding:0 0 0 16px;color:#444;font-size:14px;line-height:1.7;">{$itemList}</ul>
    </div>

    <!-- Ship to -->
    <div style="font-size:13px;color:#888;margin-bottom:28px;">
      <strong style="color:#444;">Shipping to:</strong><br>
      {$order['address_line1']}, {$shipAddr2}{$order['city']}, {$order['state']} {$order['zip']}
    </div>

    <p style="font-size:13px;color:#888;line-height:1.65;margin:0;">
      Questions? Contact us at
      <a href="mailto:info@silexcore.com" style="color:#d4a017;">info@silexcore.com</a> or
      <a href="tel:+17868165748" style="color:#d4a017;">+1 (786) 816-5748</a>.
    </p>
  </td></tr>

  <tr><td style="background:#f9f7f2;padding:20px 40px;border-top:1px solid #f0ede8;">
    <p style="font-size:12px;color:#aaa;margin:0;text-align:center;">
      SilexCore Technologies · Charlotte, NC 28277 · <a href="https://silexcore.com" style="color:#aaa;">silexcore.com</a>
    </p>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>
HTML;

    $attachName = 'ShippingLabel-' . $order['reference'] . '.pdf';
    return smtp_send($order['customer_email'], $order['customer_name'], "Your order {$order['reference']} has shipped!", $html, $labelUrl, $attachName);
}
