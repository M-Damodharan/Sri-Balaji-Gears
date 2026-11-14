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
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.mime.base import MIMEBase
from email import encoders
from datetime import datetime
from http.server import HTTPServer, SimpleHTTPRequestHandler

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

        html_body = f"""
        <html>
        <body style="font-family:Arial,sans-serif; background:#f4f6f9; padding:20px;">
          <div style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; border:1px solid #e2e8f0; overflow:hidden;">
            <div style="background:#07111e; padding:20px; border-bottom:3px solid #efad2d; color:#ffffff;">
              <h2 style="margin:0; color:#efad2d; font-size:20px;">SRI BALAJI GEARS - RFQ INQUIRY</h2>
              <p style="margin:4px 0 0; color:#94a3b8; font-size:12px;">Automated RFQ Notification</p>
            </div>
            <div style="padding:24px;">
              <p><strong>Reference ID:</strong> {ref_id}</p>
              <p><strong>Contact Person:</strong> {name}</p>
              <p><strong>Company / OEM:</strong> {company}</p>
              <p><strong>Email:</strong> <a href="mailto:{email}">{email}</a></p>
              <p><strong>Phone:</strong> <a href="tel:{phone}">{phone}</a></p>
              <p><strong>Requirement:</strong> {cat_display}</p>
              <p><strong>Estimated Volume:</strong> {quantity}</p>
              <p><strong>Attachment:</strong> {att_name or 'None provided'}</p>
              <hr style="border:0; border-top:1px solid #e2e8f0; margin:16px 0;">
              <p><strong>Specifications & Requirements:</strong></p>
              <div style="background:#f8fafc; padding:12px; border-left:4px solid #efad2d; border-radius:4px; white-space:pre-wrap;">{message}</div>
            </div>
          </div>
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

        # Send to ERP Desk
        server.sendmail(from_email, [erp_target], msg.as_string())
        print(f"[SUCCESS] RFQ Notification sent to {erp_target}")

        # Send confirmation email to Customer
        cust_msg = MIMEMultipart("alternative")
        cust_msg["From"] = f"{from_name} <{from_email}>"
        cust_msg["To"] = email
        cust_msg["Subject"] = f"RFQ Confirmation [{ref_id}] - Sri Balaji Gears"

        cust_html = f"""
        <html>
        <body style="font-family:Arial,sans-serif; background:#f4f6f9; padding:20px;">
          <div style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; border:1px solid #e2e8f0; padding:24px;">
            <h2 style="color:#07111e; margin-top:0;">SRI BALAJI GEARS</h2>
            <p>Dear <strong>{name}</strong>,</p>
            <p>Thank you for submitting your Request for Quotation (RFQ) for <strong>{company}</strong>.</p>
            <p>Your inquiry has been registered with reference ID: <strong>{ref_id}</strong>.</p>
            <p>Our engineering team in SIPCOT Hosur will review your drawings and respond within 24–48 hours.</p>
            <br>
            <p>Best regards,<br><strong>Sri Balaji Gears Team</strong><br>SIPCOT Phase I, Hosur, Tamil Nadu</p>
          </div>
        </body>
        </html>
        """
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

                # Trigger SMTP email sending
                dispatch_emails(
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
                )

                resp_obj = {
                    "success": True,
                    "message": "Thank you! Your enquiry has been successfully transmitted directly to our ERP engineering desk.",
                    "reference_id": ref_id
                }

                self.send_response(200)
                self.send_header("Content-Type", "application/json; charset=utf-8")
                self.end_headers()
                self.wfile.write(json.dumps(resp_obj).encode("utf-8"))
            except Exception as e:
                self.send_response(500)
                self.send_header("Content-Type", "application/json; charset=utf-8")
                self.end_headers()
                self.wfile.write(json.dumps({"success": False, "error": str(e)}).encode("utf-8"))
        else:
            self.send_response(404)
            self.end_headers()

def run_server():
    server_address = ("127.0.0.1", PORT)
    httpd = HTTPServer(server_address, SBGRequestHandler)
    print(f"Sri Balaji Gears Local Server running at: http://localhost:{PORT}/")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        httpd.server_close()

if __name__ == "__main__":
    run_server()
