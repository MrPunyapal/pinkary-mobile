<?php

use App\NativeComponents\Bookmarks;
use App\NativeComponents\Compose;
use App\NativeComponents\Detail;
use App\NativeComponents\Home;
use App\NativeComponents\Login;
use App\NativeComponents\Notifications;
use App\NativeComponents\Profile;
use App\NativeComponents\Register;
use App\NativeComponents\Search;
use App\NativeLayout\MobileLayout;
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

Route::native('/login', Login::class)->name('mobile.login');
Route::native('/register', Register::class)->name('mobile.register');
Route::native('/compose', Compose::class)->name('mobile.compose');

Route::nativeGroup(MobileLayout::class, function (): void {
    // The home page is the recent feed — readable without logging in,
    // like the web's home page.
    Route::native('/', Home::class)->name('mobile.home');
    Route::native('/feed', Home::class)->name('mobile.feed');
    Route::native('/search', Search::class)->name('mobile.search');
    Route::native('/bookmarks', Bookmarks::class)->name('mobile.bookmarks');
    Route::native('/notifications', Notifications::class)->name('mobile.notifications');
    Route::native('/profile', Profile::class)->name('mobile.profile');
    Route::native('/user/{username}', Profile::class)->name('mobile.user');
    Route::native('/question/{id}', Detail::class)->name('mobile.detail');
});
