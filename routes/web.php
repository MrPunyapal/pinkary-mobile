<?php

declare(strict_types=1);

use App\NativeComponents\Bookmarks;
use App\NativeComponents\Compose;
use App\NativeComponents\Detail;
use App\NativeComponents\EditProfile;
use App\NativeComponents\FollowList;
use App\NativeComponents\HashtagFeed;
use App\NativeComponents\Home;
use App\NativeComponents\Login;
use App\NativeComponents\Notifications;
use App\NativeComponents\Profile;
use App\NativeComponents\Register;
use App\NativeComponents\Search;
use App\NativeComponents\Settings;
use App\NativeLayout\MobileLayout;
use Illuminate\Support\Facades\Route;

Route::native('/login', Login::class)->name('mobile.login');
Route::native('/register', Register::class)->name('mobile.register');
Route::native('/compose', Compose::class)->name('mobile.compose');

Route::nativeGroup(MobileLayout::class, function (): void {
    Route::native('/', Home::class)->name('mobile.home');
    Route::native('/feed', Home::class)->name('mobile.feed');
    Route::native('/search', Search::class)->name('mobile.search');
    Route::native('/hashtag/{hashtagId}', HashtagFeed::class)->name('mobile.hashtag');
    Route::native('/bookmarks', Bookmarks::class)->name('mobile.bookmarks');
    Route::native('/notifications', Notifications::class)->name('mobile.notifications');
    Route::native('/profile', Profile::class)->name('mobile.profile');
    Route::native('/profile/edit', EditProfile::class)->name('mobile.profile.edit');
    Route::native('/settings', Settings::class)->name('mobile.settings');
    Route::native('/@{username}/questions/{id}', Detail::class)->name('mobile.link.detail');
    Route::native('/@{username}', Profile::class)->name('mobile.link.profile');
    Route::native('/user/{username}', Profile::class)->name('mobile.user');
    Route::native('/user/{username}/followers', FollowList::class)->name('mobile.followers');
    Route::native('/user/{username}/following', FollowList::class)->name('mobile.following');
    Route::native('/ask/{username}', Compose::class)->name('mobile.ask');
    Route::native('/question/{id}', Detail::class)->name('mobile.detail');
});
