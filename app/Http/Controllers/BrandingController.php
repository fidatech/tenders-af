<?php

namespace App\Http\Controllers;

use App\Support\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The pictures of the brand, the web app manifest and the page shown when a phone is offline. */
class BrandingController extends Controller
{
    /** A logo or icon. The address carries a version (?v=...), so it may be cached for a year. */
    public function asset(string $asset): StreamedResponse
    {
        $path = Branding::fileFor($asset);
        abort_unless($path, 404);

        return Storage::disk(Branding::DISK)->response($path, null, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** What a phone needs to add the site to its home screen. */
    public function manifest(): JsonResponse
    {
        $icons = [];
        foreach (Branding::APP_ICON_SIZES as $size) {
            if ($url = Branding::appIconUrl($size)) {
                $icons[] = ['src' => $url, 'sizes' => $size.'x'.$size, 'type' => 'image/png', 'purpose' => 'any'];
            }
        }
        $icons[] = ['src' => asset('img/favicon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'];

        return response()->json([
            'name' => Branding::name(),
            'short_name' => mb_substr(Branding::name(), 0, 12),
            'description' => Branding::tagline(),
            'id' => '/',
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#F6F8F5',
            'theme_color' => '#0B6B3A',
            'icons' => $icons,
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Shown by the service worker when a page cannot be loaded. It never pretends to show live data. */
    public function offline(): View
    {
        return view('offline');
    }

    /** The service worker: only the offline page is kept; every other request goes to the server. */
    public function serviceWorker(): Response
    {
        $offline = route('site.offline', [], false);
        $version = substr(md5((string) @filemtime(resource_path('views/offline.blade.php'))), 0, 8);
        $script = <<<JS
// Tenders.af service worker. It keeps one page, shown when the network is down.
// Tenders, payments, messages and forms always go to the server: nothing live is cached.
const CACHE = 'tenders-offline-{$version}';
const OFFLINE = '{$offline}';
self.addEventListener('install', (e) => { e.waitUntil(caches.open(CACHE).then((c) => c.add(new Request(OFFLINE, { cache: 'reload' }))).then(() => self.skipWaiting())); });
self.addEventListener('activate', (e) => { e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k.startsWith('tenders-offline-') && k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', (e) => {
    if (e.request.mode !== 'navigate' || e.request.method !== 'GET') return;
    e.respondWith(fetch(e.request).catch(() => caches.match(OFFLINE)));
});
JS;

        return response($script, 200, ['Content-Type' => 'application/javascript', 'Cache-Control' => 'no-cache', 'Service-Worker-Allowed' => '/']);
    }
}
