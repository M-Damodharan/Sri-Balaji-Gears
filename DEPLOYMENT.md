# Sri Balaji Gears – Production Deployment Guide

This document provides complete instructions for deploying the **Sri Balaji Gears** website and ERP enquiry management system to **Render** and traditional Apache/cPanel hosting environments.

---

## 1. System Architecture Overview

- **Frontend**: Clean semantic HTML5, CSS3 Custom Properties (Design System), Vanilla ES6 JavaScript. Zero node/npm compilation runtime dependencies. High performance, 100% mobile responsive.
- **Backend**: PHP 8.0+ (PHP 8.2 recommended), PDO SQLite driver, JSON REST API endpoints (`/api/enquiry.php`, `/api/download.php`).
- **Database**: SQLite 3 database (`data/enquiries.db`) with Write-Ahead Logging (WAL) mode for concurrency and zero external database management overhead.
- **Attachment Storage**: Secure file storage directory (`uploads/`) with executable script blocking via `.htaccess` and unique cryptographically randomized file hash naming.
- **Administration**: Standalone authenticated dashboard (`/admin/index.php`) featuring status workflows, search/filtering, attachment downloads, and one-click CSV export.

---

## 2. Render Hosting Architecture: Web Service (Docker)

> [!IMPORTANT]
> **Why a Web Service is Required Instead of a Static Site:**
> The Sri Balaji Gears platform includes active server-side PHP functionality:
> 1. RFQ submission processing and validation (`/api/enquiry.php`)
> 2. Persistent SQLite database storage (`/data/enquiries.db`)
> 3. Drawing and CAD file upload management (`/uploads/`)
> 4. Protected admin portal and authentication (`/admin/`)
> 
> Render's "Static Site" product only serves raw static files and cannot execute PHP or write to disk. Therefore, a **Render Web Service (Docker)** is required.

The repository includes a production-ready [`Dockerfile`](./Dockerfile), [`docker-entrypoint.sh`](./docker-entrypoint.sh), and [`render.yaml`](./render.yaml) blueprint.

---

## 3. Deploying to Render

### Method A: Automated Blueprint Deployment via `render.yaml` (Recommended)

1. Log into your [Render Dashboard](https://dashboard.render.com/).
2. Click **Blueprints** in the top navigation.
3. Click **New Blueprint Instance**.
4. Connect the GitHub repository: `https://github.com/M-Damodharan/Sri-Balaji-Gears`.
5. Render will automatically detect [`render.yaml`](./render.yaml).
6. Fill in the required environment variables when prompted:
   - `ADMIN_PASSWORD`: Enter a strong password for your admin dashboard.
   - `SMTP_USER` and `SMTP_PASS`: (Optional) Your SMTP credentials for automated email delivery.
7. Click **Apply**. Render will build the Docker container and deploy your Web Service.

---

### Method B: Manual Web Service Setup on Render

If configuring manually without the Blueprint tool:

1. In Render Dashboard, click **New +** -> **Web Service**.
2. Select **Build and deploy from a Git repository**.
3. Choose `M-Damodharan/Sri-Balaji-Gears`.
4. Configure the service settings:
   - **Name**: `sri-balaji-gears`
   - **Region**: `Singapore` (Recommended for Indian subcontinent traffic) or `Oregon`
   - **Branch**: `main`
   - **Runtime**: `Docker`
   - **Dockerfile Path**: `./Dockerfile`
   - **Docker Context**: `.`
   - **Plan**: `Free` or `Starter`
   - **Health Check Path**: `/`
5. Click **Advanced** and add the **Environment Variables** listed in Section 4 below.
6. Click **Create Web Service**.

---

## 4. Production Environment Variables

Configure these variables in Render under **Environment** (or in a `.env` file for traditional servers):

| Variable | Recommended Production Value | Description |
| :--- | :--- | :--- |
| `APP_ENV` | `production` | Sets application mode and suppresses public error displays |
| `APP_URL` | `https://www.sribalajigears.com` | Primary canonical URL of the company website |
| `ERP_EMAIL` | `erp@sribalajigears.com` | Target recipient for all RFQ and technical enquiries |
| `COMPANY_NAME` | `Sri Balaji Gears` | Company display name used in automated email templates |
| `ADMIN_USERNAME` | `admin` | Admin dashboard login username |
| `ADMIN_PASSWORD` | *(Strong custom password)* | Admin dashboard login password |
| `MAX_UPLOAD_SIZE_MB` | `15` | Maximum allowed attachment size in Megabytes |
| `SMTP_ENABLED` | `true` (or `false`) | Set to `true` to deliver via direct SMTP server |
| `SMTP_HOST` | `smtp.rediffmailpro.com` (Rediffmail Pro) | Outgoing SMTP server hostname |
| `SMTP_PORT` | `465` (SSL) | SMTP port |
| `SMTP_USER` | `erp@sribalajigears.com` | SMTP authentication username / account |
| `SMTP_PASS` | `sbgerp@2026` | SMTP authentication password |
| `SMTP_SECURE` | `ssl` | Transport encryption protocol |
| `SMTP_FROM_EMAIL` | `erp@sribalajigears.com` | From email header for notifications |
| `SMTP_FROM_NAME` | `Sri Balaji Gears RFQ Portal` | Sender display name |

---

## 5. Custom Domain & DNS Configuration (`sribalajigears.com`)

Render automatically manages free Let's Encrypt SSL/TLS certificates and renews them before expiration.

### Step 1: Add Custom Domains in Render
1. Open your service in the Render Dashboard.
2. Go to **Settings** -> **Custom Domains**.
3. Add `sribalajigears.com` and `www.sribalajigears.com`.

### Step 2: Configure DNS Records at Your Registrar (GoDaddy, Namecheap, Cloudflare, etc.)

| Type | Name / Host | Value / Target | TTL |
| :--- | :--- | :--- | :--- |
| **CNAME** | `www` | `sri-balaji-gears.onrender.com` | Auto / 3600 |
| **ANAME / ALIAS** | `@` (root) | `sri-balaji-gears.onrender.com` | Auto / 3600 |
| **A** *(if ALIAS unsupported)* | `@` (root) | `216.24.57.1` *(or IP shown in Render dashboard)* | Auto / 3600 |

*Note: DNS propagation typically takes between 5 minutes and 24 hours depending on TTL.*

---

## 6. Persistent Storage on Render (Optional Starter Plan Upgrade)

On Render's Free tier, the filesystem is ephemeral (resets on service restarts or new deploys). For 100% persistent database storage across container re-deployments:

1. Upgrade the Render Web Service to the **Starter Plan** ($7/mo).
2. Go to **Disks** -> **Add Disk**:
   - **Name**: `sbg-data`
   - **Mount Path**: `/var/www/html/data`
   - **Size**: `1 GB` (sufficient for ~50,000 enquiries)
3. You can also mount a second disk for `/var/www/html/uploads` if keeping long-term multi-gigabyte CAD files.
4. If remaining on the Free plan, use the **Export to CSV** button in `/admin/index.php` periodically to download local backups.

---

## 7. Alternative Deployment: Shared Apache / cPanel / InfinityFree

If deploying to a traditional web host instead of Render:

1. Download [`website-upload.zip`](./website-upload.zip) from the repository root.
2. In your cPanel / File Manager, navigate **inside** the `htdocs/` (or `public_html/`) folder.
3. Upload `website-upload.zip` using **Zip & Extract**.
4. Verify file permissions:
   - Folders `data/` and `uploads/`: `755` or `775` (writable by web server).
   - `.htaccess` files inside `data/` and `uploads/` must remain in place to block unauthorized downloads.
5. Create a `.env` file in the root directory (copied from [`.env.example`](./.env.example)) and fill in your production values.

---

## 8. Post-Deployment Verification Checklist

- [ ] **Home Page**: Open `https://www.sribalajigears.com/` and confirm dark-gold styling, logos, and sticky header.
- [ ] **Navigation**: Verify all 9 pages load cleanly without 404 errors.
- [ ] **RFQ Submission**:
  - Visit `/contact.html`.
  - Submit a test RFQ with a sample drawing (`.pdf` or `.step`).
  - Confirm instant green success badge with Reference ID (`SBG-RFQ-YYYYMMDD-XXXX`).
- [ ] **Admin Portal**:
  - Open `https://www.sribalajigears.com/admin/login.php`.
  - Log in with configured `ADMIN_USERNAME` and `ADMIN_PASSWORD`.
  - Confirm the test enquiry appears in the table with contact info and timestamp.
  - Download the test attachment to verify file integrity.
  - Test the **Export to CSV** button.
- [ ] **SSL / HTTPS**: Confirm green padlock is active across all URLs.
