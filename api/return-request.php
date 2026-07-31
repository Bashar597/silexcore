<?php
require __DIR__ . '/config.php';
require_post();

$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$ref     = trim($_POST['ref']     ?? '');
$product = trim($_POST['product'] ?? '');
$reason  = trim($_POST['reason']  ?? '');
$details = trim($_POST['details'] ?? '');

if (!$name || !$email || !$product || !$reason) {
    json_out(['error' => 'Missing required fields.'], 400);
}

$subject = "Return Request — $name — $product";
$body = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;padding:20px;color:#222;'>
<h2 style='color:#d4a017;'>Return Request Received</h2>
<table style='width:100%;border-collapse:collapse;font-size:14px;'>
<tr><td style='padding:8px 0;border-bottom:1px solid #eee;width:160px;color:#888;'>Customer</td><td style='padding:8px 0;border-bottom:1px solid #eee;'>" . htmlspecialchars($name) . "</td></tr>
<tr><td style='padding:8px 0;border-bottom:1px solid #eee;color:#888;'>Email</td><td style='padding:8px 0;border-bottom:1px solid #eee;'>" . htmlspecialchars($email) . "</td></tr>
<tr><td style='padding:8px 0;border-bottom:1px solid #eee;color:#888;'>Order Ref</td><td style='padding:8px 0;border-bottom:1px solid #eee;font-family:monospace;'>" . htmlspecialchars($ref ?: '—') . "</td></tr>
<tr><td style='padding:8px 0;border-bottom:1px solid #eee;color:#888;'>Product</td><td style='padding:8px 0;border-bottom:1px solid #eee;'>" . htmlspecialchars($product) . "</td></tr>
<tr><td style='padding:8px 0;border-bottom:1px solid #eee;color:#888;'>Reason</td><td style='padding:8px 0;border-bottom:1px solid #eee;'>" . htmlspecialchars($reason) . "</td></tr>
" . ($details ? "<tr><td style='padding:8px 0;color:#888;vertical-align:top;'>Details</td><td style='padding:8px 0;'>" . nl2br(htmlspecialchars($details)) . "</td></tr>" : '') . "
</table>
<hr style='border:none;border-top:1px solid #eee;margin:20px 0;'>
<p style='font-size:12px;color:#888;'>Customer confirmed product is unopened and agreed to pay return shipping.</p>
</body></html>";

$headers  = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

// Notify company
@mail(MAIL_COMPANY, $subject, $body, $headers, '-f' . MAIL_FROM);

// Auto-reply to customer
$autoReply = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;padding:20px;color:#222;'>
<h2 style='color:#d4a017;'>We received your return request</h2>
<p>Hi " . htmlspecialchars(explode(' ', $name)[0]) . ",</p>
<p>We've received your return request for <strong>" . htmlspecialchars($product) . "</strong> and will send you return instructions within <strong>1 business day</strong>.</p>
<p style='color:#888;font-size:13px;'>Please do not ship the product until you receive instructions from us with the return address.</p>
<p style='font-size:13px;'>Questions? Email us at <a href='mailto:info@silexcore.com' style='color:#d4a017;'>info@silexcore.com</a> or call <a href='tel:+17868165748' style='color:#d4a017;'>+1 (786) 816-5748</a>.</p>
<hr style='border:none;border-top:1px solid #eee;margin:16px 0;'>
<p style='font-size:12px;color:#aaa;'>SilexCore Technologies · Charlotte, NC 28277</p>
</body></html>";

@mail($email, 'Return Request Received — SilexCore', $autoReply, $headers, '-f' . MAIL_FROM);

json_out(['ok' => true]);
