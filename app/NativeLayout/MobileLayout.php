<?php

declare(strict_types=1);

namespace App\NativeLayout;

use App\Icons\Android;
use App\Icons\Ios;
use App\NativeComponents\Bookmarks;
use App\NativeComponents\Compose;
use App\NativeComponents\Detail;
use App\NativeComponents\EditProfile;
use App\NativeComponents\FollowList;
use App\NativeComponents\Home;
use App\NativeComponents\Login;
use App\NativeComponents\Notifications;
use App\NativeComponents\Profile;
use App\NativeComponents\Register;
use App\NativeComponents\Search;
use App\Support\CurrentUser;
use App\Support\UnreadCount;
use Native\Mobile\Edge\Layouts\Builders\NavBar;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\UI\Builders\Drawer;
use Native\Mobile\UI\Builders\FloatingOverlay;
use Native\Mobile\UI\Concerns\HasFloatingOverlay;
use Native\Mobile\UI\Concerns\HasLayoutDrawer;

final class MobileLayout extends NativeLayout
{
    use HasFloatingOverlay;
    use HasLayoutDrawer;

    // No chrome titles anywhere: the old Jump app left-aligns them into
    // the drawer hamburger (single-line chrome), so identity lives in
    // content — the centered brand (Home), the in-content top bar
    // (Detail, Compose, follow lists, profile editor, all profiles),
    // the centered page heading (Bookmarks, Notifications), or the
    // control itself (Search). Screens with an in-content top bar need
    // no chrome bar at all; the rest get a titleless bar (hamburger).
    public function navBar(NativeComponent $screen): ?NavBar
    {
        // Detail, Compose, follow lists, the profile editor, and every
        // profile render their own in-content top bar (title + back or
        // menu-aware title) — chrome would duplicate it.
        // Bookmarks and Notifications carry an in-content page heading,
        // and the auth cards carry their own titles — same duplication.
        if ($screen instanceof Detail
            || $screen instanceof Compose
            || $screen instanceof FollowList
            || $screen instanceof EditProfile
            || $screen instanceof Bookmarks
            || $screen instanceof Notifications
            || $screen instanceof Search
            || $screen instanceof Login
            || $screen instanceof Register
            || $screen instanceof Profile) {
            return null;
        }

        return NavBar::make()->displayMode('inline');
    }

    public function tabBar(NativeComponent $screen): ?TabBar
    {
        if ($screen instanceof Login || $screen instanceof Register) {
            return null;
        }

        $notifications = Tab::link('Notifications', '/notifications', ios: Ios::BellFill, android: Android::Notifications);

        // Web parity: the bell carries the pending count, capped like
        // the web's nav count (`> 20 ? '20+'`). Refreshed on feed entry
        // and notification visits; cleared on logout.
        $unread = UnreadCount::get();

        if (is_int($unread) && $unread > 0) {
            $notifications->badge($unread > 20 ? '20+' : (string) $unread);
        }

        return TabBar::make()
            ->activeColor('#EC4899')
            ->labelVisibility('unlabeled')
            ->add(Tab::link('Feed', '/feed', ios: Ios::HouseFill, android: Android::Home))
            ->add(Tab::link('Search', '/search', ios: Ios::Magnifyingglass, android: Android::Search))
            ->add(Tab::link('Bookmarks', '/bookmarks', ios: Ios::BookmarkFill, android: Android::Bookmark))
            ->add($notifications)
            ->add(Tab::link('Profile', '/profile', ios: Ios::PersonFill, android: Android::Person));
    }

    // Modal drawer with the real sidebar (its root must stay a plain
    // column — a scroll-view root instant-closes old Jump apps).
    public function drawer(NativeComponent $screen): ?Drawer
    {
        // Auth screens have no menu actions (missing-method tap risk).
        if ($screen instanceof Login || $screen instanceof Register) {
            return null;
        }

        return Drawer::make(view('native.sidebar', [
            'user' => CurrentUser::get(),
        ]))->modal();
    }

    // Single compose entry lives in the floating overlay (including
    // guests — Compose redirects to login), so screens carry no FAB.
    public function floatingOverlay(NativeComponent $screen): ?FloatingOverlay
    {
        if ($screen instanceof Compose
            || $screen instanceof Login
            || $screen instanceof Register) {
            return null;
        }

        return FloatingOverlay::make(view('native.compose-fab'))->offset(88);
    }
}
