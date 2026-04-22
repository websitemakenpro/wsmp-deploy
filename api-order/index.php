<?php
/**
 * WebsiteMakenPro — Order API
 * POST /api/order/
 * Uses authenticated SMTP for reliable delivery
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ===== SMTP CONFIG — Mailjet relay (reliable delivery to Gmail/Google Workspace) =====
define('SMTP_HOST', 'in-v3.mailjet.com');
define('SMTP_PORT', 587); // STARTTLS
define('SMTP_USER', 'ece21cd984f2eb8a423519a29ea3f943'); // Mailjet API Key
define('SMTP_PASS', '0bdf0c14004a2d6156fcca6768f4ecfd'); // Mailjet Secret Key
define('FROM_NAME', 'WebsiteMakenPro');
define('FROM_EMAIL', 'info@websitemakenpro.nl');
define('ADMIN_EMAIL', 'info@websitemakenpro.nl');

// ===== SMTP SEND FUNCTION =====
function smtpSend($to, $subject, $htmlBody, $bcc = '') {
    // Mailjet uses STARTTLS on port 587
    $context = stream_context_create(['ssl' => [
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
    ]]);

    $smtp = @stream_socket_client(
        'tcp://' . SMTP_HOST . ':' . SMTP_PORT,
        $errno, $errstr, 15
    );

    if (!$smtp) {
        error_log("SMTP connect failed: $errstr ($errno)");
        return false;
    }

    $read = function() use ($smtp) { return fgets($smtp, 512); };
    $send = function($cmd) use ($smtp) { fwrite($smtp, $cmd . "\r\n"); };

    $read(); // 220 greeting

    $send('EHLO websitemakenpro.nl');
    while (($line = $read()) !== false) {
        if (substr($line, 3, 1) === ' ') break;
    }

    // STARTTLS upgrade
    $send('STARTTLS');
    $tlsResp = $read();
    if (strpos($tlsResp, '220') === false) {
        error_log("STARTTLS failed: $tlsResp");
        fclose($smtp);
        return false;
    }
    stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    $send('EHLO websitemakenpro.nl');
    while (($line = $read()) !== false) {
        if (substr($line, 3, 1) === ' ') break;
    }

    $send('AUTH LOGIN');
    $read(); // 334
    $send(base64_encode(SMTP_USER));
    $read(); // 334
    $send(base64_encode(SMTP_PASS));
    $authResp = $read(); // 235 = ok, 535 = fail

    if (strpos($authResp, '235') === false) {
        error_log("SMTP auth failed: $authResp");
        fclose($smtp);
        return false;
    }

    $send('MAIL FROM:<' . FROM_EMAIL . '>');
    $read();

    // RCPT TO - support multiple recipients
    $recipients = array_filter(array_map('trim', explode(',', $to)));
    foreach ($recipients as $r) {
        $send("RCPT TO:<$r>");
        $read();
    }
    if ($bcc) {
        $send("RCPT TO:<$bcc>");
        $read();
    }

    $send('DATA');
    $read(); // 354

    // Build multipart message (text/plain + text/html improves deliverability)
    $date      = date('r');
    $msgId     = '<' . uniqid('wmp', true) . '@websitemakenpro.nl>';
    $boundary  = 'WMP_' . md5(uniqid());

    $plainText = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", preg_replace('/<\/p>/i', "\n\n", $htmlBody)));
    $plainText = preg_replace('/[ \t]+/', ' ', $plainText);
    $plainText = preg_replace('/\n{3,}/', "\n\n", trim($plainText));

    $msg =
        "Date: $date\r\n" .
        "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n" .
        "To: $to\r\n" .
        ($bcc ? "Bcc: $bcc\r\n" : '') .
        "Message-ID: $msgId\r\n" .
        "Subject: $subject\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n" .
        "\r\n" .
        "--$boundary\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n" .
        "Content-Transfer-Encoding: base64\r\n" .
        "\r\n" .
        chunk_split(base64_encode($plainText), 76, "\r\n") .
        "--$boundary\r\n" .
        "Content-Type: text/html; charset=UTF-8\r\n" .
        "Content-Transfer-Encoding: base64\r\n" .
        "\r\n" .
        chunk_split(base64_encode($htmlBody), 76, "\r\n") .
        "--$boundary--\r\n" .
        "\r\n.\r\n";

    fwrite($smtp, $msg);
    $dataResp = $read(); // 250 = ok

    $send('QUIT');
    fclose($smtp);

    return strpos($dataResp, '250') !== false;
}

// ===== FALLBACK: PHP mail() =====
function fallbackMail($to, $subject, $htmlBody, $bcc = '') {
    $headers = implode("\r\n", array_filter([
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
        'Return-Path: ' . FROM_EMAIL,
        $bcc ? 'Bcc: ' . $bcc : '',
        'X-Mailer: PHP/' . phpversion(),
    ]));
    return mail($to, $subject, $htmlBody, $headers, '-f ' . FROM_EMAIL);
}

// ===== WRAPPER =====
function sendEmail($to, $subject, $htmlBody, $bcc = '') {
    $ok = smtpSend($to, $subject, $htmlBody, $bcc);
    if (!$ok) {
        error_log("SMTP failed for $to, trying mail() fallback");
        $ok = fallbackMail($to, $subject, $htmlBody, $bcc);
    }
    return $ok;
}

// ===== INPUT =====
$raw  = file_get_contents('php://input');
$data = @json_decode($raw, true);
if (!$data) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Ongeldige invoer.']); exit; }

function s($v) { return htmlspecialchars(trim((string)($v ?? '')), ENT_QUOTES, 'UTF-8'); }

$required = ['pkg','fname','lname','email','phone','street','postcode','city'];
foreach ($required as $f) {
    if (empty($data[$f])) { http_response_code(400); echo json_encode(['success'=>false,'error'=>"Veld '$f' is verplicht."]); exit; }
}
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>'Ongeldig e-mailadres.']); exit;
}

// Extract
$pkg         = s($data['pkg']);
$pkgLabel    = s($data['pkgLabel'] ?? $pkg);
$pkgPrice    = (int)($data['pkgPrice'] ?? 0);
$domain      = s($data['domain'] ?? 'Geen domein');
$domainIsNew = (bool)($data['domainIsNew'] ?? false);
$domainPrice = (float)($data['domainPrice'] ?? 0);
$hosting     = s($data['hosting'] ?? '');
$hostingLabel= s($data['hostingLabel'] ?? '');
$hostingPrice= (float)($data['hostingPrice'] ?? 0);
$fname       = s($data['fname']);
$lname       = s($data['lname']);
$company     = s($data['company'] ?? '');
$email       = trim($data['email']);
$phone       = s($data['phone']);
$street      = s($data['street']);
$postcode    = s($data['postcode']);
$city        = s($data['city']);
$kvk         = s($data['kvk'] ?? '');
$notes       = s($data['notes'] ?? '');
$fullName    = "$fname $lname";
$totalPrice  = $pkgPrice + ($domainIsNew ? $domainPrice : 0);
$totalStr    = $pkgPrice > 0 ? 'EUR ' . number_format($totalPrice, 2, ',', '.') : 'Op maat';
$totalHTML   = $pkgPrice > 0 ? '&euro;' . number_format($totalPrice, 2, ',', '.') : 'Op maat';
$orderRef    = 'WMP-' . strtoupper(substr(md5(uniqid()), 0, 6));
$orderDate   = date('d-m-Y H:i');

// Package features
$pkgFeatures = [
    'starter'  => ['1-page long-scroll website', 'Custom design op basis van huisstijl', 'Mobile-first & responsive', 'CMS + contactformulier', 'Basis SEO + Google Analytics', 'Live binnen 10 werkdagen'],
    'pro'      => ['Tot 7 pagina\'s, volledig custom design', 'AI-chatbot ingebouwd & getraind', 'SEO-optimalisatie per pagina', 'Google Analytics + Search Console', '2 revisierondes', 'Live binnen 3 weken'],
    'business' => ['Tot 15 pagina\'s, maatwerk design', 'AI-chatbot + WhatsApp-automatisering', 'E-mail automatisering', 'SEO-strategie + 3 landingspagina\'s', '3 maanden priority support', 'Live binnen 6 weken'],
    'maatwerk' => ['Vrijblijvend adviesgesprek', 'Offerte zelf samen te stellen', 'Betaling in termijnen mogelijk', 'Persoonlijke begeleiding'],
];
$feats = $pkgFeatures[$pkg] ?? [];
$featHtml = implode('', array_map(fn($f) =>
    "<li style='padding:5px 0;border-bottom:1px solid #f5f5f0;font-size:14px;color:#3A3A44'>
     <span style='color:#0051FF;margin-right:8px;font-weight:700'>&#10003;</span>$f</li>", $feats));

// Encoded subjects (no raw unicode in headers)
$adminSubject    = '=?UTF-8?B?' . base64_encode("Nieuwe bestelling $orderRef — $pkgLabel") . '?=';
$customerSubject = '=?UTF-8?B?' . base64_encode("Bevestiging bestelling $orderRef — WebsiteMakenPro") . '?=';

// ===== ADMIN EMAIL =====
$adminBody = "<!DOCTYPE html><html lang='nl'><head><meta charset='UTF-8'><style>
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f0f0eb;margin:0;padding:20px}
.card{background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e8e8e0;max-width:600px;margin:0 auto}
.top{background:#0051FF;padding:24px 32px;color:#fff}
.top h1{font-size:20px;margin:0 0 4px;font-weight:600}
.ref{font-family:monospace;background:rgba(255,255,255,.2);padding:3px 10px;border-radius:6px;font-size:13px}
.body{padding:28px 32px}
.label{margin-bottom:12px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.1em;color:#8E8E98;padding-bottom:8px;border-bottom:2px solid #f0f0eb}
table{width:100%;border-collapse:collapse}
td{padding:9px 0;border-bottom:1px solid #f5f5f0;font-size:14px;color:#0F0F14}
.lbl{color:#54545F;width:42%}
.total td{font-weight:700;font-size:17px;border-top:2px solid #e0e0d8;border-bottom:none;padding-top:14px;color:#0051FF}
.action{background:#fffbeb;border:1px solid #fcd;border-radius:10px;padding:16px;margin-top:20px;font-size:14px;color:#5d4037}
.footer{background:#f8f8f4;padding:14px 32px;text-align:center;font-size:12px;color:#8E8E98;border-top:1px solid #eee}
</style></head><body>
<div class='card'>
<div class='top'><h1>Nieuwe bestelling</h1><span class='ref'>$orderRef</span> &nbsp; <span style='font-size:13px;opacity:.8'>$orderDate</span></div>
<div class='body'>
<div class='label'>Besteldetails</div>
<table>
<tr><td class='lbl'>Pakket</td><td><strong>$pkgLabel</strong></td></tr>
<tr><td class='lbl'>Pakketprijs</td><td>" . ($pkgPrice ? '&euro;' . number_format($pkgPrice,2,',','.') : 'Op maat') . "</td></tr>
<tr><td class='lbl'>Domeinnaam</td><td><strong>$domain</strong> — " . ($domainIsNew ? 'Nieuw registreren' : 'Bestaand koppelen') . "</td></tr>
" . ($domainIsNew && $domainPrice > 0 ? "<tr><td class='lbl'>Domeinprijs (1e j.)</td><td>&euro;" . number_format($domainPrice,2,',','.') . "</td></tr>" : "") . "
" . ($hosting ? "<tr><td class='lbl'>Hosting</td><td><strong>$hostingLabel</strong>" . ($hostingPrice > 0 ? " · &euro;" . number_format($hostingPrice,2,',','.') . "/maand" : " (eigen hosting)") . "</td></tr>" : "") . "
<tr class='total'><td>Totaal eenmalig</td><td>$totalHTML</td></tr>
</table>
<div style='margin-top:20px' class='label'>Klantgegevens</div>
<table>
<tr><td class='lbl'>Naam</td><td>$fullName</td></tr>
" . ($company ? "<tr><td class='lbl'>Bedrijf</td><td>$company</td></tr>" : "") . "
<tr><td class='lbl'>E-mail</td><td><a href='mailto:$email'>$email</a></td></tr>
<tr><td class='lbl'>Telefoon</td><td><a href='tel:$phone'>$phone</a></td></tr>
<tr><td class='lbl'>Adres</td><td>$street, $postcode $city</td></tr>
" . ($kvk ? "<tr><td class='lbl'>KVK</td><td>$kvk</td></tr>" : "") . "
" . ($notes ? "<tr><td class='lbl'>Opmerkingen</td><td>$notes</td></tr>" : "") . "
</table>
<div class='action'><strong>Actie:</strong> Neem contact op + stuur factuur. Na betaling: " . ($domainIsNew ? "domein registreren via mijn.host (<strong>$domain</strong>)." : "bestaand domein koppelen.") . "</div>
</div>
<div class='footer'>WebsiteMakenPro · info@websitemakenpro.nl · +31 6 24741597</div>
</div></body></html>";

// ===== CUSTOMER EMAIL =====
$customerBody = "<!DOCTYPE html><html lang='nl'><head><meta charset='UTF-8'><style>
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f0f0eb;margin:0;padding:20px}
.card{background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e8e8e0;max-width:600px;margin:0 auto}
.hero{background:linear-gradient(135deg,#0051FF 0%,#003ACC 100%);padding:36px 32px;text-align:center;color:#fff}
.logo{font-size:22px;font-weight:700;letter-spacing:-.02em;margin-bottom:16px}
.logo span{font-style:italic;opacity:.85}
.hero h1{font-size:26px;font-weight:300;margin:0 0 12px;line-height:1.2}
.ref-badge{display:inline-block;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);padding:6px 16px;border-radius:100px;font-family:monospace;font-size:14px;font-weight:600;letter-spacing:.05em}
.body{padding:28px 32px}
.intro{font-size:16px;color:#3A3A44;line-height:1.6;margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid #f0f0eb}
.section-title{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.12em;color:#8E8E98;margin:20px 0 10px}
table{width:100%;border-collapse:collapse}
td{padding:10px 0;border-bottom:1px solid #f5f5f0;font-size:14px;color:#0F0F14;vertical-align:top}
.lbl{color:#54545F;width:45%;font-size:13px}
.total-row td{font-weight:700;font-size:18px;border-top:2px solid #0051FF;border-bottom:none;padding-top:14px;color:#0051FF}
ul.feats{list-style:none;padding:0;margin:0}
.pay{background:#f0f5ff;border:1px solid #c7d9ff;border-radius:14px;padding:22px 24px;margin:20px 0}
.pay h3{font-size:15px;font-weight:700;color:#0051FF;margin:0 0 10px}
.iban{background:#fff;border:1.5px solid #c7d9ff;border-radius:10px;padding:12px 16px;margin:10px 0;font-family:monospace;font-size:15px;font-weight:700;color:#0F0F14;letter-spacing:.03em}
.steps{background:#f8f8f4;border-radius:14px;padding:22px 24px;margin:20px 0}
.steps h3{font-size:14px;font-weight:700;color:#0F0F14;margin:0 0 14px}
.step{display:flex;gap:12px;align-items:flex-start;padding:10px 0;border-bottom:1px solid #eee}
.step:last-child{border:none;padding-bottom:0}
.snum{width:28px;height:28px;min-width:28px;background:#0051FF;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}
.stxt{font-size:14px;color:#3A3A44;line-height:1.5}
.stxt strong{color:#0F0F14;display:block;margin-bottom:2px}
.cta{background:#0F0F14;border-radius:14px;padding:18px 24px;margin:20px 0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.cta p{color:#fff;font-size:14px;margin:0}
.cta a{display:inline-block;background:#25D366;color:#fff;padding:9px 18px;border-radius:100px;font-size:13px;font-weight:600;text-decoration:none}
.footer{text-align:center;padding:18px 24px;background:#f8f8f4;border-top:1px solid #eee;font-size:12px;color:#8E8E98;line-height:1.7}
.footer a{color:#0051FF;text-decoration:none}
.green{color:#12885D;font-weight:600}
</style></head><body>
<div class='card'>
<div class='hero'>
  <div class='logo'>WebsiteMaken<span>pro</span></div>
  <h1>Bedankt voor je bestelling,<br>$fname!</h1>
  <div class='ref-badge'>$orderRef</div>
</div>
<div class='body'>
  <p class='intro'>Je aanvraag is in goede orde ontvangen. We nemen binnen <strong>1 werkdag</strong> persoonlijk contact met je op. Hieronder alle details van je bestelling.</p>

  <div class='section-title'>Jouw bestelling</div>
  <table>
    <tr><td class='lbl'>Pakket</td><td><strong>$pkgLabel</strong></td></tr>
    <tr><td class='lbl'>Pakketprijs</td><td>" . ($pkgPrice ? '&euro;' . number_format($pkgPrice,2,',','.') : 'Op maat') . "</td></tr>
    <tr><td class='lbl'>Domeinnaam</td><td><strong>$domain</strong><br><small style='color:#12885D'>" . ($domainIsNew ? 'Nieuw te registreren, inclusief 1e jaar' : 'Bestaand domein wordt gekoppeld') . "</small></td></tr>
    " . ($domainIsNew && $domainPrice > 0 ? "<tr><td class='lbl'>Domeinregistratie (1j.)</td><td>&euro;" . number_format($domainPrice,2,',','.') . "</td></tr>" : "") . "
    " . ($hosting && $hostingLabel ? "<tr><td class='lbl'>Hosting</td><td><strong>$hostingLabel</strong>" . ($hostingPrice > 0 ? "<br><small style='color:#54545F'>&euro;" . number_format($hostingPrice,2,',','.') . "/maand (na livegang)</small>" : "<br><small style='color:#12885D'>Eigen hosting</small>") . "</td></tr>" : "") . "
    <tr><td class='lbl'>SSL certificaat</td><td class='green'>Inbegrepen &#10003;</td></tr>
    <tr class='total-row'><td>Totaal eenmalig</td><td>$totalHTML</td></tr>
  </table>

  " . (count($feats) > 0 ? "<div class='section-title'>Wat is inbegrepen</div><ul class='feats'>$featHtml</ul>" : "") . "

  " . ($pkgPrice > 0 ? "
  <div class='pay'>
    <h3>&#128179; Betaalgegevens</h3>
    <p style='font-size:13px;color:#3A3A44;margin:0 0 10px'>Wil je alvast een aanbetaling doen? Dan kun je het bedrag overmaken naar:</p>
    <div class='iban'>NL91 ABNA 0417 1643 00</div>
    <table style='font-size:13px;margin-top:8px'>
      <tr><td class='lbl'>T.n.v.</td><td><strong>WebsiteMakenPro</strong></td></tr>
      <tr><td class='lbl'>Omschrijving</td><td><strong>$orderRef</strong></td></tr>
      <tr><td class='lbl'>Bedrag</td><td><strong>$totalHTML</strong></td></tr>
    </table>
    <p style='font-size:12px;color:#8E8E98;margin:12px 0 0'>&#9432;&nbsp;Je ontvangt ook een officiële factuur zodra we telefonisch contact hebben gehad. Betalen is pas vereist na akkoord op de offerte.</p>
  </div>
  " : "") . "

  <div class='steps'>
    <h3>Wat gebeurt er nu?</h3>
    <div class='step'><div class='snum'>1</div><div class='stxt'><strong>Persoonlijk contact &mdash; binnen 1 werkdag</strong>We nemen telefonisch of via WhatsApp contact met je op om alles door te spreken.</div></div>
    <div class='step'><div class='snum'>2</div><div class='stxt'><strong>Offerte &amp; factuur</strong>Je ontvangt een officiële offerte en factuur per e-mail. Geen verrassingen.</div></div>
    <div class='step'><div class='snum'>3</div><div class='stxt'><strong>We gaan bouwen</strong>Na akkoord starten we direct. Je ontvangt tussentijds updates.</div></div>
    <div class='step'><div class='snum'>4</div><div class='stxt'><strong>Live!</strong>Na jouw goedkeuring gaat de website online. Inclusief instructievideo.</div></div>
  </div>

  <div class='cta'>
    <p>Neem gerust contact op als je vragen hebt.</p>
    <a href='https://wa.me/31624741597?text=Hoi,%20mijn%20bestelnummer%20is%20$orderRef'>WhatsApp ons</a>
  </div>
</div>
<div class='footer'>
  WebsiteMakenPro &middot; info@websitemakenpro.nl &middot; +31 6 24741597<br>
  <a href='https://websitemakenpro.nl'>websitemakenpro.nl</a> &middot; <a href='https://websitemakenpro.nl/voorwaarden.html'>Algemene voorwaarden</a>
</div>
</div></body></html>";

// ===== SEND =====
$adminOk    = sendEmail(ADMIN_EMAIL, $adminSubject, $adminBody);
// Customer email + BCC to admin so we always get a copy
$customerOk = sendEmail($email, $customerSubject, $customerBody, ADMIN_EMAIL);

if (!$adminOk && !$customerOk) {
    error_log("ORDER $orderRef: all mail delivery failed");
}

// ===== LOG =====
$logDir = __DIR__ . '/../../orders';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
@file_put_contents($logDir . '/orders.log', json_encode([
    'ref'            => $orderRef,
    'date'           => $orderDate,
    'pkg'            => $pkgLabel,
    'domain'         => $domain,
    'hosting'        => $hostingLabel,
    'name'           => $fullName,
    'email'          => $email,
    'phone'          => $phone,
    'address'        => "$street, $postcode $city",
    'total'          => $totalStr,
    'adminSmtp'      => $adminOk,
    'customerSmtp'   => $customerOk,
], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

echo json_encode([
    'success'  => true,
    'orderRef' => $orderRef,
    'message'  => "Bestelling $orderRef ontvangen!",
]);
