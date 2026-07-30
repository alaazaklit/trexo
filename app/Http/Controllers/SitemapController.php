<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    private const PATHS = ['', 'become-a-driver', 'privacy-policy', 'terms'];

    public function index(): Response
    {
        $urls = [];

        foreach (array_keys(config('localization.locales')) as $locale) {
            foreach (self::PATHS as $path) {
                $urls[] = rtrim(url($locale.'/'.$path), '/');
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url).'</loc></url>'."\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
