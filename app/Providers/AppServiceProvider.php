<?php

namespace App\Providers;

use App\Helpers\MenuHelper;
use App\Models\DiningTable;
use App\Models\PrinterSource;
use App\Models\Transaction;
use App\Observers\DiningTableObserver;
use App\Observers\TransactionObserver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! function_exists('generate_qr_code') && file_exists(app_path('helpers.php'))) {
            require_once app_path('helpers.php');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pastikan URL (aset, redirect, link QR) selalu https saat APP_URL https,
        // termasuk ketika TLS diterminasi oleh reverse proxy / CDN.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Transaction::observe(TransactionObserver::class);
        DiningTable::observe(DiningTableObserver::class);

        // Dulu dijalankan sebagai View::share sehingga SETIAP request (termasuk
        // landing page dan self-order publik) menembak schema check + query
        // printer_sources. Sekarang hanya layout admin yang memuatnya, dan
        // hasilnya di-cache singkat.
        View::composer('layouts.app', function (\Illuminate\View\View $view): void {
            $loadPrinterSources = function (): array {
                return PrinterSource::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'type'])
                    ->map(fn (PrinterSource $s) => [
                        'id' => (int) $s->id,
                        'name' => (string) $s->name,
                        'type' => (string) $s->type,
                    ])
                    ->all();
            };

            // Di lingkungan testing cache dilewati agar data uji selalu segar,
            // sama seperti cache laporan penjualan.
            $printerSourcesForJs = app()->environment('testing')
                ? $loadPrinterSources()
                : Cache::remember('printer_sources_for_js', 300, $loadPrinterSources);

            $view->with('printerSourcesForJs', is_array($printerSourcesForJs) ? $printerSourcesForJs : []);
        });

        View::composer('layouts.sidebar', function (\Illuminate\View\View $view): void {
            $user = auth()->user();

            $view->with([
                'menuGroups' => MenuHelper::getMenuGroups(),
                'currentPath' => request()->path(),
                'canAccessPos' => $user?->can('pos.access') ?? false,
            ]);
        });
    }
}
