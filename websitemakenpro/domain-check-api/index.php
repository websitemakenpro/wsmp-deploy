<?php
/**
 * WebsiteMakenPro — Domain Availability Checker
 * Powered by mijn.host Reseller API (reliable, no RDAP issues)
 *
 * GET /api/domain-check?name=example&tlds=nl,com,eu,net
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: public, max-age=60');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// mijn.host reseller API key
define('MIJN_HOST_API_KEY', '4619a7568d96e00bc91d4764e3b2f403');

// ---- Input validation ----
$name = isset($_GET['name']) ? strtolower(trim($_GET['name'])) : '';
$tldsParam = isset($_GET['tlds']) ? $_GET['tlds'] : 'nl,com,eu,net';

if (!$name || !preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/', $name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ongeldige domeinnaam.']);
    exit;
}

$tlds = array_slice(
    array_filter(array_map('trim', explode(',', $tldsParam))),
    0, 6
);

if (empty($tlds)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen TLDs opgegeven.']);
    exit;
}

// ---- Domain checker via mijn.host API ----
function checkDomain($name, $tld) {
    $domain = $name . '.' . $tld;
    $url    = 'https://mijn.host/api/v2/domains/availability/' . rawurlencode($domain);

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'API-Key: ' . MIJN_HOST_API_KEY,
                'Accept: application/json',
                'User-Agent: WebsiteMakenPro-DomainChecker/1.0 (+https://websitemakenpro.nl)',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr   = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['tld' => $tld, 'domain' => $domain, 'status' => 'error', 'message' => $curlErr];
        }
    } else {
        // Fallback: file_get_contents
        $context = stream_context_create(['http' => [
            'method'  => 'GET',
            'timeout' => 8,
            'header'  => "API-Key: " . MIJN_HOST_API_KEY . "\r\nAccept: application/json\r\n",
        ]]);
        $response = @file_get_contents($url, false, $context);
        $httpCode = 0;
        if (isset($http_response_header[0]) && preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m)) {
            $httpCode = (int) $m[1];
        }
        if ($response === false) {
            return ['tld' => $tld, 'domain' => $domain, 'status' => 'error', 'message' => 'API onbereikbaar'];
        }
    }

    if ($httpCode === 200) {
        $data   = @json_decode($response, true);
        $status = $data['data']['status'] ?? 'unknown';

        if ($status === 'available') {
            return ['tld' => $tld, 'domain' => $domain, 'status' => 'available'];
        }
        if ($status === 'taken') {
            return ['tld' => $tld, 'domain' => $domain, 'status' => 'registered'];
        }
    }

    // mijn.host doesn't support this TLD → fallback to RDAP
    return checkDomainRdap($name, $tld, $domain);
}

// ---- RDAP fallback for unsupported TLDs ----
function checkDomainRdap($name, $tld, $domain) {
    $endpoints = [
        'nl'  => 'https://rdap.sidn.nl/domain/',
        'com' => 'https://rdap.verisign.com/com/v1/domain/',
        'net' => 'https://rdap.verisign.com/net/v1/domain/',
        'org' => 'https://rdap.publicinterestregistry.org/rdap/domain/',
        'eu'  => 'https://rdap.eurid.eu/rdap/domain/',
        'io'  => 'https://rdap.identitydigital.services/rdap/domain/',
    ];

    $endpoint = $endpoints[$tld] ?? 'https://rdap.org/domain/';
    $url      = $endpoint . rawurlencode($domain);

    if (!function_exists('curl_init')) {
        return ['tld' => $tld, 'domain' => $domain, 'status' => 'unknown', 'message' => 'cURL niet beschikbaar'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/rdap+json, application/json',
            'User-Agent: WebsiteMakenPro-DomainChecker/1.0',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 404) {
        return ['tld' => $tld, 'domain' => $domain, 'status' => 'available'];
    }

    if ($httpCode === 200) {
        $data   = @json_decode($response, true);
        $result = ['tld' => $tld, 'domain' => $domain, 'status' => 'registered'];
        if ($data && isset($data['events'])) {
            foreach ($data['events'] as $ev) {
                if (($ev['eventAction'] ?? '') === 'expiration') {
                    $result['expires'] = $ev['eventDate'] ?? null;
                }
            }
        }
        return $result;
    }

    return ['tld' => $tld, 'domain' => $domain, 'status' => 'unknown'];
}

// ---- Run checks ----
$results = [];
foreach ($tlds as $tld) {
    $results[] = checkDomain($name, $tld);
}

echo json_encode([
    'name'      => $name,
    'results'   => $results,
    'source'    => 'mijn.host API',
    'timestamp' => gmdate('c'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
