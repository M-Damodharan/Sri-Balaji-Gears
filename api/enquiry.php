<?php
/**
 * Sri Balaji Gears - RFQ Enquiry API Endpoint
 * Handles submission, file upload, database persistence, and email dispatch to erp@sribalajigears.com
 */

header('Content-Type: application/json; charset=utf-8');

// Require configuration
require_once dirname(__DIR__) . '/config/config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 1. Anti-Spam: Honeypot Check
    // If hidden 'website_hp' field is populated, silently abort (bot detected)
    if (!empty($_POST['website_hp'])) {
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for your enquiry.',
            'reference_id' => 'SBG-RFQ-BOT'
        ]);
        exit;
    }

    // 2. Anti-Spam: Rate Limiting by IP (Max 1 submission every 20 seconds)
    $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $clientIp = explode(',', $clientIp)[0];
    $clientIp = trim($clientIp);

    $rateCheck = $pdo->prepare("
        SELECT created_at FROM enquiries 
        WHERE ip_address = :ip 
        ORDER BY id DESC LIMIT 1
    ");
    $rateCheck->execute([':ip' => $clientIp]);
    $lastSubmission = $rateCheck->fetchColumn();

    if ($lastSubmission) {
        $lastTime = strtotime($lastSubmission);
        if (time() - $lastTime < 15) { // 15 seconds cooldown
            http_response_code(429);
            echo json_encode([
                'success' => false, 
                'error' => 'Please wait a few seconds before submitting another enquiry.'
            ]);
            exit;
        }
    }

    // 3. Extract and Sanitize Inputs
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $company = trim(filter_input(INPUT_POST, 'company', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $projectType = trim(filter_input(INPUT_POST, 'projectType', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $otherCategory = trim(filter_input(INPUT_POST, 'otherCategory', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $quantity = trim(filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_SPECIAL_CHARS) ?? 'Not specified');
    $message = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

    // Validation
    $errors = [];
    if (strlen($name) < 2) {
        $errors[] = 'Please enter a valid contact person name.';
    }
    if (strlen($company) < 2) {
        $errors[] = 'Please enter your company or OEM name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid corporate email address.';
    }
    if (strlen(preg_replace('/[^0-9]/', '', $phone)) < 8) {
        $errors[] = 'Please enter a valid mobile or telephone number.';
    }
    if (empty($projectType)) {
        $errors[] = 'Please select a requirement category.';
    }
    if ($projectType === 'Others' && empty($otherCategory)) {
        $errors[] = 'Please specify your custom requirement category.';
    }
    if (strlen($message) < 5) {
        $errors[] = 'Please provide component specifications or details (minimum 5 characters).';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
        exit;
    }

    // 4. Handle File Attachment
    $attachmentName = null;
    $attachmentPath = null;
    $attachmentSize = 0;
    $hasAttachment = false;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['attachment'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'File upload encountered an error. Code: ' . $file['error']]);
            exit;
        }

        // Validate max size (default 15 MB)
        $maxBytes = ((int)config('MAX_UPLOAD_SIZE_MB', 15)) * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Uploaded file exceeds maximum limit of ' . config('MAX_UPLOAD_SIZE_MB', 15) . 'MB.']);
            exit;
        }

        // Validate allowed extensions
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'zip', 'dxf', 'dwg', 'step', 'stp', 'iges', 'igs'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unsupported file format (.' . htmlspecialchars($ext) . '). Allowed: PDF, CAD drawings (DWG/DXF/STEP), documents, and images.']);
            exit;
        }

        // Generate safe unique filename
        $safeOriginalName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $uniqueName = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '_' . substr($safeOriginalName, 0, 40) . '.' . $ext;
        
        $uploadDir = dirname(__DIR__) . '/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $targetPath = $uploadDir . '/' . $uniqueName;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $attachmentName = $file['name'];
            $attachmentPath = 'uploads/' . $uniqueName;
            $attachmentSize = $file['size'];
            $hasAttachment = true;
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file on server. Check folder permissions.']);
            exit;
        }
    }

    // 5. Generate Reference ID & Save to Database
    $refId = 'SBG-RFQ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

    $stmt = $pdo->prepare("
        INSERT INTO enquiries (
            reference_id, customer_name, company_name, email, phone,
            requirement_category, custom_specification, volume_estimate,
            message, attachment_name, attachment_path, attachment_size,
            ip_address, user_agent, status, created_at
        ) VALUES (
            :ref, :name, :company, :email, :phone,
            :cat, :custom, :vol,
            :msg, :att_name, :att_path, :att_size,
            :ip, :ua, 'New', CURRENT_TIMESTAMP
        )
    ");

    $stmt->execute([
        ':ref' => $refId,
        ':name' => $name,
        ':company' => $company,
        ':email' => $email,
        ':phone' => $phone,
        ':cat' => $projectType,
        ':custom' => ($projectType === 'Others' ? $otherCategory : null),
        ':vol' => $quantity,
        ':msg' => $message,
        ':att_name' => $attachmentName,
        ':att_path' => $attachmentPath,
        ':att_size' => $attachmentSize,
        ':ip' => $clientIp,
        ':ua' => $userAgent,
    ]);

    // 6. Send Emails
    $destEmail = config('ERP_EMAIL', 'erp@sribalajigears.com');
    $appUrl = rtrim(config('APP_URL', 'https://www.sribalajigears.in'), '/');
    $attachmentAbsPath = $hasAttachment ? dirname(__DIR__) . '/' . $attachmentPath : null;

    // Send ERP Notification Email
    sendErpNotificationEmail(
        $destEmail, $refId, $name, $company, $email, $phone,
        $projectType, $otherCategory, $quantity, $message,
        $attachmentName, $attachmentAbsPath, $appUrl
    );

    // Send Customer Acknowledgment Email
    sendCustomerConfirmationEmail(
        $email, $name, $company, $refId,
        $projectType, $otherCategory, $quantity, $message
    );

    // Return Success
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your enquiry has been successfully transmitted directly to our ERP engineering desk.',
        'reference_id' => $refId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while processing your request. Please email directly to ' . config('ERP_EMAIL', 'erp@sribalajigears.com')
    ]);
}

/**
 * Dispatch notification email to erp@sribalajigears.com
 */
function sendErpNotificationEmail($to, $refId, $name, $company, $email, $phone, $category, $customCategory, $volume, $message, $attName, $attPath, $appUrl) {
    $categoryDisplay = $category === 'Others' ? "Others ({$customCategory})" : $category;
    $subject = "New Automotive RFQ [{$refId}] - {$company} ({$categoryDisplay})";

    $downloadLink = $attName ? "{$appUrl}/admin/index.php" : "None";

    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='utf-8'>
      <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background:#f4f6f9; color:#1e293b; padding:24px; margin:0; }
        .wrapper { max-width:640px; margin:0 auto; background:#ffffff; border-radius:8px; border:1px solid #e2e8f0; overflow:hidden; }
        .header { background:#07111e; padding:20px 28px; border-bottom:3px solid #efad2d; color:#ffffff; }
        .header h2 { margin:0; font-size:20px; color:#efad2d; }
        .header p { margin:4px 0 0; font-size:12px; color:#94a3b8; }
        .body { padding:28px; }
        .badge { display:inline-block; padding:4px 10px; background:#fef3c7; color:#92400e; font-size:12px; font-weight:700; border-radius:4px; margin-bottom:18px; }
        .grid { width:100%; border-collapse:collapse; margin-bottom:20px; }
        .grid td { padding:10px 12px; border-bottom:1px solid #f1f5f9; font-size:14px; }
        .grid td.label { width:35%; font-weight:600; color:#475569; background:#f8fafc; }
        .grid td.val { width:65%; color:#0f172a; }
        .msg-box { background:#f8fafc; border-left:4px solid #efad2d; padding:16px; border-radius:4px; font-size:14px; line-height:1.6; white-space:pre-wrap; }
        .footer { background:#f8fafc; padding:16px 28px; border-top:1px solid #e2e8f0; font-size:12px; color:#64748b; text-align:center; }
      </style>
    </head>
    <body>
      <div class='wrapper'>
        <div class='header'>
          <h2>SRI BALAJI GEARS - ERP INQUIRY NOTIFICATION</h2>
          <p>Automated RFQ Transmitted via Website Portal</p>
        </div>
        <div class='body'>
          <span class='badge'>NEW CUSTOMER RFQ: {$refId}</span>
          <table class='grid'>
            <tr><td class='label'>Reference ID</td><td class='val'><strong>{$refId}</strong></td></tr>
            <tr><td class='label'>Contact Person</td><td class='val'><strong>{$name}</strong></td></tr>
            <tr><td class='label'>Company / OEM</td><td class='val'><strong>{$company}</strong></td></tr>
            <tr><td class='label'>Corporate Email</td><td class='val'><a href='mailto:{$email}'>{$email}</a></td></tr>
            <tr><td class='label'>Phone / Mobile</td><td class='val'><a href='tel:{$phone}'>{$phone}</a></td></tr>
            <tr><td class='label'>Requirement Focus</td><td class='val'><strong>{$categoryDisplay}</strong></td></tr>
            <tr><td class='label'>Estimated Volume</td><td class='val'>{$volume}</td></tr>
            <tr><td class='label'>Attachment</td><td class='val'>" . ($attName ? "<strong>" . htmlspecialchars($attName) . "</strong>" : "None provided") . "</td></tr>
            <tr><td class='label'>Submitted Time</td><td class='val'>" . date('d M Y, h:i A T') . "</td></tr>
          </table>
          <p style='font-size:13px; font-weight:600; color:#334155; margin-bottom:8px;'>Component Details &amp; Technical Requirements:</p>
          <div class='msg-box'>" . nl2br(htmlspecialchars($message)) . "</div>
        </div>
        <div class='footer'>
          Sri Balaji Gears &middot; SIPCOT Phase I, Hosur, Tamil Nadu &middot; <a href='mailto:{$email}'>Click here to Reply to Customer</a>
        </div>
      </div>
    </body>
    </html>
    ";

    sendMailMultipart($to, $subject, $htmlBody, $email, $name, $attName, $attPath);
}

/**
 * Dispatch confirmation email to customer
 */
function sendCustomerConfirmationEmail($to, $name, $company, $refId, $category, $customCategory, $volume, $message) {
    $categoryDisplay = $category === 'Others' ? "Others ({$customCategory})" : $category;
    $subject = "RFQ Confirmation [{$refId}] - Sri Balaji Gears Hosur";

    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='utf-8'>
      <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background:#f4f6f9; color:#1e293b; padding:24px; margin:0; }
        .wrapper { max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; border:1px solid #e2e8f0; overflow:hidden; }
        .header { background:#07111e; padding:24px 28px; border-bottom:3px solid #efad2d; color:#ffffff; }
        .header h2 { margin:0; font-size:22px; color:#efad2d; }
        .header p { margin:6px 0 0; font-size:13px; color:#cbd5e1; }
        .body { padding:28px; line-height:1.6; font-size:14px; }
        .ref-box { background:#f8fafc; border:1px dashed #cbd5e1; padding:12px 16px; border-radius:6px; margin:16px 0; font-family:monospace; font-size:15px; font-weight:700; color:#0f172a; text-align:center; }
        .info-card { background:#f8fafc; border-radius:6px; padding:16px; margin:16px 0; font-size:13px; }
        .info-card p { margin:4px 0; }
        .footer { background:#f8fafc; padding:20px 28px; border-top:1px solid #e2e8f0; font-size:12px; color:#64748b; line-height:1.6; }
      </style>
    </head>
    <body>
      <div class='wrapper'>
        <div class='header'>
          <h2>SRI BALAJI GEARS</h2>
          <p>Automotive Welding &amp; Seating Systems Manufacturer &middot; Hosur</p>
        </div>
        <div class='body'>
          <p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
          <p>Thank you for submitting your Request for Quotation (RFQ) on the official Sri Balaji Gears portal for <strong>" . htmlspecialchars($company) . "</strong>.</p>
          <p>Our engineering and production planning team in SIPCOT Hosur has received your technical specifications:</p>
          <div class='ref-box'>RFQ Reference: {$refId}</div>
          <div class='info-card'>
            <p><strong>Requirement Focus:</strong> {$categoryDisplay}</p>
            <p><strong>Estimated Volume:</strong> {$volume}</p>
            <p><strong>Target SLA:</strong> Review &amp; response within 24 business hours</p>
          </div>
          <p>An application engineer will review your drawings and component parameters and contact you directly.</p>
          <p style='margin-top:24px;'>Warm regards,<br><strong>Technical Sales &amp; ERP Team</strong><br>Sri Balaji Gears</p>
        </div>
        <div class='footer'>
          <strong>Sri Balaji Gears</strong><br>
          SIPCOT Industrial Complex, Phase I, Hosur, Tamil Nadu 635126, India<br>
          Phone: +91 87782 78761 &middot; Email: erp@sribalajigears.com &middot; Web: www.sribalajigears.in
        </div>
      </div>
    </body>
    </html>
    ";

    sendMailMultipart($to, $subject, $htmlBody, config('ERP_EMAIL', 'erp@sribalajigears.com'), 'Sri Balaji Gears');
}

/**
 * Universal MIME multipart mail sender
 */
function sendMailMultipart($to, $subject, $htmlContent, $replyToEmail = null, $replyToName = null, $attName = null, $attPath = null) {
    $fromEmail = config('SMTP_FROM_EMAIL', config('ERP_EMAIL', 'erp@sribalajigears.com'));
    $fromName = config('SMTP_FROM_NAME', 'Sri Balaji Gears');

    $boundary = '==Multipart_Boundary_x' . md5(time()) . 'x';

    // Headers
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = "From: {$fromName} <{$fromEmail}>";
    if ($replyToEmail) {
        $headers[] = "Reply-To: " . ($replyToName ? "{$replyToName} <{$replyToEmail}>" : $replyToEmail);
    }
    $headers[] = 'X-Mailer: SBG-Mailer/1.0';
    $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

    // Body
    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $htmlContent . "\r\n";

    // Attachment
    if ($attName && $attPath && file_exists($attPath) && filesize($attPath) < 10 * 1024 * 1024) {
        $fileData = file_get_contents($attPath);
        $fileChunk = chunk_split(base64_encode($fileData));
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/octet-stream; name=\"" . basename($attName) . "\"\r\n";
        $body .= "Content-Description: " . basename($attName) . "\r\n";
        $body .= "Content-Disposition: attachment; filename=\"" . basename($attName) . "\"; size=" . strlen($fileData) . ";\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= $fileChunk . "\r\n";
    }

    $body .= "--{$boundary}--";

    // Send using mail()
    return @mail($to, $subject, $body, implode("\r\n", $headers));
}
