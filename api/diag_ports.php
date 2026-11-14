<?php
header('Content-Type: text/plain; charset=utf-8');

$hosts = ['smtp.rediffmailpro.com', 'mail.rediffmailpro.com', 'rediffmailpro.com'];
$ports = [587, 465, 25, 2525];

echo "=== SRI BALAJI GEARS NETWORK PORT CONNECTIVITY TEST ===\n";

foreach ($hosts as $host) {
    $ip = gethostbyname($host);
    echo "\nHost: {$host} ({$ip})\n";
    foreach ($ports as $port) {
        $t0 = microtime(true);
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, 4);
        $dt = round(microtime(true) - $t0, 3);
        if ($fp) {
            echo "  Port {$port}: OPEN ({$dt}s) -> ";
            stream_set_timeout($fp, 3);
            $banner = fgets($fp, 515);
            echo trim($banner) . "\n";
            fclose($fp);
        } else {
            echo "  Port {$port}: BLOCKED / TIMEOUT ({$dt}s) - {$errstr} ({$errno})\n";
        }
    }
}
