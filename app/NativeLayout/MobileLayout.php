<?php

declare(strict_types=1);

namespace App\NativeLayout;

use App\Icons\Android;
use App\Icons\Ios;
use App\NativeComponents\Bookmarks;
use App\NativeComponents\Compose;
use App\NativeComponents\Detail;
use App\NativeComponents\Home;
use App\NativeComponents\Login;
use App\NativeComponents\Notifications;
use App\NativeComponents\Profile;
use App\NativeComponents\Register;
use App\Support\CurrentUser;
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

    // Chrome titles only where no in-content heading exists; the old
    // Jump app left-aligns them and OS chrome is single-line, so every
    // screen now carries exactly one title on old and new clients.
    public function navBar(NativeComponent $screen): ?NavBar
    {
        // Detail, Compose, and other users' profiles render their own
        // in-content top bar (title + back) — chrome would duplicate it.
        // Bookmarks and Notifications carry an in-content page heading,
        // and the auth cards carry their own titles — same duplication.
        if ($screen instanceof Detail
            || $screen instanceof Compose
            || $screen instanceof Bookmarks
            || $screen instanceof Notifications
            || $screen instanceof Login
            || $screen instanceof Register
            || ($screen instanceof Profile && $screen->username !== '')) {
            return null;
        }

        $bar = NavBar::make()->displayMode('inline');

        // Home carries its brand as centered in-content header (the old
        // Jump app left-aligns chrome titles); other screens use the
        // chrome title.
        if (! $screen instanceof Home) {
            $bar->title($screen->navTitle());
        }

        return $bar;
    }

    public function tabBar(NativeComponent $screen): ?TabBar
    {
        if ($screen instanceof Login || $screen instanceof Register) {
            return null;
        }

        return TabBar::make()
            ->activeColor('#EC4899')
            ->labelVisibility('unlabeled')
            ->add(Tab::link('Feed', '/feed', ios: Ios::HouseFill, android: Android::Home))
            ->add(Tab::link('Search', '/search', ios: Ios::Magnifyingglass, android: Android::Search))
            ->add(Tab::link('Bookmarks', '/bookmarks', ios: Ios::BookmarkFill, android: Android::Bookmark))
            ->add(Tab::link('Notifications', '/notifications', ios: Ios::BellFill, android: Android::Notifications))
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
