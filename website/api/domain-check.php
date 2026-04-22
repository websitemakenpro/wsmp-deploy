<?php
/**
 * WebsiteMakenPro.nl — Domain Availability Check Proxy
 *
 * Bu dosya mijn.host API'sine guvenli bir proxy gorevi gorur.
 * API key sunucu tarafinda kalir, istemciye acilmaz.
 *
 * KURULUM:
 * 1. mijn.host panelinden API key olustur (https://mijn.host/account/api)
 * 2. Asagidaki API_KEY degerini kendi key'inle degistir
 * 3. Bu dosyayi hosting'e yukle (mijn.host PHP destekliyor)
 * 4. CORS origini kendi domainine degistir
 */

// ============================================
// YAPILANDIRMA — BU DEGERLERI DEGISTIR
// ============================================
define('MIJHOST_API_KEY', '4619a7568d96e00bc91d4764e3b2f403');  // mijn.host API key
define('ALLOWED_ORIGIN', 'https://websitemakenpro.nl');  // Kendi domainin
// Gelistirme icin: define('ALLOWED_ORIGIN', '*');

// ============================================
// CORS Headers
// ============================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Sadece GET kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ============================================
// DOMAIN PARAMETRESINI AL
// ============================================
$domain = isset($_GET['domain']) ? trim($_GET['domain']) : '';

if (empty($domain)) {
    http_response_code(400);
    echo json_encode(['error' => 'Domain parametresi gerekli', 'example' => '?domain=example.nl']);
    exit;
}

// Domain temizle
$domain = strtolower($domain);
$domain = preg_replace('/[^a-z0-9\.\-]/', '', $domain);

// Extension kontrolu
$allowed_extensions = ['.nl', '.com', '.eu', '.be', '.net', '.org', '.de'];
$has_valid_ext = false;
foreach ($allowed_extensions as $ext) {
    if (str_ends_with($domain, $ext)) {
        $has_valid_ext = true;
        break;
    }
}

if (!$has_valid_ext) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige extensie. Toegestaan: ' . implode(', ', $allowed_extensions)]);
    exit;
}

// ============================================
// MIJN.HOST API AANROEPEN
// ============================================
$api_url = 'https://mijn.host/api/v2/domains/availability/' . urlencode($domain);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'API-Key: ' . MIJHOST_API_KEY,
        'User-Agent: WebsiteMakenPro/1.0'
    ],
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// ============================================
// RESPONSE VERWERKEN
// ============================================
if ($curl_error) {
    http_response_code(502);
    echo json_encode([
        'error' => 'API verbinding mislukt',
        'domain' => $domain,
        'available' => null
    ]);
    exit;
}

$data = json_decode($response, true);

if ($http_code === 200 && isset($data['data'])) {
    // Beschikbaarheidsstatus bepalen
    $status = $data['data']['status'] ?? 'unknown';
    $is_available = (
        $status === 'available' ||
        $status === 'free' ||
        stripos($status, 'available') !== false
    );

    echo json_encode([
        'domain' => $domain,
        'available' => $is_available,
        'status' => $status,
        'register_url' => $is_available
            ? 'https://mijn.host/bestellen/domein/?domain=' . urlencode($domain)
            : null,
        'price_indication' => $is_available ? getDomainPrice($domain) : null
    ]);
} elseif ($http_code === 429) {
    http_response_code(429);
    echo json_encode([
        'error' => 'Te veel verzoeken. Probeer het later opnieuw.',
        'domain' => $domain,
        'available' => null
    ]);
} else {
    http_response_code(502);
    echo json_encode([
        'error' => 'Kan beschikbaarheid niet controleren',
        'domain' => $domain,
        'available' => null,
        'api_status' => $http_code
    ]);
}

// ============================================
// PRIJS INDICATIE PER EXTENSIE
// ============================================
function getDomainPrice($domain) {
    $prices = [
        '.nl'  => ['register' => '€5,99/jaar',  'renew' => '€8,99/jaar'],
        '.com' => ['register' => '€10,99/jaar', 'renew' => '€12,99/jaar'],
        '.eu'  => ['register' => '€7,99/jaar',  'renew' => '€9,99/jaar'],
        '.be'  => ['register' => '€8,99/jaar',  'renew' => '€10,99/jaar'],
        '.net' => ['register' => '€12,99/jaar', 'renew' => '€14,99/jaar'],
        '.org' => ['register' => '€12,99/jaar', 'renew' => '€14,99/jaar'],
        '.de'  => ['register' => '€8,99/jaar',  'renew' => '€10,99/jaar'],
    ];

    foreach ($prices as $ext => $price) {
        if (str_ends_with($domain, $ext)) {
            return $price;
        }
    }
    return ['register' => '€12,99/jaar', 'renew' => '€14,99/jaar'];
}
?>
