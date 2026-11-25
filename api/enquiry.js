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

    // Prepare Brevo email payload (configured via Vercel Environment Variables)
    const brevoApiKey = process.env.BREVO_API_KEY;
    const targetEmail = process.env.ERP_EMAIL || 'erp@sribalajigears.com';

    const htmlContent = `
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

    // Dispatch via Brevo API if key configured
    if (brevoApiKey) {
      try {
        const emailPayload = {
          sender: { name: 'Sri Balaji Gears RFQ Portal', email: 'erp@sribalajigears.com' },
          to: [{ email: targetEmail }],
          replyTo: { email: email, name: name },
          subject: `[NEW RFQ] ${category} - ${company || name} [${referenceId}]`,
          htmlContent: htmlContent
        };

        if (fileAttachment && fileAttachment.data) {
          emailPayload.attachment = [{
            name: fileAttachment.filename,
            content: fileAttachment.data.toString('base64')
          }];
        }

        await fetch('https://api.brevo.com/v3/smtp/email', {
          method: 'POST',
          headers: {
            'api-key': brevoApiKey,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(emailPayload)
        });
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
