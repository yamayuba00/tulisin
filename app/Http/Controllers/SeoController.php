<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $base = rtrim((string) config('app.url'), '/');

        $pages = [
            ['loc' => $base . '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => $base . '/reviews', 'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => $base . '/register', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => $base . '/login', 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['loc' => $base . '/forgot-password', 'priority' => '0.1', 'changefreq' => 'yearly'],
        ];

        $lastmod = now()->toIso8601String();

        $urls = '';
        foreach ($pages as $page) {
            $urls .= sprintf(
                "  <url>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n    <changefreq>%s</changefreq>\n    <priority>%s</priority>\n  </url>\n",
                htmlspecialchars($page['loc'], ENT_XML1, 'UTF-8'),
                $lastmod,
                $page['changefreq'],
                $page['priority'],
            );
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            . $urls
            . "</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $base = rtrim((string) config('app.url'), '/');

        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            '',
            'Disallow: /api/',
            'Disallow: /apps/',
            'Disallow: /auth/',
            'Disallow: /payment/',
            '',
            'Sitemap: ' . $base . '/sitemap.xml',
        ]);

        return response($content . "\n", 200, ['Content-Type' => 'text/plain']);
    }
}
