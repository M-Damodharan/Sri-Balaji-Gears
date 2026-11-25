export const config = {
  api: {
    bodyParser: false
  }
};

async function getRawBody(req) {
  const chunks = [];
  for await (const chunk of req) {
    chunks.push(typeof chunk === 'string' ? Buffer.from(chunk) : chunk);
  }
  return Buffer.concat(chunks);
}

function parseMultipart(buffer, boundary) {
  const result = {};
  const str = buffer.toString('latin1');
  const parts = str.split('--' + boundary);
  for (const part of parts) {
    const match = part.match(/Content-Disposition:\s*form-data;\s*name="([^"]+)"(?:;\s*filename="([^"]+)")?/i);
    if (!match) continue;
    const name = match[1];
    const filename = match[2];
    const headerEndIndex = part.indexOf('\r\n\r\n');
    if (headerEndIndex === -1) continue;
    let data = part.substring(headerEndIndex + 4);
    if (data.endsWith('\r\n')) data = data.slice(0, -2);
    if (filename) {
      result[name] = {
        filename: filename,
        data: Buffer.from(data, 'latin1')
      };
    } else {
      result[name] = Buffer.from(data, 'latin1').toString('utf-8').trim();
    }
  }
  return result;
}

export default async function handler(req, res) {
  // Enterprise CORS & Security Headers
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  if (req.method !== 'POST') {
    return res.status(405).json({ success: false, error: 'Method Not Allowed' });
  }

  try {
    const rawBuffer = await getRawBody(req);
    const contentType = req.headers['content-type'] || '';
    let body = {};
    let fileAttachment = null;

    if (contentType.includes('multipart/form-data')) {
      const boundaryMatch = contentType.match(/boundary=(?:["']?)([^"';]+)/i);
      if (boundaryMatch) {
        body = parseMultipart(rawBuffer, boundaryMatch[1]);
        if (body.attachment && body.attachment.filename) {
          fileAttachment = body.attachment;
        } else if (body.drawing && body.drawing.filename) {
          fileAttachment = body.drawing;
        }
      }
    } else if (contentType.includes('application/json')) {
      body = JSON.parse(rawBuffer.toString('utf-8') || '{}');
    } else {
      const str = rawBuffer.toString('utf-8');
      body = Object.fromEntries(new URLSearchParams(str));
    }

    // 1. Anti-spam honeypot
    if (body.website_hp) {
      return res.status(200).json({
        success: true,
        message: 'Thank you for your enquiry.',
        reference_id: 'SBG-RFQ-BOT'
      });
    }

    const name = (body.name || '').trim();
    const company = (body.company || '').trim();
    const email = (body.email || '').trim();
    const phone = (body.phone || '').trim();
    const category = (body.category || 'General Engineering RFQ').trim();
    const specifications = (body.specifications || '').trim();
    const volume = (body.volume || body.quantity || 'Not specified').trim();
    const message = (body.message || '').trim();

    if (!name || !email || !phone) {
      return res.status(400).json({
        success: false,
        error: 'Please fill in all required fields (Name, Email, and Phone).'
      });
    }

    // Generate unique reference ID: SBG-RFQ-YYYYMMDD-XXXX
    const now = new Date();
    const dateStr = now.toISOString().slice(0, 10).replace(/-/g, '');
    const randStr = Math.floor(1000 + Math.random() * 9000).toString();
    const referenceId = `SBG-RFQ-${dateStr}-${randStr}`;

    const brevoApiKey = process.env.BREVO_API_KEY;
    const targetEmail = process.env.ERP_EMAIL || 'erp@sribalajigears.com';

    // 2. Email HTML Template for Plant / Engineering Desk
    const plantHtml = `
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #0d121c; color: #ffffff; padding: 20px; }
    .card { background: #161e2e; border: 1px solid #d99a26; border-radius: 8px; max-width: 600px; margin: 0 auto; overflow: hidden; }
    .header { background: linear-gradient(135deg, #1f293d, #111723); padding: 24px; text-align: center; border-bottom: 2px solid #efad2d; }
    .header h1 { margin: 0; color: #efad2d; font-size: 22px; letter-spacing: 1px; }
    .header p { margin: 4px 0 0 0; color: #94a3b8; font-size: 13px; text-transform: uppercase; }
    .body { padding: 24px; }
    .ref-badge { background: #232f45; border: 1px solid #3b4d6b; padding: 10px 16px; border-radius: 6px; margin-bottom: 20px; font-family: monospace; color: #efad2d; font-size: 14px; text-align: center; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    td { padding: 10px 8px; border-bottom: 1px solid #243044; font-size: 14px; }
    td.label { width: 35%; color: #94a3b8; font-weight: bold; }
    td.value { color: #ffffff; }
    .message-box { background: #0b0f17; border-left: 3px solid #efad2d; padding: 14px; margin-top: 15px; border-radius: 4px; font-size: 14px; line-height: 1.5; color: #e2e8f0; }
    .footer { padding: 16px; text-align: center; font-size: 12px; color: #64748b; background: #0f1523; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h1>SRI BALAJI GEARS</h1>
      <p>Automated Production RFQ Transmission</p>
    </div>
    <div class="body">
      <div class="ref-badge">REFERENCE ID: <strong>${referenceId}</strong></div>
      <table>
        <tr><td class="label">Client Name</td><td class="value"><strong>${name}</strong></td></tr>
        <tr><td class="label">Company / Unit</td><td class="value">${company || 'Not specified'}</td></tr>
        <tr><td class="label">Work Email</td><td class="value"><a href="mailto:${email}" style="color:#efad2d;">${email}</a></td></tr>
        <tr><td class="label">Phone / WhatsApp</td><td class="value"><a href="tel:${phone}" style="color:#efad2d;">${phone}</a></td></tr>
        <tr><td class="label">Requirement Category</td><td class="value"><strong>${category}</strong></td></tr>
        <tr><td class="label">Technical Specifications</td><td class="value">${specifications || 'Standard specifications'}</td></tr>
        <tr><td class="label">Volume / Batch Size</td><td class="value">${volume}</td></tr>
        ${fileAttachment ? `<tr><td class="label">Uploaded Drawing</td><td class="value">📎 ${fileAttachment.filename}</td></tr>` : ''}
      </table>
      <div style="margin-top: 20px; font-weight: bold; color: #efad2d; font-size: 13px; text-transform: uppercase;">Technical Scope & Message</div>
      <div class="message-box">${message.replace(/\n/g, '<br>') || 'No additional message provided.'}</div>
    </div>
    <div class="footer">
      Sri Balaji Gears RFQ Desk · Hosur, Tamil Nadu · Direct delivery via Vercel Production
    </div>
  </div>
</body>
</html>
    `;

    // 3. Email HTML Template for Customer Confirmation Receipt
    const clientHtml = `
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Thank You for Your RFQ [${referenceId}] - Sri Balaji Gears</title>
</head>
<body style="margin:0; padding:0; background-color:#0b1320; font-family:'Segoe UI', Roboto, Arial, sans-serif; color:#1e293b;">
  <center style="width:100%; background-color:#0b1320; padding:24px 0;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:620px; background-color:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 10px 25px rgba(0,0,0,0.3); border:1px solid #1e293b;">
      <tr>
        <td height="4" style="background:linear-gradient(90deg, #d97706 0%, #f59e0b 50%, #fbbf24 100%); line-height:4px; font-size:4px;">&nbsp;</td>
      </tr>
      <tr>
        <td style="background-color:#07111e; padding:24px 28px; border-bottom:2px solid #efad2d;">
          <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td>
                <div style="font-size:22px; font-weight:800; color:#ffffff; letter-spacing:0.04em;">
                  SRI BALAJI <span style="color:#efad2d;">GEARS</span>
                </div>
                <div style="font-size:11px; color:#94a3b8; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; margin-top:4px;">
                  Tier-1 Automotive Seating &amp; Chassis Welded Assemblies · Hosur
                </div>
              </td>
              <td align="right">
                <span style="display:inline-block; padding:6px 12px; background:rgba(16, 185, 129, 0.15); border:1px solid #10b981; border-radius:20px; color:#34d399; font-size:11px; font-weight:700; text-transform:uppercase;">
                  ✓ RFQ LOGGED
                </span>
              </td>
            </tr>
          </table>
        </td>
      </tr>
      <tr>
        <td style="background-color:#f8fafc; padding:18px 28px; border-bottom:1px solid #e2e8f0;">
          <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td>
                <div style="font-size:11px; font-weight:800; color:#b45309; text-transform:uppercase; letter-spacing:0.08em;">OFFICIAL TRACKING REFERENCE ID</div>
                <div style="font-size:19px; font-weight:900; color:#0f172a; font-family:monospace; margin-top:4px;">
                  <span style="background:#fef3c7; color:#92400e; padding:4px 12px; border-radius:6px; border:1px solid #fde68a;">${referenceId}</span>
                </div>
              </td>
              <td align="right">
                <div style="font-size:11px; font-weight:800; color:#065f46; text-transform:uppercase; letter-spacing:0.08em;">REVIEW STATUS</div>
                <div style="font-size:13px; font-weight:800; color:#059669; margin-top:4px;">
                  Under Engineering Appraisal (24–48h)
                </div>
              </td>
            </tr>
          </table>
        </td>
      </tr>
      <tr>
        <td style="padding:28px 28px 20px 28px; line-height:1.6; color:#334155; font-size:14px;">
          <p style="margin:0 0 16px 0; font-size:15px; color:#0f172a;">
            Dear <strong>${name}</strong>,
          </p>
          <p style="margin:0 0 16px 0;">
            Thank you for reaching out to <strong>Sri Balaji Gears</strong>. Your quotation enquiry for <strong>${category}</strong> has been logged in our engineering desk.
          </p>
          <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#f1f5f9; border-radius:8px; padding:12px; margin-bottom:20px;">
            <tr><td style="padding:6px 10px; font-weight:bold; color:#475569; width:35%;">Company:</td><td style="padding:6px 10px; color:#0f172a;">${company || 'Not specified'}</td></tr>
            <tr><td style="padding:6px 10px; font-weight:bold; color:#475569;">Category:</td><td style="padding:6px 10px; color:#0f172a;">${category}</td></tr>
            <tr><td style="padding:6px 10px; font-weight:bold; color:#475569;">Volume / Batch:</td><td style="padding:6px 10px; color:#0f172a;">${volume}</td></tr>
            ${fileAttachment ? `<tr><td style="padding:6px 10px; font-weight:bold; color:#475569;">Technical Drawing:</td><td style="padding:6px 10px; color:#059669; font-weight:bold;">📎 ${fileAttachment.filename} (Received)</td></tr>` : ''}
          </table>
          <p style="margin:0 0 8px 0; font-size:13px; color:#64748b;">
            Our technical review team is evaluating your requirements against our press fleet (50T–300T) and robotic welding capabilities. You will receive our formal quotation within <strong>24 to 48 business hours</strong>.
          </p>
        </td>
      </tr>
      <tr>
        <td style="background-color:#07111e; padding:20px 28px; text-align:center; color:#94a3b8; font-size:12px;">
          <div style="color:#ffffff; font-weight:700; font-size:13px; margin-bottom:4px;">Sri Balaji Gears · Manufacturing Facility</div>
          <div>SF No. 138/4, Belagondapalli Village, Thally Road, Denkanikottai Taluk, Krishnagiri Dist, Tamil Nadu - 635114</div>
          <div style="margin-top:6px; color:#efad2d;">Direct Engineering Line: +91 73733 34411 · erp@sribalajigears.com</div>
        </td>
      </tr>
    </table>
  </center>
</body>
</html>
    `;

    // 4. Dispatch Emails via Brevo API
    if (brevoApiKey) {
      try {
        // A) Email to Plant & Senior Management (with CC to damodharan390@gmail.com for redundancy)
        const plantEmailPayload = {
          sender: { name: 'Sri Balaji Gears RFQ Portal', email: 'erp@sribalajigears.com' },
          to: [{ email: targetEmail }],
          cc: [{ email: 'damodharan390@gmail.com' }],
          replyTo: { email: email, name: name },
          subject: `[NEW RFQ] ${category} - ${company || name} [${referenceId}]`,
          htmlContent: plantHtml
        };

        if (fileAttachment && fileAttachment.data) {
          plantEmailPayload.attachment = [{
            name: fileAttachment.filename,
            content: fileAttachment.data.toString('base64')
          }];
        }

        const plantPromise = fetch('https://api.brevo.com/v3/smtp/email', {
          method: 'POST',
          headers: {
            'api-key': brevoApiKey,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(plantEmailPayload)
        });

        // B) Official Confirmation Receipt to the Customer
        const clientEmailPayload = {
          sender: { name: 'Sri Balaji Gears', email: 'erp@sribalajigears.com' },
          to: [{ email: email, name: name }],
          replyTo: { name: 'Sri Balaji Gears Engineering Desk', email: 'erp@sribalajigears.com' },
          subject: `Quotation Enquiry Received [${referenceId}] | Sri Balaji Gears`,
          htmlContent: clientHtml
        };

        const clientPromise = fetch('https://api.brevo.com/v3/smtp/email', {
          method: 'POST',
          headers: {
            'api-key': brevoApiKey,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(clientEmailPayload)
        });

        // Execute both concurrently
        await Promise.allSettled([plantPromise, clientPromise]);
      } catch (mailErr) {
        console.error('Brevo API dispatch warning:', mailErr);
      }
    }

    return res.status(200).json({
      success: true,
      reference_id: referenceId,
      message: 'Thank you! Your quotation enquiry has been successfully transmitted to our engineering team.'
    });
  } catch (err) {
    console.error('API Error:', err);
    return res.status(500).json({
      success: false,
      error: 'An internal error occurred while processing your enquiry. Please reach us at +91 73733 34411.'
    });
  }
}
