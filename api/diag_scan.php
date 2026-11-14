<?php
header('Content-Type: text/plain; charset=utf-8');

$host = 'smtp.rediffmailpro.com';
$ip = gethostbyname($host);
echo "Testing $host ($ip)\n";

$ports = [80, 443, 993, 995, 110, 143, 465, 587, 2525, 8080, 8443, 2082, 2083, 2086, 2087, 2095, 2096, 25000, 25025];

foreach ($ports as $port) {
    $t0 = microtime(true);
    $errno = 0; $errstr = '';
    $fp = @fsockopen($host, $port, $errno, $errstr, 2);
    $dt = round(microtime(true) - $t0, 3);
    if ($fp) {
        echo "Port {$port}: OPEN ({$dt}s)\n";
        fclose($fp);
    } else {
        echo "Port {$port}: CLOSED/TIMEOUT ({$dt}s)\n";
    }
}

$host2 = 'mail.rediffmailpro.com';
$ip2 = gethostbyname($host2);
echo "\nTesting $host2 ($ip2)\n";
foreach ([80, 443, 993, 995, 110, 143] as $port) {
    $t0 = microtime(true);
    $errno = 0; $errstr = '';
    $fp = @fsockopen($host2, $port, $errno, $errstr, 2);
    $dt = round(microtime(true) - $t0, 3);
    if ($fp) {
        echo "Port {$port}: OPEN ({$dt}s)\n";
        fclose($fp);
    } else {
        echo "Port {$port}: CLOSED/TIMEOUT ({$dt}s)\n";
    }
}
