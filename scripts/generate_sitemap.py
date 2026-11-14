#!/usr/bin/env python3
"""
Automated Sitemap Generator for Sri Balaji Gears
Scans public HTML pages, reads modification timestamps, and outputs Google-compliant sitemap.xml.
"""
import os
import glob
from datetime import datetime, timezone

BASE_URL = "https://www.sribalajigears.com"

# Priority and changefreq rules
PAGE_CONFIG = {
    "index.html": {"loc": f"{BASE_URL}/", "priority": "1.0", "changefreq": "weekly"},
    "products.html": {"loc": f"{BASE_URL}/products.html", "priority": "0.9", "changefreq": "weekly"},
    "contact.html": {"loc": f"{BASE_URL}/contact.html", "priority": "0.9", "changefreq": "weekly"},
    "manufacturing.html": {"loc": f"{BASE_URL}/manufacturing.html", "priority": "0.8", "changefreq": "monthly"},
    "infrastructure.html": {"loc": f"{BASE_URL}/infrastructure.html", "priority": "0.8", "changefreq": "monthly"},
    "quality.html": {"loc": f"{BASE_URL}/quality.html", "priority": "0.8", "changefreq": "monthly"},
    "certifications.html": {"loc": f"{BASE_URL}/certifications.html", "priority": "0.8", "changefreq": "monthly"},
    "customers.html": {"loc": f"{BASE_URL}/customers.html", "priority": "0.8", "changefreq": "monthly"},
    "about.html": {"loc": f"{BASE_URL}/about.html", "priority": "0.8", "changefreq": "monthly"}
}

def generate_sitemap(output_path="sitemap.xml"):
    xml_lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
    ]

    for filename, config in PAGE_CONFIG.items():
        if os.path.exists(filename):
            mtime = os.path.getmtime(filename)
            lastmod = datetime.fromtimestamp(mtime, tz=timezone.utc).strftime('%Y-%m-%d')
        else:
            lastmod = datetime.now(tz=timezone.utc).strftime('%Y-%m-%d')

        xml_lines.append('  <url>')
        xml_lines.append(f'    <loc>{config["loc"]}</loc>')
        xml_lines.append(f'    <lastmod>{lastmod}</lastmod>')
        xml_lines.append(f'    <changefreq>{config["changefreq"]}</changefreq>')
        xml_lines.append(f'    <priority>{config["priority"]}</priority>')
        xml_lines.append('  </url>')

    xml_lines.append('</urlset>')

    sitemap_content = '\n'.join(xml_lines) + '\n'
    with open(output_path, 'w', encoding='utf-8') as f:
        f.write(sitemap_content)

    print(f"Successfully generated Google sitemap at {output_path} with {len(PAGE_CONFIG)} pages.")

if __name__ == "__main__":
    generate_sitemap()
