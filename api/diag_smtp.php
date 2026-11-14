<?php
header('Content-Type: text/plain; charset=utf-8');

$host = 'smtp.rediffmailpro.com';
$port = 465;
$user = 'erp@sribalajigears.com';
$pass = 'sbgerp@2026';

echo "=== SRI BALAJI GEARS SMTP DIAGNOSTIC ===\n";
echo "Date: " . date('r') . "\n";
echo "PHP SAPI: " . php_sapi_name() . "\n";
echo "PHP Binary: " . (defined('PHP_BINARY') ? PHP_BINARY : 'undefined') . "\n";
echo "Exec function exists: " . (function_exists('exec') ? 'YES' : 'NO') . "\n";

// Test exec of php
if (function_exists('exec')) {
    $out = [];
    $ret = -1;
    @exec('which php 2>&1', $out, $ret);
    echo "which php: " . implode(' ', $out) . " (code: $ret)\n";

    $out2 = [];
    $ret2 = -1;
    @exec('php -v 2>&1', $out2, $ret2);
    echo "php -v: " . implode(' ', $out2) . " (code: $ret2)\n";

    $out3 = [];
    $ret3 = -1;
    @exec('/usr/local/bin/php -v 2>&1', $out3, $ret3);
    echo "/usr/local/bin/php -v: " . implode(' ', $out3) . " (code: $ret3)\n";
}

// Test SMTP Socket
echo "\n--- Testing SMTP Socket Connection ---\n";
$start = microtime(true);
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
]);

$socket = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
if (!$socket) {
    echo "FAIL: stream_socket_client failed: {$errstr} ({$errno})\n";
    exit;
}
echo "Connected in " . round(microtime(true) - $start, 3) . "s\n";

stream_set_timeout($socket, 15);

function readSmtp($s) {
    $data = '';
    while ($line = fgets($s, 515)) {
        $data .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }
    return trim($data);
}

echo "Banner: " . readSmtp($socket) . "\n";

fputs($socket, "EHLO sribalajigears.com\r\n");
echo "EHLO: " . readSmtp($socket) . "\n";

fputs($socket, "AUTH LOGIN\r\n");
echo "AUTH LOGIN: " . readSmtp($socket) . "\n";

fputs($socket, base64_encode($user) . "\r\n");
echo "USER sent: " . readSmtp($socket) . "\n";

fputs($socket, base64_encode($pass) . "\r\n");
$authRes = readSmtp($socket);
echo "PASS sent: " . $authRes . "\n";

if (strpos($authRes, '235') === 0) {
    echo "AUTH SUCCESSFUL!\n";

    // Test sending an actual diagnostic mail to erp@sribalajigears.com
    fputs($socket, "MAIL FROM: <erp@sribalajigears.com>\r\n");
    echo "MAIL FROM: " . readSmtp($socket) . "\n";

    fputs($socket, "RCPT TO: <erp@sribalajigears.com>\r\n");
    echo "RCPT TO erp@: " . readSmtp($socket) . "\n";

    fputs($socket, "RCPT TO: <prabhu@sribalajigears.com>\r\n");
    echo "RCPT TO prabhu@: " . readSmtp($socket) . "\n";

    fputs($socket, "RCPT TO: <ddamo4688@gmail.com>\r\n");
    echo "RCPT TO ddamo4688@: " . readSmtp($socket) . "\n";

    fputs($socket, "DATA\r\n");
    echo "DATA: " . readSmtp($socket) . "\n";

    $testBody = "From: Sri Balaji Gears <erp@sribalajigears.com>\r\n"
              . "To: erp@sribalajigears.com, prabhu@sribalajigears.com\r\n"
              . "Subject: [TEST DIAGNOSTIC] SMTP Verification - Sri Balaji Gears\r\n"
              . "MIME-Version: 1.0\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
              . "This is an automated verification confirming direct SMTP delivery to erp@sribalajigears.com and prabhu@sribalajigears.com.\r\n"
              . "Time: " . date('r') . "\r\n.\r\n";

    fputs($socket, $testBody);
    echo "PAYLOAD sent: " . readSmtp($socket) . "\n";
} else {
    echo "AUTH FAILED!\n";
}

fputs($socket, "QUIT\r\n");
echo "QUIT: " . readSmtp($socket) . "\n";
fclose($socket);
echo "Completed in " . round(microtime(true) - $start, 3) . "s\n";
