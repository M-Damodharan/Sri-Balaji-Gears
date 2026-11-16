# Sri Balaji Gears – Google SEO & Integration Setup Guide

This guide details the complete procedure for connecting the **Sri Balaji Gears** website to Google Search Console, Google Analytics 4, and Google Business Profile, ensuring top search visibility for automotive engineering and industrial search queries in Hosur and South India.

---

## 1. Google Search Console (GSC) Verification

Google Search Console monitors search performance, indexes pages, and identifies crawl issues.

### Step 1: Add Property in Search Console
1. Visit [Google Search Console](https://search.google.com/search-console/).
2. Select **Add Property**.
3. Choose one of two property types:
   - **Domain Property** (Recommended): Enter `sribalajigears.in` (covers both `https://`, `http://`, `www.`, and non-`www`).
   - **URL Prefix**: Enter `https://www.sribalajigears.in/`.

### Step 2: Choose Your Verification Method

#### Method A: DNS TXT Record (Required for Domain Property – Cleanest)
1. In Search Console, select **DNS record** verification.
2. Copy the `google-site-verification=...` text provided by Google.
3. Open your domain registrar DNS manager (GoDaddy, Namecheap, Cloudflare, etc.).
4. Add a new DNS Record:
   - **Type**: `TXT`
   - **Host / Name**: `@` (or leave empty depending on registrar)
   - **Value**: Paste the exact verification string from Google.
   - **TTL**: `3600` (or Auto).
5. Click **Verify** in Search Console.

#### Method B: HTML Meta Tag Verification
1. In Search Console, select **HTML tag** under Other verification methods.
2. Google will provide a meta tag:
   ```html
   <meta name="google-site-verification" content="YOUR_UNIQUE_CODE_HERE" />
   ```
3. Copy only the value inside `content="..."`.
4. Open the project HTML files (`index.html`, `contact.html`, etc.) and find:
   ```html
   <meta name="google-site-verification" content="GSC_VERIFICATION_TOKEN_PLACEHOLDER">
   ```
5. Replace `GSC_VERIFICATION_TOKEN_PLACEHOLDER` with your actual token.
6. Commit and deploy, then click **Verify** in Search Console.

---

## 2. Google Sitemap Submission

The sitemap informs Google of all official URLs, priorities, and update frequencies.

- **Sitemap Public URL**: `https://www.sribalajigears.in/sitemap.xml`
- **Robots Reference**: Configured in [`robots.txt`](./robots.txt):
  ```txt
  Sitemap: https://www.sribalajigears.in/sitemap.xml
  ```

### How to Submit:
1. In Google Search Console, navigate to **Indexing** -> **Sitemaps** in the left sidebar.
2. Under "Add a new sitemap", enter:
   ```
   sitemap.xml
   ```
3. Click **Submit**.
4. Status will show as **Success** once crawled (usually within 12–48 hours).

### Automated Sitemap Regeneration:
Whenever you publish new pages or modify content, run either:
- Python: `python scripts/generate_sitemap.py`
- PHP: `php scripts/generate-sitemap.php`

Both scripts automatically re-calculate modification timestamps and format a valid Google-compliant XML document.

---

## 3. Google Analytics 4 (GA4) Integration

Track OEM visitors, RFQ page conversions, and customer interactions across the site.

### Step 1: Create a GA4 Data Stream
1. Log into [Google Analytics](https://analytics.google.com/).
2. Navigate to **Admin** -> **Data Streams** -> **Web**.
3. Enter:
   - **Website URL**: `https://www.sribalajigears.in`
   - **Stream Name**: `Sri Balaji Gears Web Portal`
4. Copy your **Measurement ID** (format: `G-XXXXXXXXXX`).

### Step 2: Enable the Tracking Code in HTML Files
Every public `.html` file already contains a dedicated placeholder right above `</head>`. Uncomment and paste your Measurement ID:

```html
<!-- Google Analytics 4 (GA4) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX');
</script>
```

Replace both occurrences of `G-XXXXXXXXXX` with your actual Measurement ID, commit, and deploy.

---

## 4. Google Business Profile (GBP) Linking & Local SEO

To dominate Google Maps and local search for "automotive welding Hosur", "bus seat frame manufacturer", and "SIPCOT metal stamping":

### Consistent NAP (Name, Address, Phone) Standard:
Ensure your Google Business Profile matches the website structured data exactly:

- **Business Name**: `Sri Balaji Gears`
- **Primary Category**: `Automotive Manufacturer` or `Metal Fabricator`
- **Secondary Categories**: `Welding Supply Store`, `Mechanical Engineer`, `Precision Engineer`
- **Address**: `SIPCOT Industrial Complex, Phase I, Hosur, Tamil Nadu 635126, India`
- **Latitude / Longitude**: `12.7409° N, 77.8253° E`
- **Phone Number**: `+91 73733 34411`
- **Official Website URL**: `https://www.sribalajigears.in/`
- **Appointment / RFQ URL**: `https://www.sribalajigears.in/contact.html`
- **Operating Hours**:
  - Monday – Saturday: `08:30 – 18:00 IST`
  - Sunday: `Closed (Maintenance & 5S)`

---

## 5. Technical SEO & Rich Snippets Architecture

The website is already built with enterprise structured data compliant with schema.org:

1. **Organization & AutomotiveBusiness Schema** (in [`index.html`](./index.html)):
   - Declares official legal entity name, Hosur SIPCOT address, official logo, telephone, and industrial capabilities (`knowsAbout`: Bus Seat Frame Assemblies, MIG/TIG Welding, 50T–300T Press Stamping, IATF 16949 Standards).
2. **BreadcrumbList Schema** (across all 8 subpages):
   - Enables hierarchical navigation snippets directly in Google search result listings.
3. **Open Graph & Twitter Cards**:
   - Ensures high-impact social preview cards with titles, descriptions, and logos whenever links are shared on LinkedIn, WhatsApp, or email clients.
4. **Canonical URL Tags**:
   - Enforces the authoritative HTTPS `https://www.sribalajigears.in/` URL on every page to prevent duplicate content indexing penalties.
