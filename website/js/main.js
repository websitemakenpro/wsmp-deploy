/* ============================================
   WebsiteMakenPro.nl — Main JavaScript
   ============================================ */

document.addEventListener('DOMContentLoaded', () => {
  // --- Mobile Menu Toggle ---
  const mobileToggle = document.querySelector('.nav-mobile-toggle');
  const mobileMenu = document.querySelector('.nav-mobile-menu');
  if (mobileToggle && mobileMenu) {
    mobileToggle.addEventListener('click', () => {
      mobileMenu.classList.toggle('open');
      const isOpen = mobileMenu.classList.contains('open');
      mobileToggle.innerHTML = isOpen
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>'
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>';
    });
    // Close menu on link click
    mobileMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        mobileMenu.classList.remove('open');
        mobileToggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>';
      });
    });
  }

  // --- FAQ Accordion ---
  document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', () => {
      const item = btn.parentElement;
      const isActive = item.classList.contains('active');
      // Close all
      document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('active'));
      // Open clicked (if wasn't active)
      if (!isActive) item.classList.add('active');
    });
  });

  // --- Scroll Animations ---
  const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -40px 0px' };
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);
  document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));

  // --- Navbar scroll effect ---
  const navbar = document.querySelector('.navbar');
  if (navbar) {
    window.addEventListener('scroll', () => {
      if (window.scrollY > 10) {
        navbar.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
      } else {
        navbar.style.boxShadow = 'none';
      }
    });
  }

  // --- CTA Email Form ---
  const ctaForm = document.getElementById('cta-form');
  if (ctaForm) {
    ctaForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const email = ctaForm.querySelector('input[type="email"]').value;
      if (email && email.includes('@')) {
        ctaForm.style.display = 'none';
        const note = ctaForm.nextElementSibling;
        if (note) note.style.display = 'none';
        const success = document.getElementById('cta-success');
        if (success) success.style.display = 'block';
        // In production: send to backend/EmailJS
        console.log('Waitlist signup:', email);
      }
    });
  }

  // --- Offerte Form ---
  const offerteForm = document.getElementById('offerte-form');
  if (offerteForm) {
    offerteForm.addEventListener('submit', (e) => {
      e.preventDefault();
      let valid = true;

      // Reset errors
      offerteForm.querySelectorAll('.form-error').forEach(err => err.style.display = 'none');

      // Validate required fields
      const required = offerteForm.querySelectorAll('[required]');
      required.forEach(field => {
        if (!field.value.trim()) {
          valid = false;
          const error = field.parentElement.querySelector('.form-error');
          if (error) { error.textContent = 'Dit veld is verplicht'; error.style.display = 'block'; }
        }
      });

      // Email validation
      const emailField = offerteForm.querySelector('input[type="email"]');
      if (emailField && emailField.value && !emailField.value.includes('@')) {
        valid = false;
        const error = emailField.parentElement.querySelector('.form-error');
        if (error) { error.textContent = 'Voer een geldig e-mailadres in'; error.style.display = 'block'; }
      }

      if (valid) {
        offerteForm.style.display = 'none';
        const success = document.getElementById('offerte-success');
        if (success) success.style.display = 'block';
        // In production: send via EmailJS or backend API
        const formData = new FormData(offerteForm);
        console.log('Offerte aanvraag:', Object.fromEntries(formData));
      }
    });
  }

  // --- Contact Form ---
  const contactForm = document.getElementById('contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', (e) => {
      e.preventDefault();
      let valid = true;
      contactForm.querySelectorAll('.form-error').forEach(err => err.style.display = 'none');
      contactForm.querySelectorAll('[required]').forEach(field => {
        if (!field.value.trim()) {
          valid = false;
          const error = field.parentElement.querySelector('.form-error');
          if (error) { error.textContent = 'Dit veld is verplicht'; error.style.display = 'block'; }
        }
      });
      if (valid) {
        contactForm.style.display = 'none';
        const success = document.getElementById('contact-success');
        if (success) success.style.display = 'block';
        console.log('Contact form submitted');
      }
    });
  }

  // --- Domain Checker (REAL — via mijn.host API proxy) ---
  const domainBtn = document.getElementById('domain-check-btn');
  if (domainBtn) {
    domainBtn.addEventListener('click', async () => {
      const input = document.getElementById('domain-input');
      const ext = document.getElementById('domain-ext');
      const result = document.getElementById('domain-result');
      const loading = document.getElementById('domain-loading');
      const registerSection = document.getElementById('domain-register');

      if (!input || !result) return;

      const name = input.value.trim().toLowerCase().replace(/[^a-z0-9\-]/g, '');
      if (!name) {
        result.className = 'domain-result domain-taken';
        result.style.display = 'block';
        result.innerHTML = 'Voer een domeinnaam in (bijv. <strong>mijnbedrijf</strong>)';
        if (registerSection) registerSection.style.display = 'none';
        return;
      }

      const extension = ext ? ext.value : '.nl';
      const domain = name + extension;

      // Show loading, hide previous results
      if (loading) loading.style.display = 'block';
      result.style.display = 'none';
      if (registerSection) registerSection.style.display = 'none';
      domainBtn.disabled = true;
      domainBtn.textContent = 'Controleren...';

      try {
        // ============================================
        // ECHTE API CALL via PHP proxy
        // ============================================
        // De proxy staat op /api/domain-check.php en roept mijn.host API aan
        // API key blijft veilig op de server
        const apiUrl = '/api/domain-check.php?domain=' + encodeURIComponent(domain);
        const response = await fetch(apiUrl);
        const data = await response.json();

        if (loading) loading.style.display = 'none';
        domainBtn.disabled = false;
        domainBtn.textContent = 'Controleer beschikbaarheid';

        if (data.error) {
          // API error — toon melding
          result.className = 'domain-result domain-taken';
          result.innerHTML = 'Er ging iets mis bij het controleren. Probeer het later opnieuw.';
          result.style.display = 'block';
          console.error('Domain check error:', data.error);
          return;
        }

        if (data.available === true) {
          // BESCHIKBAAR
          const price = data.price_indication ? data.price_indication.register : '€12,99/jaar';
          result.className = 'domain-result domain-available';
          result.innerHTML =
            '<svg style="width:20px;height:20px;vertical-align:middle;margin-right:8px" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>' +
            '<strong>' + domain + '</strong> is beschikbaar!' +
            '<br><small style="color:#6b7280">Registratie vanaf ' + price + '</small>';
          result.style.display = 'block';

          // Toon registratie sectie
          if (registerSection) {
            registerSection.style.display = 'block';
            const registerLink = registerSection.querySelector('.domain-register-link');
            if (registerLink) {
              registerLink.href = data.register_url || ('https://mijn.host/bestellen/domein/?domain=' + encodeURIComponent(domain));
            }
            const registerDomain = registerSection.querySelector('.domain-register-name');
            if (registerDomain) {
              registerDomain.textContent = domain;
            }
          }

        } else if (data.available === false) {
          // BEZET
          result.className = 'domain-result domain-taken';
          result.innerHTML =
            '<svg style="width:20px;height:20px;vertical-align:middle;margin-right:8px" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>' +
            '<strong>' + domain + '</strong> is helaas al bezet.' +
            '<br><small style="color:#6b7280">Probeer een andere naam of extensie (.com, .eu, .be)</small>';
          result.style.display = 'block';
        } else {
          // Onbekend resultaat
          result.className = 'domain-result domain-taken';
          result.innerHTML = 'Kon de beschikbaarheid niet bepalen. Neem <a href="pages/contact.html" style="color:#2563eb">contact</a> met ons op.';
          result.style.display = 'block';
        }

      } catch (err) {
        // Network error / fetch failed
        if (loading) loading.style.display = 'none';
        domainBtn.disabled = false;
        domainBtn.textContent = 'Controleer beschikbaarheid';

        // FALLBACK: als de API niet beschikbaar is (bijv. lokale test),
        // gebruik DNS-gebaseerde check via Google DNS API
        console.warn('PHP proxy niet bereikbaar, fallback naar DNS check...', err);
        try {
          const dnsUrl = 'https://dns.google/resolve?name=' + encodeURIComponent(domain) + '&type=A';
          const dnsResponse = await fetch(dnsUrl);
          const dnsData = await dnsResponse.json();

          // Status 3 = NXDOMAIN (domain bestaat niet in DNS = waarschijnlijk beschikbaar)
          // Status 0 = NOERROR (domain heeft DNS records = bezet)
          const isAvailable = dnsData.Status === 3;

          if (isAvailable) {
            result.className = 'domain-result domain-available';
            result.innerHTML =
              '<svg style="width:20px;height:20px;vertical-align:middle;margin-right:8px" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>' +
              '<strong>' + domain + '</strong> is waarschijnlijk beschikbaar!' +
              '<br><small style="color:#6b7280">Neem contact op om te registreren vanaf €12/jaar</small>';
          } else {
            result.className = 'domain-result domain-taken';
            result.innerHTML =
              '<svg style="width:20px;height:20px;vertical-align:middle;margin-right:8px" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>' +
              '<strong>' + domain + '</strong> is helaas al bezet.' +
              '<br><small style="color:#6b7280">Probeer een andere naam of extensie</small>';
          }
          result.style.display = 'block';

          // Toon registratie knop als beschikbaar
          if (isAvailable && registerSection) {
            registerSection.style.display = 'block';
            const registerLink = registerSection.querySelector('.domain-register-link');
            if (registerLink) {
              registerLink.href = 'https://mijn.host/bestellen/domein/?domain=' + encodeURIComponent(domain);
            }
            const registerDomain = registerSection.querySelector('.domain-register-name');
            if (registerDomain) registerDomain.textContent = domain;
          }

        } catch (dnsErr) {
          result.className = 'domain-result domain-taken';
          result.innerHTML = 'Kan de beschikbaarheid niet controleren. <a href="pages/contact.html" style="color:#2563eb">Neem contact op</a> en wij checken het voor je.';
          result.style.display = 'block';
          console.error('DNS fallback ook mislukt:', dnsErr);
        }
      }
    });

    // Enter toets support
    const domainInput = document.getElementById('domain-input');
    if (domainInput) {
      domainInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          domainBtn.click();
        }
      });
    }
  }

  // --- Pre-select offerte type from URL params ---
  const urlParams = new URLSearchParams(window.location.search);
  const typeParam = urlParams.get('type');
  if (typeParam) {
    const typeSelect = document.querySelector('select[name="type"]');
    if (typeSelect) {
      for (let option of typeSelect.options) {
        if (option.value.toLowerCase() === typeParam.toLowerCase()) {
          option.selected = true;
          break;
        }
      }
    }
  }
});
