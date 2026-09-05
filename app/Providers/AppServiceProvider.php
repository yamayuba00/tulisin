<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate limiter umum untuk endpoint API terproteksi.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter ketat untuk endpoint auth (anti-bruteforce / anti-spam).
        // Dibatasi per kombinasi email+IP agar serangan terarah ke satu akun
        // dari IP yang sama juga ikut terkunci.
        RateLimiter::for('auth', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return Limit::perMinute(5)->by($email !== '' ? $email.'|'.$request->ip() : $request->ip());
        });

        // Rate limiter untuk pencarian paper (proxy ke Crossref).
        RateLimiter::for('papers', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
