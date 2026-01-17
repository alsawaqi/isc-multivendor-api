<?php

use Illuminate\Support\Facades\Route;



// Example: if vendor SPA lives under /vendor
Route::get('/{any?}', function () {
    return view('vendor'); // your Blade that contains <div id="app"></div> and @vite
})->where('any', '.*');
