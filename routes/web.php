<?php

use App\NativeComponents\Login;
use App\NativeComponents\Profile;
use App\NativeComponents\Register;
use App\NativeComponents\Welcome;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Stack routes
|--------------------------------------------------------------------------
|
| Home is a SuperNative screen. On a phone NativePHP renders it natively;
| in the browser nativephp/mobile-web (Web UI) renders the same Blade as HTML.
|
*/

Route::native('/', Welcome::class)->name('mobile.home');
Route::native('/login', Login::class)->name('mobile.login');
Route::native('/register', Register::class)->name('mobile.register');
Route::native('/profile', Profile::class)->name('mobile.profile');
