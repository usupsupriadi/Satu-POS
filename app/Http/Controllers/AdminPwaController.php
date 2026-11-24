<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Manifest & service worker PWA admin. Sebelumnya dua closure besar ditulis
 * duplikat di routes/web.php (satu untuk domain admin, satu untuk prefix
 * /admin), sehingga mudah menyimpang.
 */
class AdminPwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $hasAdminDomain = (string) config('domains.admin', '') !== '';

        $manifest = [
            'name' => config('app.name').' Admin',
            'short_name' => 'Admin',
            'start_url' => $hasAdminDomain ? '/signin' : '/admin/signin',
            'scope' => $hasAdminDomain ? '/' : '/admin/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#111827',
            'icons' => [
                [
                    'src' => '/assets/images/192.png?v=2',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                ],
                [
                    'src' => '/assets/images/512.png?v=2',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                ],
            ],
        ];

        return response()->json($manifest)->header('Content-Type', 'application/manifest+json');
    }

    public function serviceWorker(): Response
    {
        $script = <<<'JS'
        const CACHE_NAME = 'admin-static-v2';

        self.addEventListener('install', (event) => {
          event.waitUntil(self.skipWaiting());
        });

        self.addEventListener('activate', (event) => {
          event.waitUntil((async () => {
            const keys = await caches.keys();
            await Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)));
            await self.clients.claim();
          })());
        });

        function isCacheable(request) {
          if (request.method !== 'GET') {
            return false;
          }

          const url = new URL(request.url);

          if (url.origin !== self.location.origin) {
            return false;
          }

          if (request.mode === 'navigate') {
            return false;
          }

          if (['script', 'style', 'image', 'font'].includes(request.destination)) {
            return true;
          }

          return /\.(?:js|css|png|jpg|jpeg|webp|svg|gif|woff2?|ttf|eot)$/.test(url.pathname);
        }

        async function cacheFirst(request) {
          const cache = await caches.open(CACHE_NAME);
          const cached = await cache.match(request);

          if (cached) {
            return cached;
          }

          const response = await fetch(request);

          if (response.ok) {
            await cache.put(request, response.clone());
          }

          return response;
        }

        async function networkFirst(request) {
          try {
            return await fetch(request);
          } catch (e) {
            return new Response(
              '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title></head><body style="font-family:system-ui;padding:24px"><h1>Offline</h1><p>Koneksi internet tidak tersedia.</p></body></html>',
              { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
            );
          }
        }

        self.addEventListener('fetch', (event) => {
          const request = event.request;

          if (request.method !== 'GET') {
            return;
          }

          if (request.mode === 'navigate') {
            event.respondWith(networkFirst(request));
            return;
          }

          if (isCacheable(request)) {
            event.respondWith(cacheFirst(request));
          }
        });
        JS;

        return response($script)->header('Content-Type', 'application/javascript; charset=utf-8');
    }
}
