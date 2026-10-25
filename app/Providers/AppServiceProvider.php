<?php

namespace App\Providers;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Maatwebsite\Excel\ExcelServiceProvider;
use Maatwebsite\Excel\Facades\Excel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(ExcelServiceProvider::class);
        AliasLoader::getInstance()->alias('Excel', Excel::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('login', function (\Illuminate\Http\Request $request) {
            $username = $request->input('username');
            $username = is_string($username) ? mb_strtolower(trim($username)) : '';
            return [
                \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by($request->ip()),
                \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by(hash('sha256', $username.'|'.$request->ip())),
            ];
        });
        \Illuminate\Support\Facades\RateLimiter::for('secret-login', fn (\Illuminate\Http\Request $request) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->user()?->id.'|'.$request->ip())
        );

        Schema::defaultStringLength(191);

        // Share currency symbol with all views so Blade templates can use $currency
        // Share global settings-derived values with all views
        try {
            $symbol = \App\Models\Setting::where('key', 'currency')->value('value');
        } catch (\Throwable $e) {
            $symbol = null;
        }

        \Illuminate\Support\Facades\View::share('currency', $symbol ?: 'Rs');

        $defaultTimezone = env('APP_TIMEZONE', config('app.timezone', 'Asia/Colombo'));

        // Apply timezone from settings (default to Asia/Colombo for Sri Lanka)
        try {
            $tz = \App\Models\Setting::where('key', 'timezone')->value('value');
        } catch (\Throwable $e) {
            $tz = null;
        }
        // Fall back to the configured default timezone when none is stored or when the stored value is still UTC.
        $timezone = $tz ?: $defaultTimezone;
        if ($timezone === 'UTC' && $defaultTimezone !== 'UTC') {
            $timezone = $defaultTimezone;
        }
        date_default_timezone_set($timezone);
        config(['app.timezone' => $timezone]);
    }
}
