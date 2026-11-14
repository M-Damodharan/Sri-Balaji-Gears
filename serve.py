#!/usr/bin/env python3
"""
Local Development & Preview Server for Sri Balaji Gears
Supports static pages, SQLite database persistence, and real SMTP email delivery to Outlook/Gmail.
"""
import os
import sys
import json
import sqlite3
import urllib.parse
import uuid
import smtplib
import threading
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.mime.base import MIMEBase
from email import encoders
from datetime import datetime
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler

PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 8080
DIRECTORY = os.path.dirname(os.path.abspath(__file__))

os.makedirs(os.path.join(DIRECTORY, "data"), exist_ok=True)
os.makedirs(os.path.join(DIRECTORY, "uploads"), exist_ok=True)

# Helper to load .env variables
def load_env():
    env_vars = {}
    env_file = os.path.join(DIRECTORY, ".env")
    if os.path.exists(env_file):
        with open(env_file, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#"):
                    continue
                if "=" in line:
                    k, v = line.split("=", 1)
                    k = k.strip()
                    v = v.strip().strip('"').strip("'")
                    env_vars[k] = v
    return env_vars

def get_env(key, default=""):
    env_vars = load_env()
    return os.environ.get(key, env_vars.get(key, default))

def init_db():
    db_path = os.path.join(DIRECTORY, "data", "enquiries.db")
    conn = sqlite3.connect(db_path)
    c = conn.cursor()
    c.execute("""
        CREATE TABLE IF NOT EXISTS enquiries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reference_id TEXT UNIQUE NOT NULL,
            customer_name TEXT NOT NULL,
            company_name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT NOT NULL,
            requirement_category TEXT NOT NULL,
            custom_specification TEXT,
            volume_estimate TEXT,
            message TEXT NOT NULL,
            attachment_name TEXT,
            attachment_path TEXT,
            attachment_size INTEGER,
            ip_address TEXT,
            user_agent TEXT,
            status TEXT DEFAULT 'New',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    """)
    conn.commit()
    conn.close()

init_db()

def dispatch_emails(ref_id, name, company, email, phone, project_type, custom_cat, quantity, message, att_name, att_path):
    smtp_enabled = get_env("SMTP_ENABLED", "false").lower() in ("true", "1")
    smtp_host = get_env("SMTP_HOST", "").strip()

    if not smtp_enabled or not smtp_host:
        print(f"\n[INFO] Enquiry {ref_id} saved to database.")
        print("[NOTICE] To send real emails to Outlook/Gmail, configure SMTP in .env:")
        print("         SMTP_ENABLED=true")
        print("         SMTP_HOST=your-smtp-host (e.g. smtp.office365.com or smtp.gmail.com)")
        print("         SMTP_USER=your-email")
        print("         SMTP_PASS=your-password-or-app-password\n")
        return False

    smtp_port = int(get_env("SMTP_PORT", "465"))
    smtp_user = get_env("SMTP_USER", get_env("SMTP_USERNAME", "")).strip()
    smtp_pass = get_env("SMTP_PASS", get_env("SMTP_PASSWORD", "")).strip()
    smtp_secure = get_env("SMTP_SECURE", "ssl" if smtp_port == 465 else "tls").lower()
    erp_target = get_env("ERP_EMAIL", "erp@sribalajigears.com")
    from_name = get_env("SMTP_FROM_NAME", "Sri Balaji Gears RFQ Portal")
    from_email = get_env("SMTP_FROM_EMAIL", get_env("SMTP_FROM", erp_target))

    cat_display = f"Others: {custom_cat}" if project_type == "Others" and custom_cat else project_type

    try:
        # Build ERP Notification Email
        msg = MIMEMultipart("mixed")
        msg["From"] = f"{from_name} <{from_email}>"
        msg["To"] = erp_target
        msg["Reply-To"] = f"{name} <{email}>"
        msg["Subject"] = f"New Automotive RFQ [{ref_id}] - {company} ({cat_display})"

        app_url = get_env("APP_URL", "https://www.sribalajigears.in").rstrip("/")
        att_display = f"<strong style='color:#059669;'>✓ {att_name}</strong> (Securely attached below)" if att_name else "<span style='color:#94a3b8;'>No attachment submitted</span>"

        html_body = f"""<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>New Automotive RFQ [{ref_id}]</title>
</head>
<body style="margin:0; padding:0; background-color:#0b1320; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1e293b; -webkit-font-smoothing:antialiased;">
  <center style="width:100%; background-color:#0b1320; padding:24px 0;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:650px; background-color:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 10px 25px rgba(0,0,0,0.3); border:1px solid #1e293b;">
      
      <!-- Top Gold Accent Line -->
      <tr>
        <td height="4" style="background:linear-gradient(90deg, #d97706 0%, #f59e0b 50%, #fbbf24 100%); line-height:4px; font-size:4px;">&nbsp;</td>
      </tr>

      <!-- Corporate Header -->
      <tr>
        <td style="background-color:#07111e; padding:24px 28px; border-bottom:2px solid #efad2d;">
          <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td valign="middle">
                <div style="font-size:22px; font-weight:800; color:#ffffff; letter-spacing:0.04em;">
                  SRI BALAJI <span style="color:#efad2d;">GEARS</span>
                </div>
                <div style="font-size:11px; color:#94a3b8; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; margin-top:4px;">
                  Tier-1 Automotive Seating &amp; Chassis Welded Assemblies · Hosur
                </div>
              </td>
              <td align="right" valign="middle">
                <span style="display:inline-block; padding:6px 12px; background:rgba(239, 173, 45, 0.15); border:1px solid #efad2d; border-radius:20px; color:#fbbf24; font-size:11px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase;">
                  ⚡ NEW RFQ INQUIRY
                </span>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Subheader with Reference ID & Timestamp -->
      <tr>
        <td style="background-color:#f8fafc; padding:16px 28px; border-bottom:1px solid #e2e8f0;">
          <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td>
                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.06em;">Tracking Reference ID</div>
                <div style="font-size:16px; font-weight:800; color:#0f172a; font-family:ui-monospace, monospace; margin-top:3px;">
                  <span style="background:#fef3c7; color:#92400e; padding:3px 8px; border-radius:4px; border:1px solid #fde68a;">{ref_id}</span>
                </div>
              </td>
              <td align="right">
                <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.06em;">Received Timestamp</div>
                <div style="font-size:13px; font-weight:600; color:#334155; margin-top:3px;">
                  {datetime.now().strftime('%d %b %Y · %I:%M %p IST')}
                </div>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Main Body Content -->
      <tr>
        <td style="padding:26px 28px;">
          
          <div style="font-size:12px; font-weight:700; color:#07111e; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:6px;">
            📋 Customer &amp; Component Parameters
          </div>

          <!-- Parameters Table -->
          <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; margin-bottom:22px; font-size:14px;">
            <tr style="background-color:#f8fafc;">
              <td style="padding:10px 14px; width:34%; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;">👤 Contact Person</td>
              <td style="padding:10px 14px; width:66%; font-weight:700; color:#0f172a; border-bottom:1px solid #e2e8f0;">{name}</td>
            </tr>
            <tr>
              <td style="padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;">🏢 Company / OEM</td>
              <td style="padding:10px 14px; font-weight:700; color:#0f172a; border-bottom:1px solid #e2e8f0;">{company}</td>
            </tr>
            <tr style="background-color:#f8fafc;">
              <td style="padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;">✉️ Corporate Email</td>
              <td style="padding:10px 14px; border-bottom:1px solid #e2e8f0;">
                <a href="mailto:{email}" style="color:#2563eb; text-decoration:none; font-weight:600;">{email}</a>
              </td>
            </tr>
            <tr>
              <td style="padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;">📞 Phone / Mobile</td>
              <td style="padding:10px 14px; border-bottom:1px solid #e2e8f0;">
                <a href="tel:{phone}" style="color:#0f172a; text-decoration:none; font-weight:700;">{phone}</a>
              </td>
            </tr>
            <tr style="background-color:#f8fafc;">
              <td style="padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;">🎯 Requirement Focus</td>
              <td style="padding:10px 14px; border-bottom:1px solid #e2e8f0;">
                <span style="display:inline-block; padding:3px 10px; background:#e0f2fe; color:#0369a1; border-radius:4px; font-weight:700; font-size:13px;">
                  {cat_display}
                </span>
              </td>
            </tr>
            <tr>
              <td style="padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;">📊 Estimated Volume</td>
              <td style="padding:10px 14px; font-weight:700; color:#0f172a; border-bottom:1px solid #e2e8f0;">{quantity}</td>
            </tr>
            <tr style="background-color:#f8fafc;">
              <td style="padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;">📎 Technical Drawing</td>
              <td style="padding:10px 14px; border-bottom:1px solid #e2e8f0;">{att_display}</td>
            </tr>
          </table>

          <!-- Component Details & Technical Notes Box -->
          <div style="font-size:12px; font-weight:700; color:#07111e; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:8px;">
            📝 Technical Specifications &amp; Inquiry Notes:
          </div>
          <div style="background-color:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #efad2d; border-radius:6px; padding:16px 18px; font-size:14px; line-height:1.6; color:#1e293b; white-space:pre-wrap; margin-bottom:22px;">{message}</div>

          <!-- Quick Action Buttons for ERP Engineering Team -->
          <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top:10px;">
            <tr>
              <td align="center">
                <table border="0" cellpadding="0" cellspacing="0">
                  <tr>
                    <td style="border-radius:6px; background-color:#07111e;">
                      <a href="mailto:{email}?subject=RE:%20Sri%20Balaji%20Gears%20RFQ%20[{ref_id}]" target="_blank" style="display:inline-block; padding:11px 20px; font-size:12px; font-weight:700; color:#efad2d; text-decoration:none; border-radius:6px; letter-spacing:0.04em; text-transform:uppercase; border:1px solid #efad2d;">
                        ↩ Reply to Customer
                      </a>
                    </td>
                    <td width="10">&nbsp;</td>
                    <td style="border-radius:6px; background-color:#10b981;">
                      <a href="tel:{phone}" target="_blank" style="display:inline-block; padding:11px 20px; font-size:12px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:6px; letter-spacing:0.04em; text-transform:uppercase;">
                        📞 Call Customer
                      </a>
                    </td>
                    <td width="10">&nbsp;</td>
                    <td style="border-radius:6px; background-color:#f1f5f9; border:1px solid #cbd5e1;">
                      <a href="{app_url}/admin/index.php" target="_blank" style="display:inline-block; padding:11px 18px; font-size:12px; font-weight:700; color:#334155; text-decoration:none; border-radius:6px; letter-spacing:0.04em; text-transform:uppercase;">
                        🛡️ Admin Portal
                      </a>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>

        </td>
      </tr>

      <!-- Corporate Footer -->
      <tr>
        <td style="background-color:#07111e; padding:20px 28px; border-top:1px solid #1e293b; color:#94a3b8; font-size:12px; line-height:1.6; text-align:center;">
          <div style="font-weight:700; color:#f8fafc; font-size:13px; margin-bottom:4px;">SRI BALAJI GEARS</div>
          <div>SIPCOT Industrial Complex, Phase I · Hosur, Tamil Nadu 635126, India</div>
          <div style="color:#64748b; font-size:11px; margin-top:5px;">
            IATF 16949 Aligned · ISO 9001:2015 · Automated ERP Web Transmission System
          </div>
        </td>
      </tr>

    </table>
  </center>
</body>
</html>
        """
        msg.attach(MIMEText(html_body, "html", "utf-8"))

        if att_path and os.path.exists(att_path) and os.path.getsize(att_path) < 15 * 1024 * 1024:
            with open(att_path, "rb") as f_att:
                part = MIMEBase("application", "octet-stream")
                part.set_payload(f_att.read())
            encoders.encode_base64(part)
            part.add_header("Content-Disposition", f'attachment; filename="{os.path.basename(att_name)}"')
            msg.attach(part)

        # Connect to SMTP server
        if smtp_secure == "ssl" or smtp_port == 465:
            server = smtplib.SMTP_SSL(smtp_host, smtp_port, timeout=15)
        else:
            server = smtplib.SMTP(smtp_host, smtp_port, timeout=15)
            server.ehlo()
            if smtp_secure == "tls" or smtp_port == 587:
                server.starttls()
                server.ehlo()

        if smtp_user and smtp_pass:
            server.login(smtp_user, smtp_pass)

        # Send to Prabhu & ERP Desk
        all_recipients = list(set([erp_target, "prabhu@sribalajigears.com", "erp@sribalajigears.com"]))
        server.sendmail(from_email, all_recipients, msg.as_string())
        print(f"[SUCCESS] RFQ Notification sent to {all_recipients}")

        # Send confirmation email to Customer
        cust_msg = MIMEMultipart("alternative")
        cust_msg["From"] = f"{from_name} <{from_email}>"
        cust_msg["To"] = email
        cust_msg["Subject"] = f"RFQ Confirmation [{ref_id}] - Sri Balaji Gears"

        cust_html = f"""<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>RFQ Confirmation [{ref_id}] - Sri Balaji Gears</title>
</head>
<body style="margin:0; padding:0; background-color:#0b1320; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1e293b; -webkit-font-smoothing:antialiased;">
  <center style="width:100%; background-color:#0b1320; padding:24px 0;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:620px; background-color:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 10px 25px rgba(0,0,0,0.3); border:1px solid #1e293b;">
      
      <!-- Top Gold Accent Line -->
      <tr>
        <td height="4" style="background:linear-gradient(90deg, #d97706 0%, #f59e0b 50%, #fbbf24 100%); line-height:4px; font-size:4px;">&nbsp;</td>
      </tr>

      <!-- Corporate Header -->
      <tr>
        <td style="background-color:#07111e; padding:24px 28px; border-bottom:2px solid #efad2d;">
          <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td valign="middle">
                <div style="font-size:22px; font-weight:800; color:#ffffff; letter-spacing:0.04em;">
                  SRI BALAJI <span style="color:#efad2d;">GEARS</span>
                </div>
                <div style="font-size:11px; color:#94a3b8; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; margin-top:4px;">
                  Tier-1 Automotive Seating &amp; Chassis Welded Assemblies · Hosur
                </div>
              </td>
              <td align="right" valign="middle">
                <span style="display:inline-block; padding:6px 12px; background:rgba(16, 185, 129, 0.15); border:1px solid #10b981; border-radius:20px; color:#34d399; font-size:11px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase;">
                  ✓ INQUIRY RECEIVED
                </span>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Body Content -->
      <tr>
        <td style="padding:28px 28px; font-size:14px; line-height:1.6;">
          
          <p style="margin-top:0;">Dear <strong>{name}</strong>,</p>
          <p>Thank you for submitting your Request for Quotation (RFQ) for <strong>{company}</strong> through the official Sri Balaji Gears engineering portal.</p>
          <p>Our production planning and tool engineering team in SIPCOT Hosur has received your technical specifications:</p>

          <!-- Reference ID Box -->
          <div style="background-color:#f8fafc; border:1px dashed #cbd5e1; border-radius:6px; padding:14px 18px; margin:18px 0; text-align:center;">
            <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.06em;">Official Tracking Reference ID</div>
            <div style="font-size:18px; font-weight:800; color:#0f172a; font-family:ui-monospace, monospace; margin-top:4px;">
              <span style="background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:4px; border:1px solid #fde68a;">{ref_id}</span>
            </div>
          </div>

          <!-- Parameter Summary Table -->
          <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; margin-bottom:20px; font-size:13px; border:1px solid #e2e8f0; border-radius:6px;">
            <tr style="background-color:#f8fafc;">
              <td style="padding:10px 14px; width:38%; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;">🎯 Requirement Category</td>
              <td style="padding:10px 14px; font-weight:700; color:#0f172a; border-bottom:1px solid #e2e8f0;">{cat_display}</td>
            </tr>
            <tr>
              <td style="padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;">📊 Estimated Monthly Volume</td>
              <td style="padding:10px 14px; font-weight:700; color:#0f172a; border-bottom:1px solid #e2e8f0;">{quantity}</td>
            </tr>
            <tr style="background-color:#f8fafc;">
              <td style="padding:10px 14px; font-weight:600; color:#475569;">⚡ Target Turnaround SLA</td>
              <td style="padding:10px 14px; font-weight:700; color:#d97706;">Technical review &amp; quote within 24–48 hours</td>
            </tr>
          </table>

          <p>An application engineer has been assigned to inspect your component parameters and CAD drawings. We will reach out directly to <strong>{email}</strong> or <strong>{phone}</strong> with our technical appraisal and commercial estimate.</p>

          <p style="margin-top:24px; margin-bottom:0;">Warm regards,<br>
            <strong>Technical Sales &amp; ERP Engineering Team</strong><br>
            <span style="color:#64748b; font-size:13px;">Sri Balaji Gears · SIPCOT Hosur</span>
          </p>

        </td>
      </tr>

      <!-- Corporate Footer -->
      <tr>
        <td style="background-color:#07111e; padding:20px 28px; border-top:1px solid #1e293b; color:#94a3b8; font-size:12px; line-height:1.6; text-align:center;">
          <div style="font-weight:700; color:#f8fafc; font-size:13px; margin-bottom:4px;">SRI BALAJI GEARS</div>
          <div>SIPCOT Industrial Complex, Phase I · Hosur, Tamil Nadu 635126, India</div>
          <div style="margin-top:4px;">
            <strong>Hotline:</strong> <a href="tel:+917373334411" style="color:#efad2d; text-decoration:none;">+91 73733 34411</a> &middot; 
            <strong>Email:</strong> <a href="mailto:erp@sribalajigears.com" style="color:#efad2d; text-decoration:none;">erp@sribalajigears.com</a>
          </div>
        </td>
      </tr>

    </table>
  </center>
</body>
</html>"""
        cust_msg.attach(MIMEText(cust_html, "html", "utf-8"))
        server.sendmail(from_email, [email], cust_msg.as_string())
        print(f"[SUCCESS] Customer confirmation sent to {email}")

        server.quit()
        return True
    except Exception as e:
        print(f"[ERROR] SMTP dispatch error: {e}")
        return False

class SBGRequestHandler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=DIRECTORY, **kwargs)

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "POST, GET, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, X-Requested-With")
        self.send_header("Content-Length", "0")
        self.end_headers()

    def do_POST(self):
        if self.path.startswith("/api/enquiry.php"):
            try:
                content_length = int(self.headers.get("Content-Length", 0))
                content_type = self.headers.get("Content-Type", "")
                raw_body = self.rfile.read(content_length)

                fields = {}
                att_name = None
                att_path = None
                att_size = 0

                if "multipart/form-data" in content_type:
                    boundary = None
                    for part in content_type.split(";"):
                        part = part.strip()
                        if part.startswith("boundary="):
                            boundary = part.split("=", 1)[1].strip('"').encode("utf-8")
                            break

                    if boundary:
                        chunks = raw_body.split(b"--" + boundary)
                        for chunk in chunks:
                            if not chunk or chunk == b"--\r\n" or chunk == b"--":
                                continue
                            if b"\r\n\r\n" in chunk:
                                header_part, body_part = chunk.split(b"\r\n\r\n", 1)
                                body_part = body_part.rstrip(b"\r\n")
                                header_text = header_part.decode("utf-8", errors="ignore")

                                name_match = None
                                filename_match = None
                                for line in header_text.split("\r\n"):
                                    if "Content-Disposition:" in line:
                                        for token in line.split(";"):
                                            token = token.strip()
                                            if token.startswith("name="):
                                                name_match = token.split("=", 1)[1].strip('"')
                                            elif token.startswith("filename="):
                                                filename_match = token.split("=", 1)[1].strip('"')

                                if filename_match and name_match == "attachment" and len(body_part) > 0:
                                    safe_ext = os.path.splitext(filename_match)[1] or ".dat"
                                    unique_fname = f"upload_{datetime.now().strftime('%Y%m%d_%H%M%S')}_{uuid.uuid4().hex[:6]}{safe_ext}"
                                    save_dest = os.path.join(DIRECTORY, "uploads", unique_fname)
                                    with open(save_dest, "wb") as f_out:
                                        f_out.write(body_part)
                                    att_name = filename_match
                                    att_path = f"uploads/{unique_fname}"
                                    att_size = len(body_part)
                                elif name_match:
                                    fields[name_match] = body_part.decode("utf-8", errors="ignore")
                else:
                    parsed = urllib.parse.parse_qs(raw_body.decode("utf-8", errors="ignore"))
                    for k, v in parsed.items():
                        fields[k] = v[0] if v else ""

                if fields.get("website_hp"):
                    self.send_response(200)
                    self.send_header("Content-Type", "application/json; charset=utf-8")
                    self.end_headers()
                    self.wfile.write(json.dumps({"success": True, "reference_id": "SBG-RFQ-BOT"}).encode("utf-8"))
                    return

                ref_id = f"SBG-RFQ-{datetime.now().strftime('%Y%m%d')}-{uuid.uuid4().hex[:4].upper()}"

                db_path = os.path.join(DIRECTORY, "data", "enquiries.db")
                conn = sqlite3.connect(db_path)
                c = conn.cursor()
                c.execute("""
                    INSERT INTO enquiries (
                        reference_id, customer_name, company_name, email, phone,
                        requirement_category, custom_specification, volume_estimate,
                        message, attachment_name, attachment_path, attachment_size,
                        ip_address, user_agent, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """, (
                    ref_id,
                    fields.get("name", ""),
                    fields.get("company", ""),
                    fields.get("email", ""),
                    fields.get("phone", ""),
                    fields.get("projectType", "General RFQ"),
                    fields.get("otherCategory") if fields.get("projectType") == "Others" else None,
                    fields.get("quantity", "Not specified"),
                    fields.get("message", ""),
                    att_name,
                    att_path,
                    att_size,
                    self.client_address[0],
                    self.headers.get("User-Agent", "Unknown"),
                    "New"
                ))
                conn.commit()
                conn.close()

                # Trigger background SMTP email sending without blocking HTTP response
                email_thread = threading.Thread(
                    target=dispatch_emails,
                    args=(
                        ref_id,
                        fields.get("name", ""),
                        fields.get("company", ""),
                        fields.get("email", ""),
                        fields.get("phone", ""),
                        fields.get("projectType", "General RFQ"),
                        fields.get("otherCategory", ""),
                        fields.get("quantity", "Not specified"),
                        fields.get("message", ""),
                        att_name,
                        att_path
                    ),
                    daemon=True
                )
                email_thread.start()

                resp_obj = {
                    "success": True,
                    "message": "Thank you! Your enquiry has been successfully transmitted directly to our ERP engineering desk.",
                    "reference_id": ref_id
                }

                self.send_response(200)
                self.send_header("Access-Control-Allow-Origin", "*")
                self.send_header("Content-Type", "application/json; charset=utf-8")
                self.end_headers()
                self.wfile.write(json.dumps(resp_obj).encode("utf-8"))
            except Exception as e:
                self.send_response(500)
                self.send_header("Access-Control-Allow-Origin", "*")
                self.send_header("Content-Type", "application/json; charset=utf-8")
                self.end_headers()
                self.wfile.write(json.dumps({"success": False, "error": str(e)}).encode("utf-8"))
        else:
            self.send_response(404)
            self.end_headers()

def run_server():
    server_address = ("127.0.0.1", PORT)
    httpd = ThreadingHTTPServer(server_address, SBGRequestHandler)
    print(f"Sri Balaji Gears Local Server running at: http://localhost:{PORT}/")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        httpd.server_close()

if __name__ == "__main__":
    run_server()
