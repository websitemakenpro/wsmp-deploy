/**
 * WebsiteMakenPro · Domain availability checker
 *
 * Real-time domain availability via official RDAP registries.
 * Cloudflare Pages Function — deployed at /api/domain-check
 *
 * Usage: GET /api/domain-check?name=example&tlds=nl,com,eu,net
 * Response: { name, results: [{ tld, domain, status, expires?, registered? }] }
 *
 * Sources:
 *   .nl  → SIDN        https://rdap.sidn.nl/domain/
 *   .com → Verisign    https://rdap.verisign.com/com/v1/domain/
 *   .net → Verisign    https://rdap.verisign.com/net/v1/domain/
 *   .org → PIR         https://rdap.publicinterestregistry.org/rdap/domain/
 *   .eu  → EURid       https://rdap.eu.org/domain/  (with fallback)
 *   .io  → Identity DS https://rdap.identitydigital.services/rdap/domain/
 *   any  → RDAP.org    https://rdap.org/domain/  (bootstrap/fallback)
 */

const RDAP_ENDPOINTS = {
  'nl':   'https://rdap.sidn.nl/domain/',
  'com':  'https://rdap.verisign.com/com/v1/domain/',
  'net':  'https://rdap.verisign.com/net/v1/domain/',
  'org':  'https://rdap.publicinterestregistry.org/rdap/domain/',
  'eu':   'https://rdap.eu.org/domain/',
  'io':   'https://rdap.identitydigital.services/rdap/domain/',
  'co':   'https://rdap.identitydigital.services/rdap/domain/',
  'info': 'https://rdap.identitydigital.services/rdap/domain/',
  'me':   'https://rdap.identitydigital.services/rdap/domain/',
  'biz':  'https://rdap.identitydigital.services/rdap/domain/',
  'app':  'https://www.registry.google/rdap/domain/',
  'dev':  'https://www.registry.google/rdap/domain/',
  'shop': 'https://rdap.identitydigital.services/rdap/domain/',
};

// Universal fallback — RDAP.org aggregates all known RDAP servers
// and redirects (302) to the authoritative one. Cloudflare Workers
// follow redirects by default.
const RDAP_FALLBACK = 'https://rdap.org/domain/';

const ALLOWED_TLDS = new Set(Object.keys(RDAP_ENDPOINTS));
// Allow fallback for these additional TLDs (will use RDAP.org)
const FALLBACK_TLDS = new Set(['be', 'de', 'fr', 'es', 'it', 'pl', 'se', 'no', 'dk', 'fi', 'at', 'ch', 'uk', 'tech', 'store', 'online']);

const MAX_TLDS_PER_REQUEST = 6;
const REQUEST_TIMEOUT_MS = 8000;
const CACHE_TTL_SECONDS = 300; // 5 minutes edge cache

// ==================== MAIN HANDLER ====================

export async function onRequestGet(context) {
  const url = new URL(context.request.url);
  const nameParam = url.searchParams.get('name');
  const tldsParam = url.searchParams.get('tlds') || 'nl,com,eu,net';

  // Validate domain name (2-63 chars, letters/digits/hyphens, no leading/trailing hyphen)
  const name = (nameParam || '').trim().toLowerCase();
  if (!name || !/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/.test(name)) {
    return json(
      { error: 'Ongeldige domeinnaam. Alleen letters, cijfers en streepjes toegestaan (2–63 tekens).' },
      400
    );
  }

  // Parse and validate TLDs
  const tlds = tldsParam
    .split(',')
    .map(t => t.trim().toLowerCase())
    .filter(Boolean)
    .slice(0, MAX_TLDS_PER_REQUEST);

  if (tlds.length === 0) {
    return json({ error: 'Geen TLDs opgegeven.' }, 400);
  }

  // Check all TLDs in parallel
  const results = await Promise.all(
    tlds.map(tld => checkDomain(name, tld))
  );

  return json({
    name,
    results,
    source: 'RDAP (real-time)',
    timestamp: new Date().toISOString()
  }, 200);
}

// Handle OPTIONS for CORS preflight
export async function onRequestOptions() {
  return new Response(null, {
    status: 204,
    headers: corsHeaders()
  });
}

// ==================== DOMAIN CHECK LOGIC ====================

async function checkDomain(name, tld) {
  const domain = `${name}.${tld}`;

  // Determine endpoint
  let endpoint;
  if (RDAP_ENDPOINTS[tld]) {
    endpoint = RDAP_ENDPOINTS[tld];
  } else if (FALLBACK_TLDS.has(tld)) {
    endpoint = RDAP_FALLBACK;
  } else {
    return {
      tld,
      domain,
      status: 'unsupported',
      message: 'Deze extensie is nog niet ondersteund door de checker.'
    };
  }

  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

    const response = await fetch(endpoint + encodeURIComponent(domain), {
      method: 'GET',
      headers: {
        'Accept': 'application/rdap+json, application/json',
        'User-Agent': 'WebsiteMakenPro-DomainChecker/1.0 (+https://websitemakenpro.nl)'
      },
      redirect: 'follow',
      signal: controller.signal,
      cf: {
        // Edge cache per-TLD to reduce load on registries
        cacheTtl: CACHE_TTL_SECONDS,
        cacheEverything: true
      }
    });

    clearTimeout(timeoutId);

    // HTTP 404 = domain not found in registry = AVAILABLE
    if (response.status === 404) {
      return {
        tld,
        domain,
        status: 'available'
      };
    }

    // HTTP 200 = domain exists in registry = REGISTERED
    if (response.status === 200) {
      const data = await response.json().catch(() => null);

      const result = { tld, domain, status: 'registered' };

      // Extract events (registration/expiration dates)
      if (data && Array.isArray(data.events)) {
        const registration = data.events.find(e => e.eventAction === 'registration');
        const expiration = data.events.find(e => e.eventAction === 'expiration');
        if (registration?.eventDate) result.registered = registration.eventDate;
        if (expiration?.eventDate)   result.expires = expiration.eventDate;
      }

      return result;
    }

    // HTTP 429 = rate limited
    if (response.status === 429) {
      return {
        tld,
        domain,
        status: 'unknown',
        message: 'Registry rate limit — probeer over een minuut opnieuw.'
      };
    }

    // Other statuses = uncertain
    return {
      tld,
      domain,
      status: 'unknown',
      message: `Onverwachte status ${response.status} van registry.`
    };

  } catch (err) {
    // Network error, timeout, or unreachable
    const message = err.name === 'AbortError'
      ? 'Timeout bij registry-server.'
      : `Registry onbereikbaar: ${err.message || 'onbekende fout'}`;

    return {
      tld,
      domain,
      status: 'error',
      message
    };
  }
}

// ==================== HELPERS ====================

function corsHeaders() {
  return {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Access-Control-Max-Age': '86400'
  };
}

function json(body, status = 200) {
  return new Response(JSON.stringify(body, null, 2), {
    status,
    headers: {
      'Content-Type': 'application/json; charset=utf-8',
      'Cache-Control': status === 200
        ? `public, max-age=60, s-maxage=${CACHE_TTL_SECONDS}`
        : 'no-store',
      ...corsHeaders()
    }
  });
}
