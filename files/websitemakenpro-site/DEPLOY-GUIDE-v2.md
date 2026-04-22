# WebsiteMakenPro — Deploy Guide v2 (22 april 2026)

## 📦 Wat zit er in deze zip

Deze zip bevat **alle fixes en uitbreidingen** sinds de eerste deploy:

### A. Kritieke fixes (uit vorige sessie — nog niet live!)
1. Homepage H1 versterkt voor SEO
2. Portfolio voorbeeld-labels (legal compliance)
3. Fake testimonials verwijderd, vervangen door "4 Beloftes"
4. Fake AggregateRating uit schema.org
5. Nederlandse hoofdletter-fixes (56 instances)
6. CTA routing opgeschoond (41 links → `/bestellen`)

### B. NIEUW in deze sessie — 7 nieuwe blog artikelen voor SEO
| Artikel | Keyword | Volume | Rekabet |
|---------|---------|--------|---------|
| eu-ai-act-mkb.html | eu ai act mkb | ~100/mnd | **Düşük** ← kolay ranking |
| website-snelheid-verbeteren.html | website snelheid verbeteren | ~500/mnd | Orta |
| core-web-vitals-uitleg.html | core web vitals | ~600/mnd | Orta |
| website-onderhoud-checklist.html | website onderhoud | ~400/mnd | Orta |
| avg-compliance-website.html | avg compliance website | ~300/mnd | Orta |
| chatgpt-vs-claude-websites.html | chatgpt vs claude | ~800/mnd | Orta |
| landingspagina-vs-website.html | landingspagina vs website | ~250/mnd | **Düşük** |

Her makale: 1400-1900 kelime, unique içerik, Article + FAQPage + BreadcrumbList schema, internal links.

### C. Updated sitemap.xml
31 URLs nu (eerst 16):
- Homepage + bestellen + legal
- 7 diensten (6 service pages + overview)
- 9 locaties (8 steden + overview)
- 11 blog (10 artikelen + overview)

### D. Updated blog/index.html
10 artikel-kaarten toont nu (eerder 3).

---

## 🚀 Deploy-instructie

Je deployt zelf via Git push. Stappen:

```bash
# Vanuit je lokale git-repo root:
cd /path/to/websitemakenpro-repo

# 1. Onzip nieuwe bestanden (overschrijft bestaande)
#    Kopieer ALLE bestanden uit de zip naar je repo-root

# 2. Check wat gewijzigd is:
git status

# 3. Commit alles tegelijk:
git add .
git commit -m "feat: 7 new SEO blog articles + H1 fix + testimonial cleanup + sitemap update"

# 4. Push:
git push origin main
```

Cloudflare Pages zal binnen ~2 minuten auto-deploy triggeren.

---

## ✅ Post-deploy verificatie

**Na deploy: check in browser (hard-refresh, Ctrl+Shift+R):**

### Homepage
- [ ] H1 toont: "Website laten maken met AI. Vaste prijs vanaf €749."
- [ ] Portfolio cards starten met "Voorbeeld · [sector]"
- [ ] Geen fake testimonials meer (Mehmet/Linda/Sanne)
- [ ] In plaats: "4 Beloftes" (Vaste prijs / Directe communicatie / 100% eigendom / Eerlijk advies)
- [ ] Pricing "Kies Pro →" leidt naar `/bestellen?pakket=pro`
- [ ] Nav "Offerte aanvragen" leidt naar `/bestellen`

### Blog
- [ ] `/blog/` toont 10 artikelen (eerst 3)
- [ ] `/blog/eu-ai-act-mkb.html` laadt en toont correct
- [ ] `/blog/website-snelheid-verbeteren.html` laadt
- [ ] ... en 5 andere nieuwe artikelen

### Locaties
- [ ] `/locaties/website-laten-maken-amsterdam.html` toont "Amsterdamse" (hoofdletter)
- [ ] Geen rating-sterren meer (fake AggregateRating is weg)

### Sitemap
- [ ] `https://websitemakenpro.nl/sitemap.xml` laadt XML met 31 URLs

---

## 🔍 Google Search Console actie (KRITIEK voor SEO)

Na deploy, **binnen 24 uur**:

1. Login op [Google Search Console](https://search.google.com/search-console)
2. Property: `websitemakenpro.nl` (als niet, toevoegen — DNS-verificatie)
3. **Sitemaps** → Submit sitemap: `https://websitemakenpro.nl/sitemap.xml`
4. **URL Inspection** → voer per belangrijkste pagina in en klik "Request indexing":
   - `https://websitemakenpro.nl/` (homepage)
   - `https://websitemakenpro.nl/blog/eu-ai-act-mkb.html` (laagste rekabet, eerste win kansı)
   - `https://websitemakenpro.nl/diensten/ai-chatbot-laten-maken.html`
   - `https://websitemakenpro.nl/diensten/whatsapp-bestelbot-laten-maken.html`
   - `https://websitemakenpro.nl/blog/landingspagina-vs-website.html`

Dit versnelt indexering van dagen/weken naar 24-48 uur.

---

## 📈 Verwachte SEO impact (60-180 dagen)

| Term | Verwachte positie | Timeframe |
|------|------------------|-----------|
| `eu ai act mkb` | Top 10, mogelijk top 3 | 4-8 weken |
| `whatsapp bestelbot` | Top 10 | 8-12 weken |
| `ai chatbot laten maken` | Top 20 | 12-20 weken |
| `landingspagina vs website` | Top 10 | 8-12 weken |
| `core web vitals` | Top 30-50 | 12-24 weken |
| `website laten maken` | Top 50-100 | 6-12 maanden |

**Let op:** SEO is een traag spel. In de eerste 4 weken zie je weinig. Tussen week 4-8 begint Google te indexeren en ranken. Echte resultaten vanaf maand 3.

---

## 🎯 Volgende stappen (als dit live is)

In volgorde van prioriteit:

1. **Google Search Console setup** (vandaag, 15 min)
2. **Deploy verificatie** (morgen, 10 min)
3. **Analytics check** — Google Analytics installatie bevestigen
4. **Klantgesprekken**: vraag aan je alfareclame-klanten toestemming voor portfolio-pagina's op websitemakenpro.nl
   - Critisch: niet cross-linken tussen sites in eerste 6 maanden
   - Wel toegestaan: aparte case-pagina met logo + naam op websitemakenpro.nl
5. **Programmatic SEO** (parça 3): 48 city×sector combo landing pages
6. **AI Agency project** — wacht tot websitemakenpro.nl eerste SEO-traction toont

---

## 📝 Changelog sinds eerste deploy

```
v0.1 (originele deploy)
  - Homepage met Pro-pakket
  - 8 stadspagina's
  - 3 blog artikelen
  - Domain checker

v0.2 (vorige sessie, niet gedeployd)
  - H1 versterkt voor SEO
  - Portfolio Voorbeeld-labels
  - Fake testimonials vervangen
  - Dutch capitalization fixes
  - CTA routing cleanup
  - 6 dienst-pagina's toegevoegd

v0.3 (deze sessie) ← DEZE ZIP
  - 7 nieuwe SEO blog artikelen
  - Sitemap uitgebreid naar 31 URLs
  - Blog index geupdated naar 10 artikelen
  - Alle blog artikelen internal-linked
```

---

## ❓ Als iets niet werkt

Post-deploy problemen die kunnen voorkomen:

**Blog artikelen laden niet**
- Check: staan alle HTML files in `/blog/` folder?
- Check: Cloudflare cache clear via Dashboard → Caching → Purge Everything

**Sitemap error bij Google**
- Check: XML validator op `https://websitemakenpro.nl/sitemap.xml`
- Check: robots.txt verwijst naar sitemap

**H1 nog steeds oude versie**
- Hard refresh: Ctrl+Shift+R (Windows) / Cmd+Shift+R (Mac)
- Cloudflare purge cache

**CTA links nog steeds #contact**
- Browser cache probleem — incognito/private window proberen
- Of CF cache clear

---

## 💡 Pro-tip voor maximum SEO-impact

Na deployment, **wacht 48 uur** en voer dan dit uit in Google Search Console:

1. Ga naar "Pages" rapport
2. Filter op "Last crawl" datum
3. Check of alle 31 URLs gecrawld zijn
4. Zo niet: URL Inspection → Request Indexing voor elk

Extra: plaats één link naar websitemakenpro.nl vanaf alfareclame.nl **alleen in footer**, met rel="nofollow". Dat is de enige "cross-link" die veilig is zonder footprint-detection risico.

---

Succes met de deploy! 🚀
