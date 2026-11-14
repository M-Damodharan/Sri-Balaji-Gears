<?php
/**
 * Sri Balaji Gears - RFQ Enquiry API Endpoint
 * Handles submission, file upload, database persistence, and email dispatch to erp@sribalajigears.com
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

    // 3. Extract and Sanitize Inputs (direct $_POST with fallback to filter_input)
    $name = trim($_POST['name'] ?? filter_input(INPUT_POST, 'name') ?? '');
    $company = trim($_POST['company'] ?? filter_input(INPUT_POST, 'company') ?? '');
    $email = trim($_POST['email'] ?? filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $phone = trim($_POST['phone'] ?? filter_input(INPUT_POST, 'phone') ?? '');
    $projectType = trim($_POST['projectType'] ?? filter_input(INPUT_POST, 'projectType') ?? '');
    $otherCategory = trim($_POST['otherCategory'] ?? filter_input(INPUT_POST, 'otherCategory') ?? '');
    $quantity = trim($_POST['quantity'] ?? filter_input(INPUT_POST, 'quantity') ?? 'Not specified');
    $message = trim($_POST['message'] ?? filter_input(INPUT_POST, 'message') ?? '');
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

    $uploadedFile = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadedFile = $_FILES['attachment'];
    } elseif (isset($_FILES['drawing']) && $_FILES['drawing']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadedFile = $_FILES['drawing'];
    }

    if ($uploadedFile) {
        $file = $uploadedFile;

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
    $appUrl = rtrim(config('APP_URL', 'https://www.sribalajigears.in'), '/');
    $attachmentAbsPath = $hasAttachment ? dirname(__DIR__) . '/' . $attachmentPath : null;

    // Send Notification Email to Prabhu & ERP desk simultaneously in one high-speed connection
    $plantRecipients = array_values(array_unique([
        config('ERP_EMAIL', 'prabhu@sribalajigears.com'),
        'prabhu@sribalajigears.com',
        'erp@sribalajigears.com'
    ]));

    sendErpNotificationEmail(
        $plantRecipients, $refId, $name, $company, $email, $phone,
        $projectType, $otherCategory, $quantity, $message,
        $attachmentName, $attachmentAbsPath, $appUrl
    );

    // Send Customer "Thank You" Confirmation Email with Tracking Reference ID & Details
    sendCustomerConfirmationEmail(
        $email, $name, $company, $phone, $refId,
        $projectType, $otherCategory, $quantity, $message,
        $attachmentName
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

    $htmlBody = "
<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>
<html xmlns='http://www.w3.org/1999/xhtml'>
<head>
  <meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
  <meta name='viewport' content='width=device-width, initial-scale=1.0'/>
  <title>New Automotive RFQ [{$refId}]</title>
</head>
<body style='margin:0; padding:0; background-color:#0b1320; font-family:-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color:#1e293b; -webkit-font-smoothing:antialiased;'>
  <center style='width:100%; background-color:#0b1320; padding:24px 0;'>
    <table border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width:650px; background-color:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 10px 25px rgba(0,0,0,0.3); border:1px solid #1e293b;'>
      
      <!-- Top Gold Accent Line -->
      <tr>
        <td height='4' style='background:linear-gradient(90deg, #d97706 0%, #f59e0b 50%, #fbbf24 100%); line-height:4px; font-size:4px;'>&nbsp;</td>
      </tr>

      <!-- Corporate Header -->
      <tr>
        <td style='background-color:#07111e; padding:24px 28px; border-bottom:2px solid #efad2d;'>
          <table border='0' cellpadding='0' cellspacing='0' width='100%'>
            <tr>
              <td valign='middle'>
                <div style='font-size:22px; font-weight:800; color:#ffffff; letter-spacing:0.04em;'>
                  SRI BALAJI <span style='color:#efad2d;'>GEARS</span>
                </div>
                <div style='font-size:11px; color:#94a3b8; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; margin-top:4px;'>
                  Tier-1 Automotive Seating &amp; Chassis Welded Assemblies · Hosur
                </div>
              </td>
              <td align='right' valign='middle'>
                <span style='display:inline-block; padding:6px 12px; background:rgba(239, 173, 45, 0.15); border:1px solid #efad2d; border-radius:20px; color:#fbbf24; font-size:11px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase;'>
                  ⚡ NEW RFQ INQUIRY
                </span>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Subheader with Reference ID & Timestamp -->
      <tr>
        <td style='background-color:#f8fafc; padding:16px 28px; border-bottom:1px solid #e2e8f0;'>
          <table border='0' cellpadding='0' cellspacing='0' width='100%'>
            <tr>
              <td>
                <div style='font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.06em;'>Tracking Reference ID</div>
                <div style='font-size:16px; font-weight:800; color:#0f172a; font-family:ui-monospace, monospace; margin-top:3px;'>
                  <span style='background:#fef3c7; color:#92400e; padding:3px 8px; border-radius:4px; border:1px solid #fde68a;'>{$refId}</span>
                </div>
              </td>
              <td align='right'>
                <div style='font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.06em;'>Received Timestamp</div>
                <div style='font-size:13px; font-weight:600; color:#334155; margin-top:3px;'>
                  " . date('d M Y · h:i A T') . "
                </div>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Main Body Content -->
      <tr>
        <td style='padding:26px 28px;'>
          
          <div style='font-size:12px; font-weight:700; color:#07111e; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:6px;'>
            📋 Customer &amp; Component Parameters
          </div>

          <!-- Parameters Table -->
          <table border='0' cellpadding='0' cellspacing='0' width='100%' style='border-collapse:collapse; margin-bottom:22px; font-size:14px;'>
            <tr style='background-color:#f8fafc;'>
              <td style='padding:10px 14px; width:34%; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;'>👤 Contact Person</td>
              <td style='padding:10px 14px; width:66%; font-weight:700; color:#0f172a; border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($name) . "</td>
            </tr>
            <tr>
              <td style='padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;'>🏢 Company / OEM</td>
              <td style='padding:10px 14px; font-weight:700; color:#0f172a; border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($company) . "</td>
            </tr>
            <tr style='background-color:#f8fafc;'>
              <td style='padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;'>✉️ Corporate Email</td>
              <td style='padding:10px 14px; border-bottom:1px solid #e2e8f0;'>
                <a href='mailto:{$email}' style='color:#2563eb; text-decoration:none; font-weight:600;'>{$email}</a>
              </td>
            </tr>
            <tr>
              <td style='padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;'>📞 Phone / Mobile</td>
              <td style='padding:10px 14px; border-bottom:1px solid #e2e8f0;'>
                <a href='tel:{$phone}' style='color:#0f172a; text-decoration:none; font-weight:700;'>{$phone}</a>
              </td>
            </tr>
            <tr style='background-color:#f8fafc;'>
              <td style='padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;'>🎯 Requirement Focus</td>
              <td style='padding:10px 14px; border-bottom:1px solid #e2e8f0;'>
                <span style='display:inline-block; padding:3px 10px; background:#e0f2fe; color:#0369a1; border-radius:4px; font-weight:700; font-size:13px;'>
                  {$categoryDisplay}
                </span>
              </td>
            </tr>
            <tr>
              <td style='padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;'>📊 Estimated Volume</td>
              <td style='padding:10px 14px; font-weight:700; color:#0f172a; border-bottom:1px solid #e2e8f0;'>{$volume}</td>
            </tr>
            <tr style='background-color:#f8fafc;'>
              <td style='padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;'>📎 Technical Drawing</td>
              <td style='padding:10px 14px; border-bottom:1px solid #e2e8f0;'>" . 
                ($attName ? "<strong style='color:#059669;'>✓ " . htmlspecialchars($attName) . "</strong> (Securely attached below)" : "<span style='color:#94a3b8;'>No attachment submitted</span>") . 
              "</td>
            </tr>
          </table>

          <!-- Component Details & Technical Notes Box -->
          <div style='font-size:12px; font-weight:700; color:#07111e; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:8px;'>
            📝 Technical Specifications &amp; Inquiry Notes:
          </div>
          <div style='background-color:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #efad2d; border-radius:6px; padding:16px 18px; font-size:14px; line-height:1.6; color:#1e293b; white-space:pre-wrap; margin-bottom:22px;'>" . nl2br(htmlspecialchars($message)) . "</div>

          <!-- Quick Action Buttons for ERP Engineering Team -->
          <table border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-top:10px;'>
            <tr>
              <td align='center'>
                <table border='0' cellpadding='0' cellspacing='0'>
                  <tr>
                    <td style='border-radius:6px; background-color:#07111e;'>
                      <a href='mailto:{$email}?subject=RE:%20Sri%20Balaji%20Gears%20RFQ%20[{$refId}]' target='_blank' style='display:inline-block; padding:11px 20px; font-size:12px; font-weight:700; color:#efad2d; text-decoration:none; border-radius:6px; letter-spacing:0.04em; text-transform:uppercase; border:1px solid #efad2d;'>
                        ↩ Reply to Customer
                      </a>
                    </td>
                    <td width='10'>&nbsp;</td>
                    <td style='border-radius:6px; background-color:#10b981;'>
                      <a href='tel:{$phone}' target='_blank' style='display:inline-block; padding:11px 20px; font-size:12px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:6px; letter-spacing:0.04em; text-transform:uppercase;'>
                        📞 Call Customer
                      </a>
                    </td>
                    <td width='10'>&nbsp;</td>
                    <td style='border-radius:6px; background-color:#f1f5f9; border:1px solid #cbd5e1;'>
                      <a href='{$appUrl}/admin/index.php' target='_blank' style='display:inline-block; padding:11px 18px; font-size:12px; font-weight:700; color:#334155; text-decoration:none; border-radius:6px; letter-spacing:0.04em; text-transform:uppercase;'>
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
        <td style='background-color:#07111e; padding:20px 28px; border-top:1px solid #1e293b; color:#94a3b8; font-size:12px; line-height:1.6; text-align:center;'>
          <div style='font-weight:700; color:#f8fafc; font-size:13px; margin-bottom:4px;'>SRI BALAJI GEARS</div>
          <div>SIPCOT Industrial Complex, Phase I · Hosur, Tamil Nadu 635126, India</div>
          <div style='color:#64748b; font-size:11px; margin-top:5px;'>
            IATF 16949 Aligned · ISO 9001:2015 · Automated ERP Web Transmission System
          </div>
        </td>
      </tr>

    </table>
  </center>
</body>
</html>
    ";

    sendMailMultipart($to, $subject, $htmlBody, $email, $name, $attName, $attPath);
}

/**
 * Dispatch confirmation email to customer
 */
function sendCustomerConfirmationEmail($to, $name, $company, $phone, $refId, $category, $customCategory, $volume, $message, $attName = null) {
    $categoryDisplay = $category === 'Others' ? "Others ({$customCategory})" : $category;
    $subject = "Thank You for Your RFQ [{$refId}] - Sri Balaji Gears Hosur";

    $attDisplay = $attName 
        ? "<strong style='color:#059669;'>✓ " . htmlspecialchars($attName) . "</strong> (Received &amp; Logged)"
        : "<span style='color:#94a3b8;'>No attachment submitted</span>";

    $htmlBody = "
<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>
<html xmlns='http://www.w3.org/1999/xhtml'>
<head>
  <meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
  <meta name='viewport' content='width=device-width, initial-scale=1.0'/>
  <title>Thank You for Your RFQ [{$refId}] - Sri Balaji Gears</title>
</head>
<body style='margin:0; padding:0; background-color:#0b1320; font-family:-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color:#1e293b; -webkit-font-smoothing:antialiased;'>
  <center style='width:100%; background-color:#0b1320; padding:24px 0;'>
    <table border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width:620px; background-color:#ffffff; border-radius:10px; overflow:hidden; box-shadow:0 10px 25px rgba(0,0,0,0.3); border:1px solid #1e293b;'>
      
      <!-- Top Gold Accent Line -->
      <tr>
        <td height='4' style='background:linear-gradient(90deg, #d97706 0%, #f59e0b 50%, #fbbf24 100%); line-height:4px; font-size:4px;'>&nbsp;</td>
      </tr>

      <!-- Corporate Header -->
      <tr>
        <td style='background-color:#07111e; padding:24px 28px; border-bottom:2px solid #efad2d;'>
          <table border='0' cellpadding='0' cellspacing='0' width='100%'>
            <tr>
              <td valign='middle'>
                <div style='font-size:22px; font-weight:800; color:#ffffff; letter-spacing:0.04em;'>
                  SRI BALAJI <span style='color:#efad2d;'>GEARS</span>
                </div>
                <div style='font-size:11px; color:#94a3b8; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; margin-top:4px;'>
                  Tier-1 Automotive Seating &amp; Chassis Welded Assemblies · Hosur
                </div>
              </td>
              <td align='right' valign='middle'>
                <span style='display:inline-block; padding:6px 12px; background:rgba(16, 185, 129, 0.15); border:1px solid #10b981; border-radius:20px; color:#34d399; font-size:11px; font-weight:700; letter-spacing:0.05em; text-transform:uppercase;'>
                  ✓ RFQ RECEIVED &amp; LOGGED
                </span>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Subheader with Reference ID & Tracking Status -->
      <tr>
        <td style='background-color:#f8fafc; padding:16px 28px; border-bottom:1px solid #e2e8f0;'>
          <table border='0' cellpadding='0' cellspacing='0' width='100%'>
            <tr>
              <td>
                <div style='font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.06em;'>Tracking Reference ID</div>
                <div style='font-size:17px; font-weight:800; color:#0f172a; font-family:ui-monospace, monospace; margin-top:3px;'>
                  <span style='background:#fef3c7; color:#92400e; padding:3px 10px; border-radius:4px; border:1px solid #fde68a;'>{$refId}</span>
                </div>
              </td>
              <td align='right'>
                <div style='font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.06em;'>Current Status</div>
                <div style='font-size:12px; font-weight:700; color:#059669; margin-top:3px;'>
                  ● Under Engineering Appraisal
                </div>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Body Content -->
      <tr>
        <td style='padding:26px 28px; font-size:14px; line-height:1.6;'>
          
          <p style='margin-top:0;'>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
          <p>Thank you for submitting your Request for Quotation (RFQ) for <strong>" . htmlspecialchars($company) . "</strong>. Your technical parameters and component requirements have been logged into our Hosur plant ERP system.</p>

          <!-- Parameter Summary Table -->
          <table border='0' cellpadding='0' cellspacing='0' width='100%' style='border-collapse:collapse; margin:18px 0; font-size:13px; border:1px solid #e2e8f0; border-radius:6px;'>
            <tr style='background-color:#f8fafc;'>
              <td style='padding:10px 14px; width:36%; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;'>👤 Contact Person</td>
              <td style='padding:10px 14px; font-weight:700; color:#0f172a; border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($name) . "</td>
            </tr>
            <tr>
              <td style='padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;'>🏢 Company / OEM</td>
              <td style='padding:10px 14px; font-weight:700; color:#0f172a; border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($company) . "</td>
            </tr>
            <tr style='background-color:#f8fafc;'>
              <td style='padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;'>📞 Contact Phone / Mobile</td>
              <td style='padding:10px 14px; font-weight:700; color:#0f172a; border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($phone) . "</td>
            </tr>
            <tr>
              <td style='padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;'>✉️ Registered Email</td>
              <td style='padding:10px 14px; font-weight:600; color:#2563eb; border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($to) . "</td>
            </tr>
            <tr style='background-color:#f8fafc;'>
              <td style='padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;'>🎯 Requirement Category</td>
              <td style='padding:10px 14px; font-weight:700; color:#0f172a; border-bottom:1px solid #e2e8f0;'>{$categoryDisplay}</td>
            </tr>
            <tr>
              <td style='padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;'>📊 Estimated Monthly Volume</td>
              <td style='padding:10px 14px; font-weight:700; color:#0f172a; border-bottom:1px solid #e2e8f0;'>{$volume}</td>
            </tr>
            <tr style='background-color:#f8fafc;'>
              <td style='padding:10px 14px; font-weight:600; color:#475569; border-bottom:1px solid #e2e8f0;'>📎 Attached Technical Drawing</td>
              <td style='padding:10px 14px; border-bottom:1px solid #e2e8f0;'>{$attDisplay}</td>
            </tr>
            <tr>
              <td style='padding:10px 14px; font-weight:600; color:#475569;'>⚡ Target Turnaround SLA</td>
              <td style='padding:10px 14px; font-weight:700; color:#d97706;'>Technical review &amp; quote within 24–48 hours</td>
            </tr>
          </table>

          <div style='font-size:12px; font-weight:700; color:#07111e; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:6px;'>
            📝 Your Technical Notes:
          </div>
          <div style='background-color:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #efad2d; border-radius:6px; padding:12px 14px; font-size:13px; color:#1e293b; line-height:1.6; margin-bottom:18px;'>" . nl2br(htmlspecialchars($message)) . "</div>

          <p>A Senior Tooling &amp; Welding Engineer has been assigned to inspect your component parameters. We will contact you at <strong>" . htmlspecialchars($phone) . "</strong> or <strong>" . htmlspecialchars($to) . "</strong> with our formal techno-commercial proposal.</p>

          <p style='margin-top:24px; margin-bottom:0;'>Warm regards,<br>
            <strong>Technical Sales &amp; ERP Engineering Team</strong><br>
            <span style='color:#64748b; font-size:13px;'>Sri Balaji Gears · SIPCOT Industrial Complex, Hosur</span>
          </p>

        </td>
      </tr>

      <!-- Corporate Footer -->
      <tr>
        <td style='background-color:#07111e; padding:20px 28px; border-top:1px solid #1e293b; color:#94a3b8; font-size:12px; line-height:1.6; text-align:center;'>
          <div style='font-weight:700; color:#f8fafc; font-size:13px; margin-bottom:4px;'>SRI BALAJI GEARS</div>
          <div>SIPCOT Industrial Complex, Phase I · Hosur, Tamil Nadu 635126, India</div>
          <div style='margin-top:4px;'>
            <strong>Direct Plant Line:</strong> <a href='tel:+917373334411' style='color:#efad2d; text-decoration:none;'>+91 73733 34411</a> &middot; 
            <strong>Email:</strong> <a href='mailto:prabhu@sribalajigears.com' style='color:#efad2d; text-decoration:none;'>prabhu@sribalajigears.com</a>
          </div>
        </td>
      </tr>

    </table>
  </center>
</body>
</html>
    ";

    sendMailMultipart($to, $subject, $htmlBody, config('ERP_EMAIL', 'prabhu@sribalajigears.com'), 'Sri Balaji Gears');
}

/**
 * Universal MIME multipart mail sender
 */
function sendMailMultipart($to, $subject, $htmlContent, $replyToEmail = null, $replyToName = null, $attName = null, $attPath = null) {
    $fromEmail = config('SMTP_FROM_EMAIL', config('SMTP_FROM', config('ERP_EMAIL', 'erp@sribalajigears.com')));
    $fromName = config('SMTP_FROM_NAME', 'Sri Balaji Gears');

    $boundary = '==Multipart_Boundary_x' . md5(microtime(true) . rand()) . 'x';
    $toHeader = is_array($to) ? implode(', ', $to) : $to;

    // Headers
    $headers = [];
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'Message-ID: <' . time() . '.' . bin2hex(random_bytes(8)) . '@sribalajigears.in>';
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = "From: {$fromName} <{$fromEmail}>";
    if ($replyToEmail) {
        $headers[] = "Reply-To: " . ($replyToName ? "{$replyToName} <{$replyToEmail}>" : $replyToEmail);
    }
    $headers[] = 'X-Mailer: SBG-Mailer/1.0';
    $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

    // Body with base64 encoded HTML for 100% UTF-8 / emoji safety across all mail clients
    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($htmlContent)) . "\r\n";

    // Attachment
    if ($attName && $attPath && file_exists($attPath) && filesize($attPath) < 15 * 1024 * 1024) {
        $fileData = file_get_contents($attPath);
        $fileChunk = chunk_split(base64_encode($fileData));
        $cleanAttName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($attName));
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/octet-stream; name=\"{$cleanAttName}\"\r\n";
        $body .= "Content-Description: {$cleanAttName}\r\n";
        $body .= "Content-Disposition: attachment; filename=\"{$cleanAttName}\"; size=" . strlen($fileData) . ";\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= $fileChunk . "\r\n";
    }

    $body .= "--{$boundary}--";

    // 1. Direct socket SMTP transmission
    $smtpHost = config('SMTP_HOST', 'smtp.rediffmailpro.com');
    $smtpEnabled = config('SMTP_ENABLED', 'true');
    if ($smtpEnabled === 'true' || $smtpEnabled === '1' || !empty($smtpHost)) {
        $port = (int)config('SMTP_PORT', 465);
        $user = config('SMTP_USER', config('SMTP_USERNAME', 'erp@sribalajigears.com'));
        $pass = config('SMTP_PASS', config('SMTP_PASSWORD', 'sbgerp@2026'));
        $crypto = strtolower(config('SMTP_ENCRYPTION', config('SMTP_SECURE', ($port == 465 ? 'ssl' : 'tls'))));

        $smtpSuccess = sendViaSmtpSocket($to, $subject, $body, $headers, $smtpHost, $port, $user, $pass, $crypto);
        if ($smtpSuccess) {
            return true;
        }
        error_log("Native SMTP delivery failed. Falling back to PHP mail().");
    }

    // 2. Standard server mail() fallback
    return @mail($toHeader, $subject, $body, implode("\r\n", $headers));
}

/**
 * Lightweight, zero-dependency SMTP client via standard PHP sockets
 * Supports multiple simultaneous recipients, full stream buffering, and robust error checking
 */
function sendViaSmtpSocket($to, $subject, $body, $headers, $host, $port, $user, $pass, $crypto = 'tls') {
    $timeout = 15;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $protocol = ($crypto === 'ssl' || $port == 465) ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client($protocol . $host . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        error_log("SMTP connection failed to {$host}:{$port} - {$errstr} ({$errno})");
        return false;
    }

    stream_set_timeout($socket, $timeout);

    $read = function() use ($socket) {
        $data = '';
        while ($str = fgets($socket, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) === ' ') break;
        }
        return $data;
    };

    $writeLine = function($cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };

    $writeAll = function($data) use ($socket) {
        $length = strlen($data);
        $written = 0;
        while ($written < $length) {
            $chunk = fwrite($socket, substr($data, $written, 8192));
            if ($chunk === false || $chunk === 0) {
                return false;
            }
            $written += $chunk;
        }
        return true;
    };

    $read(); // Initial service banner
    $writeLine("EHLO " . (gethostname() ?: 'sribalajigears.com'));
    $read();

    if ($crypto === 'tls' && $port != 465) {
        $writeLine("STARTTLS");
        $res = $read();
        if (strpos($res, '220') === 0) {
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $writeLine("EHLO " . (gethostname() ?: 'sribalajigears.com'));
            $read();
        }
    }

    if (!empty($user) && !empty($pass)) {
        $writeLine("AUTH LOGIN");
        $read();
        $writeLine(base64_encode($user));
        $read();
        $writeLine(base64_encode($pass));
        $res = $read();
        if (strpos($res, '235') !== 0) {
            error_log("SMTP AUTH rejected for user: {$user}");
            $writeLine("QUIT");
            fclose($socket);
            return false;
        }
    }

    $fromMail = config('SMTP_FROM_EMAIL', config('ERP_EMAIL', 'erp@sribalajigears.com'));
    $writeLine("MAIL FROM: <{$fromMail}>");
    $read();

    $recipients = is_array($to) ? $to : array_map('trim', explode(',', $to));
    $acceptedCount = 0;
    foreach ($recipients as $rcpt) {
        if (!empty($rcpt)) {
            $writeLine("RCPT TO: <{$rcpt}>");
            $rcptRes = $read();
            if (strpos($rcptRes, '250') === 0 || strpos($rcptRes, '251') === 0) {
                $acceptedCount++;
            }
        }
    }

    if ($acceptedCount === 0) {
        error_log("SMTP recipient(s) rejected for: " . json_encode($to));
        $writeLine("QUIT");
        fclose($socket);
        return false;
    }

    $writeLine("DATA");
    $read();

    $toHeader = implode(', ', array_filter($recipients));
    $payload = implode("\r\n", $headers) . "\r\nSubject: {$subject}\r\nTo: {$toHeader}\r\n\r\n" . $body . "\r\n.\r\n";
    $writeAll($payload);
    $res = $read();

    $writeLine("QUIT");
    $read();
    fclose($socket);

    return strpos($res, '250') === 0;
}
