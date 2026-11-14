<?php
/**
 * Automated PHP Sitemap Generator for Sri Balaji Gears
 */

$baseUrl = 'https://www.sribalajigears.com';

$pages = [
    'index.html'          => ['loc' => $baseUrl . '/',                    'priority' => '1.0', 'changefreq' => 'weekly'],
    'products.html'       => ['loc' => $baseUrl . '/products.html',       'priority' => '0.9', 'changefreq' => 'weekly'],
    'contact.html'        => ['loc' => $baseUrl . '/contact.html',        'priority' => '0.9', 'changefreq' => 'weekly'],
    'manufacturing.html'  => ['loc' => $baseUrl . '/manufacturing.html',  'priority' => '0.8', 'changefreq' => 'monthly'],
    'infrastructure.html' => ['loc' => $baseUrl . '/infrastructure.html', 'priority' => '0.8', 'changefreq' => 'monthly'],
    'quality.html'        => ['loc' => $baseUrl . '/quality.html',        'priority' => '0.8', 'changefreq' => 'monthly'],
    'certifications.html' => ['loc' => $baseUrl . '/certifications.html', 'priority' => '0.8', 'changefreq' => 'monthly'],
    'customers.html'      => ['loc' => $baseUrl . '/customers.html',      'priority' => '0.8', 'changefreq' => 'monthly'],
    'about.html'          => ['loc' => $baseUrl . '/about.html',          'priority' => '0.8', 'changefreq' => 'monthly'],
];

$rootDir = dirname(__DIR__);
$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
$xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

foreach ($pages as $file => $meta) {
    $filePath = $rootDir . '/' . $file;
    $lastmod = file_exists($filePath) ? date('Y-m-d', filemtime($filePath)) : date('Y-m-d');

    $xml .= "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($meta['loc']) . "</loc>\n";
    $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
    $xml .= "    <changefreq>{$meta['changefreq']}</changefreq>\n";
    $xml .= "    <priority>{$meta['priority']}</priority>\n";
    $xml .= "  </url>\n";
}

$xml .= "</urlset>\n";

$targetFile = $rootDir . '/sitemap.xml';
file_put_contents($targetFile, $xml);
echo "Google Sitemap generated successfully at: " . $targetFile . "\n";
