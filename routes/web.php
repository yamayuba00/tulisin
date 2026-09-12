<?php

use App\Http\Controllers\SeoController;
use App\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

// ---- OAuth Social Login (Google / GitHub) ----
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['google', 'github'])
    ->name('social.redirect');

Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['google', 'github'])
    ->name('social.callback');

// ---- SEO: sitemap & robots (dinamis, mengikuti config('app.url')) ----
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

// Semua route non-API diarahkan ke SPA Vue.
Route::get('/{any?}', fn () => view('app'))->where('any', '^(?!api($|/)).*');
