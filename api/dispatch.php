<?php
/**
 * Sri Balaji Gears - Background Asynchronous Email Dispatch Worker
 * Executed via detached CLI process:
 * php api/dispatch.php <enquiry_id>
 */

// Silently ignore non-CLI execution if someone tries to hit it via browser
if (php_sapi_name() !== 'cli' && empty($_SERVER['argv'])) {
    http_response_code(403);
    die("Access Denied: Background worker CLI only.");
}

$enquiryId = isset($argv[1]) ? (int)$argv[1] : 0;
if ($enquiryId <= 0) {
    error_log("dispatch.php: Called without valid enquiry ID.");
    exit(1);
}

// Ensure error logging is captured in container log
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/mail_helper.php';

// Dispatch all 3 emails (prabhu@sribalajigears.com, erp@sribalajigears.com, and customer confirmation receipt)
$success = dispatchEnquiryEmails($enquiryId);

if ($success) {
    error_log("dispatch.php: Successfully dispatched all RFQ emails for enquiry #{$enquiryId}");
} else {
    error_log("dispatch.php: Failed to dispatch one or more emails for enquiry #{$enquiryId}");
}

exit($success ? 0 : 1);
