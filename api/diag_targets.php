<?php
header('Content-Type: text/plain; charset=utf-8');

$targets = [
    'google.com:443',
    'smtp.gmail.com:587',
    'api.brevo.com:443',
    'api.resend.com:443',
    'smtp.rediffmailpro.com:465',
    '119.252.155.19:465'
];

echo "=== OUTBOUND CONNECTIVITY TEST FROM RENDER ===\n";
foreach ($targets as $target) {
    list($host, $port) = explode(':', $target);
    $t0 = microtime(true);
    $errno = 0; $errstr = '';
    $fp = @fsockopen($host, (int)$port, $errno, $errstr, 3);
    $dt = round(microtime(true) - $t0, 3);
    if ($fp) {
        echo "{$target} -> SUCCESS ({$dt}s)\n";
        fclose($fp);
    } else {
        echo "{$target} -> FAILED ({$dt}s) - {$errstr} ({$errno})\n";
    }
}
