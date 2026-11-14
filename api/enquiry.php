<?php
/**
 * Sri Balaji Gears - RFQ Enquiry API Endpoint
 * Handles submission, file upload, database persistence, and launches
 * background asynchronous email delivery without blocking the user.
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

    // 2. Anti-Spam: Rate Limiting by IP (Max 1 submission every 15 seconds)
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

    $enquiryId = (int)$pdo->lastInsertId();
    if ($enquiryId <= 0) {
        $idStmt = $pdo->prepare("SELECT id FROM enquiries WHERE reference_id = :ref LIMIT 1");
        $idStmt->execute([':ref' => $refId]);
        $enquiryId = (int)$idStmt->fetchColumn();
    }

    // 6. Spawn Detached Asynchronous Background Dispatch Worker
    // This delivers all 3 emails (prabhu@, erp@, and customer receipt) in the background
    // without blocking the user's browser for 30-50 seconds.
    $phpBin = (defined('PHP_BINARY') && is_executable(PHP_BINARY) && strpos(PHP_BINARY, 'php') !== false) ? PHP_BINARY : 'php';
    if (!is_executable($phpBin) && file_exists('/usr/local/bin/php')) {
        $phpBin = '/usr/local/bin/php';
    }

    $worker = escapeshellarg(__DIR__ . '/dispatch.php');
    $argId = (int)$enquiryId;
    $cmd = "{$phpBin} {$worker} {$argId} > /dev/null 2>&1 &";

    $dispatched = false;
    if (function_exists('exec')) {
        @exec($cmd);
        $dispatched = true;
    }

    // Fallback if exec is disabled in restricted environments
    if (!$dispatched && $enquiryId > 0) {
        require_once __DIR__ . '/mail_helper.php';
        dispatchEnquiryEmails($enquiryId);
    }

    // 7. Return Instant Sub-Second JSON Success Response (<100ms)
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your enquiry has been successfully transmitted directly to our ERP engineering desk.',
        'reference_id' => $refId
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while processing your request. Please email directly to ' . config('ERP_EMAIL', 'erp@sribalajigears.com')
    ]);
}
