<?php
/**
 * Sri Balaji Gears - Secure Admin Attachment Download Endpoint
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/admin/auth.php';

// Verify admin login
requireAdminAuth();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    die('Invalid enquiry ID.');
}

$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT attachment_name, attachment_path FROM enquiries WHERE id = :id");
$stmt->execute([':id' => $id]);
$enquiry = $stmt->fetch();

if (!$enquiry || empty($enquiry['attachment_path'])) {
    http_response_code(404);
    die('No attachment associated with this enquiry.');
}

$fullPath = dirname(__DIR__) . '/' . $enquiry['attachment_path'];
if (!file_exists($fullPath) || !is_readable($fullPath)) {
    http_response_code(404);
    die('Attachment file not found on disk.');
}

// Stream download
$filename = basename($enquiry['attachment_name']);
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
