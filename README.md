# Sri Balaji Gears — Enterprise Automotive Manufacturing Website & ERP Portal

A modern, production-grade corporate website and RFQ enquiry management portal for **Sri Balaji Gears** (SIPCOT Industrial Complex, Phase I, Hosur, Tamil Nadu), engineered for Tier-1 automotive manufacturing credibility, seamless customer RFQ processing, and Google search visibility.

---

## 🌟 Architecture & Core Capabilities

1. **Frontend Experience (9 Public Pages)**:
   - **Modern Responsive Design**: Optimized across mobile phones (320px–480px), tablets (768px–1024px), laptops, and 4K ultra-wide screens using CSS `clamp()`, responsive grids, and touch target optimization (>44px).
   - **Interactive Components**: Real-time counter animations, tabbed product portfolio, modal image lightbox for compliance certificates, expandable FAQ accordions, and one-click copy buttons for phone/email/address with toast feedback.
   - **9 Complete Pages**: `index.html` (Home), `about.html`, `manufacturing.html`, `products.html`, `infrastructure.html`, `quality.html`, `certifications.html`, `customers.html` (showcasing UNO MINDA & TVS Motor), and `contact.html`.

2. **Customer Enquiry & ERP Integration (`erp@sribalajigears.com`)**:
   - **Instant RFQ Submission**: Connects directly to `erp@sribalajigears.com` via backend API (`/api/enquiry.php`).
   - **Drawing / CAD File Uploads**: Validates and accepts 2D/3D CAD (`.dwg`, `.dxf`, `.step`, `.stp`, `.iges`), `.pdf`, Excel, Word, ZIP, and image specifications up to 15MB.
   - **Dual-Action Delivery**: Sends an HTML notification to the plant ERP desk and a confirmation receipt to the customer.
   - **Tracking Reference**: Automatically issues unique IDs (e.g. `SBG-RFQ-YYYYMMDD-XXXX`).
   - **Anti-Spam & Security**: Invisible honeypot trap (`website_hp`) and IP rate limiting (15-second cooldown).

3. **Admin Enquiry Management Dashboard (`/admin/`)**:
   - Secure session-based authentication portal (`/admin/login.php`).
   - Comprehensive enquiries list with status management (New, In Review, Quoted, Closed).
   - Attachment download links with secure path traversal protection (`/api/download.php`).
   - **1-Click Export to CSV / Excel** for ERP record keeping.

4. **Production Hosting Preparation (Render & Apache)**:
   - Configured for **Render Web Service** via [`Dockerfile`](./Dockerfile) and [`render.yaml`](./render.yaml).
   - Dynamic port binding for Render via [`docker-entrypoint.sh`](./docker-entrypoint.sh).
   - Compatible with traditional Apache / cPanel / Shared hosting via [`.htaccess`](./.htaccess) and [`website-upload.zip`](./website-upload.zip).

5. **Google SEO & Integrations**:
   - **Schema.org Structured Data**: Complete `AutomotiveBusiness`, `Organization`, `LocalBusiness`, and `BreadcrumbList` JSON-LD schemas.
   - **Search Console & Analytics**: Ready for Google Search Console verification and Google Analytics 4 (`G-XXXXXXXXXX`).
   - **Automated Sitemap**: Auto-generated [`sitemap.xml`](./sitemap.xml) and [`robots.txt`](./robots.txt) pointing to `https://www.sribalajigears.in/`.

---

## 🏭 Company Profile Parameters

- **Legal Entity**: Sri Balaji Gears
- **Established**: 2001 (23+ years continuous manufacturing)
- **Workforce**: 400 Employees
- **Campus Area**: 43,600 Sq. Ft. Total Land Area | 35,000 Sq. Ft. Built-up
- **Location**: SIPCOT Industrial Complex Phase I, Hosur, Tamil Nadu 635126, India
- **Coordinates**: 12.7409° N, 77.8253° E
- **Corporate Email**: [erp@sribalajigears.com](mailto:erp@sribalajigears.com)
- **Plant Hotline**: +91 73733 34411
- **Domain**: [sribalajigears.in](https://www.sribalajigears.in)
- **Key OEM Customers**:
  - **UNO MINDA**: Dedicated bus seat frame welding (~90% specialized work focus)
  - **TVS Motor Company**: TVS Export Packing Structure

---

## 🚀 Quick Start & Local Preview

### Option 1: PHP Built-in Server (Full Features including Form & Admin)
```bash
# Run PHP server with SQLite support:
php -S localhost:8000

# Open in browser:
http://localhost:8000
# Admin Dashboard:
http://localhost:8000/admin/login.php
```

### Option 2: Docker Container
```bash
# Build the production image:
docker build -t sri-balaji-gears .

# Run container on port 8080:
docker run -p 8080:80 sri-balaji-gears

# Open:
http://localhost:8080
```

### Option 3: Python Server (Static Preview Only)
```bash
python -m http.server 8080
# Note: Form submission will automatically offer fallback to email client in static mode.
```

---

## 📖 Detailed Documentation Guides

- 📘 [**Render & Server Deployment Guide (DEPLOYMENT.md)**](./DEPLOYMENT.md): Detailed Render Blueprint setup, environment variables, persistent disk setup, custom domain DNS, and Apache hosting.
- 📗 [**Google SEO & Integration Guide (SEO_SETUP.md)**](./SEO_SETUP.md): Step-by-step instructions for Google Search Console verification, GA4 setup, sitemap submission, and Google Business Profile linking.
- ⚙️ [**Environment Variables Template (.env.example)**](./.env.example): Production environment variables template.

---

## 🔒 Security & Data Protection

- SQLite database (`data/enquiries.db`) and uploaded files (`uploads/`) are protected against direct web URL browsing via `.htaccess`.
- Passwords, email credentials, and API keys are stored strictly in environment variables, never committed to git.
- All SQL operations utilize prepared statements with parameter binding to prevent SQL injection.
