# WebsiteMakenPro.nl — Installatie Handleiding

## Stap 1: mijn.host API Key aanmaken

1. Ga naar https://mijn.host en log in met je reseller account
2. Ga naar **Account** > **API** (of https://mijn.host/account/api)
3. Klik op **API Key aanmaken**
4. Geef de key een naam: "WebsiteMakenPro Domain Checker"
5. Kopieer de API key (begint meestal met een lange string)
6. **BEWAAR DEZE KEY VEILIG** — je hebt hem maar 1x te zien

## Stap 2: API Key instellen

1. Open het bestand `api/domain-check.php`
2. Zoek de regel:
   ```
   define('MIJHOST_API_KEY', 'BURAYA_API_KEY_YAZIN');
   ```
3. Vervang `BURAYA_API_KEY_YAZIN` met jouw echte API key:
   ```
   define('MIJHOST_API_KEY', 'jouw-echte-api-key-hier');
   ```
4. Controleer ook de ALLOWED_ORIGIN:
   ```
   define('ALLOWED_ORIGIN', 'https://websitemakenpro.nl');
   ```
   Dit moet je eigen domeinnaam zijn.

## Stap 3: Bestanden uploaden naar mijn.host

Upload de HELE `website/` map naar je hosting via FTP of File Manager:

```
website/
  index.html          → /public_html/index.html
  css/style.css       → /public_html/css/style.css
  js/main.js          → /public_html/js/main.js
  api/domain-check.php → /public_html/api/domain-check.php
  pages/offerte.html  → /public_html/pages/offerte.html
  pages/zelf-bouwen.html → /public_html/pages/zelf-bouwen.html
  pages/prijzen.html  → /public_html/pages/prijzen.html
  pages/webshop.html  → /public_html/pages/webshop.html
  pages/contact.html  → /public_html/pages/contact.html
```

**Let op:** De map `api/` met `domain-check.php` MOET op de server staan.
PHP moet beschikbaar zijn (mijn.host ondersteunt dit standaard).

## Stap 4: DNS instellen

Zorg dat websitemakenpro.nl naar je mijn.host hosting wijst:
- A record: `@` → jouw server IP
- A record: `www` → jouw server IP
- Of gebruik de nameservers van mijn.host

## Stap 5: Testen

1. Ga naar https://websitemakenpro.nl
2. Klik op "Zelf bouwen met AI"
3. Typ een domeinnaam in de checker
4. Klik "Controleer beschikbaarheid"
5. Het resultaat moet komen van de echte mijn.host API

## Hoe het werkt (technisch)

```
Browser (JS) → /api/domain-check.php → mijn.host API
                  ↓                         ↓
              API key zit              Echte domain check
              veilig op server         via WHOIS/registrar
                  ↓
              Resultaat terug
              naar browser
```

De API key is NOOIT zichtbaar voor bezoekers van je site.

## Domain Registratie Flow

Als een domain beschikbaar is:
1. Bezoeker ziet "beschikbaar!" met groene checkmark
2. Twee opties verschijnen:
   - **"Direct registreren via mijn.host"** → opent mijn.host bestelproces
   - **"Wij regelen het voor je"** → gaat naar offerte formulier
3. Bij de eerste optie verdien je als reseller de marge op de domain registratie
4. Bij de tweede optie kun je de domain + website bundelen in een pakket

## Prijzen instellen

De domeinprijzen staan in `api/domain-check.php` in de functie `getDomainPrice()`.
Pas deze aan op basis van je werkelijke mijn.host reseller prijzen:

```php
$prices = [
    '.nl'  => ['register' => '€5,99/jaar',  'renew' => '€8,99/jaar'],
    '.com' => ['register' => '€10,99/jaar', 'renew' => '€12,99/jaar'],
    // ... etc
];
```

## Fallback (als API niet werkt)

Als de PHP proxy niet bereikbaar is (bijv. bij lokaal testen), valt het systeem
automatisch terug op Google DNS API. Dit checkt of een domain DNS records heeft:
- Geen records = waarschijnlijk beschikbaar
- Wel records = bezet

Dit is minder nauwkeurig dan de mijn.host API maar werkt als backup.

## WhatsApp nummer instellen

In alle HTML bestanden staat een WhatsApp link:
```html
<a href="https://wa.me/31XXXXXXXXX" ...>
```
Vervang `31XXXXXXXXX` met je eigen Nederlandse telefoonnummer (zonder 0, met landcode 31).
Voorbeeld: 31612345678

## E-mail formulieren

De offerte en contact formulieren loggen nu naar de console.
Voor echte e-mail verzending heb je twee opties:

### Optie A: EmailJS (gratis, geen backend nodig)
1. Ga naar https://www.emailjs.com en maak een gratis account
2. Voeg je e-mail service toe (Gmail, Outlook, etc.)
3. Maak een e-mail template
4. Voeg de EmailJS SDK toe aan de HTML bestanden
5. Update main.js om EmailJS te gebruiken in plaats van console.log

### Optie B: PHP mail (server-side)
Maak een `api/contact.php` en `api/offerte.php` die de formulieren per e-mail versturen.
