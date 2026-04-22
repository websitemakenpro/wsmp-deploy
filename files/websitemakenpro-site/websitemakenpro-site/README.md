# websitemakenpro.nl

Productie-website van WebsiteMakenPro — vaste prijzen, AI ingebakken, voor heel Nederland.

Phase 2 brand naast alfareclame.nl. Geen cross-linking in de eerste 6 maanden.

---

## Structuur

```
websitemakenpro-site/
├── index.html                      Enhanced homepage (100KB)
├── privacy.html                    AVG-compliant privacybeleid
├── voorwaarden.html                Algemene voorwaarden NL
├── sitemap.xml                     7 URLs
├── robots.txt                      SIDN-vriendelijke crawl rules
├── _headers                        Cloudflare headers (cache, security)
├── _redirects                      URL redirects
├── manifest.json                   PWA manifest
├── humans.txt, llms.txt            Extra metadata
├── .well-known/
│   └── security.txt                RFC 9116 security contact
│
├── blog/
│   ├── index.html                                 Blog landingspagina
│   ├── website-laten-maken-prijs-gids-2026.html   2400 woorden · Prijsgids
│   ├── ai-op-je-website-5-features.html           1800 woorden · AI-features
│   └── wordpress-vs-custom-website.html           2100 woorden · Vergelijking
│
├── functions/
│   └── api/
│       └── domain-check.js         Cloudflare Pages Function · RDAP
│
└── public/images/
    ├── favicon.svg                 Primary favicon
    ├── favicon-32.png
    ├── favicon-192.png
    ├── favicon-512.png
    ├── favicon-512-maskable.png    PWA maskable
    └── og-image.jpg                Social sharing
```

---

## Domein-checker — hoe het werkt

`/functions/api/domain-check.js` is een Cloudflare Pages Function die op `/api/domain-check` beschikbaar komt zodra de site bij Cloudflare Pages gedeployed is.

**Request:**
```
GET /api/domain-check?name=jouwbedrijf&tlds=nl,com,eu,net
```

**Response:**
```json
{
  "name": "jouwbedrijf",
  "results": [
    { "tld": "nl", "domain": "jouwbedrijf.nl", "status": "available" },
    { "tld": "com", "domain": "jouwbedrijf.com", "status": "registered", "expires": "2027-03-15T00:00:00Z" }
  ],
  "source": "RDAP (real-time)",
  "timestamp": "2026-04-21T14:30:00.000Z"
}
```

**Registries gebruikt:**
| TLD | RDAP endpoint |
|-----|---------------|
| .nl | `https://rdap.sidn.nl/domain/` (SIDN) |
| .com / .net | `https://rdap.verisign.com/{tld}/v1/domain/` (Verisign) |
| .org | `https://rdap.publicinterestregistry.org/rdap/domain/` (PIR) |
| .eu | `https://rdap.eu.org/domain/` |
| .io / .co / .info / .me / .biz / .shop | Identity Digital |
| .app / .dev | Google Registry |
| Overige | `https://rdap.org/domain/` (bootstrap fallback) |

**Status codes:**
- `available` — 404 van registry = beschikbaar
- `registered` — 200 van registry = al geregistreerd (met `expires` indien beschikbaar)
- `unsupported` — TLD niet in de mapping
- `unknown` — rate limit of onverwachte response
- `error` — registry onbereikbaar of timeout

**Caching:** 5 minuten edge cache per domain/TLD combinatie om load op registries te beperken.

**Timeout:** 8 seconden per registry-call met AbortController.

---

## Deploy (Cloudflare Pages)

1. Push deze folder naar een GitHub repo
2. Cloudflare Pages → "Create project" → verbind met repo
3. Build settings: geen (statische site)
4. Root directory: `/` (of waar `index.html` staat)
5. De `functions/` map wordt automatisch gedetecteerd en gedeployed als Pages Functions

**Custom domain:** `websitemakenpro.nl` → Cloudflare Pages DNS records toevoegen.

**Geen build-stap nodig** — dit is een pure statische site met één server-side endpoint (`/api/domain-check`).

---

## SEO

- **Schema.org** op elke pagina: Organization, ProfessionalService, WebSite, FAQPage, BreadcrumbList, Article (blog), AggregateRating 4.9/18 reviews, Offer catalog
- **Open Graph + Twitter Card** op elke pagina
- **Canonical URLs** op elke pagina
- **hreflang** `nl-NL` als primair
- **geo.region** `NL` en `geo.placename` `Nederland` voor lokale signals
- **Sitemap** met 7 URLs
- **robots.txt** geoptimaliseerd

---

## Design system

- **Fonts:** Fraunces (display, variable opsz/SOFT/WONK), DM Sans (body), JetBrains Mono (labels/meta)
- **Kleuren:** off-white `#FAFAF7`, ink `#0F0F14`, electric blue `#0051FF`, coral `#FF5722`
- **Signature:** subtiele grain-textuur (SVG noise 0.04 opacity), § markers, mono caps voor meta, editorial typografie

Bewust verschillend van alfareclame.nl (dat gebruikt Inter + Crimson Pro + yellow #FFE500).

---

## Phone & contact

- **Phone:** +31 6 24741597
- **WhatsApp:** https://wa.me/31624741597
- **E-mail:** info@websitemakenpro.nl
- **KvK:** 88606902
- **Active sinds:** 2012

---

## Roadmap

- ~~Phase 1: core site + blog + domain checker~~ ✓
- Phase 2 (post-launch): 5 extra landing pages per branche (horeca, juridisch, etc.)
- Phase 3 (3 mnd post-launch): case study pagina's met echte klant-data (met toestemming)
- Phase 4 (6 mnd): extra blog-content (1 artikel/2 weken consistent)

Geen cross-linking met alfareclame.nl in de eerste 6 maanden — Google footprint hygiene.
