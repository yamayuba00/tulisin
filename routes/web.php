<?php

use Illuminate\Support\Facades\Route;

// Semua route non-API diarahkan ke SPA Vue.
Route::get('/{any?}', fn () => view('app'))->where('any', '^(?!api($|/)).*');
