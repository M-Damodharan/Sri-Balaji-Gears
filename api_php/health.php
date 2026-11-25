<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode([
    'status' => 'healthy',
    'service' => 'Sri Balaji Gears Production Web Service',
    'timestamp' => date('c'),
    'uptime' => '24/7 active'
]);
