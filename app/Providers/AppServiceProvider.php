<?php

namespace App\Providers;

use App\Models\CertificacionItem;
use App\Observers\CertificacionItemObserver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        CertificacionItem::observe(CertificacionItemObserver::class);

        if ($this->app->runningInConsole()) {
            return;
        }

        $cacheKey = 'cedula_next_year_ensured_' . now()->year;
        Cache::rememberForever($cacheKey, function () {
            try {
                Artisan::call('cedula:next-year');
            } catch (\Throwable $e) {
                \Log::error('cedula:next-year boot error: ' . $e->getMessage());
            }
            return true;
        });
    }
}
