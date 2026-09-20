<?php

declare(strict_types=1);

namespace App\NativeLayout;

use App\Icons\Android;
use App\Icons\Ios;
use App\NativeComponents\Compose;
use App\NativeComponents\Detail;
use App\NativeComponents\Home;
use App\NativeComponents\Notifications;
use App\NativeComponents\Profile;
use App\Support\CurrentUser;
use Native\Mobile\Edge\Layouts\Builders\NavAction;
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

    // BISECT-3 (restore step 1): plain chrome only — no titleView,
    // no actions, no drawer, no overlay.
    public function navBar(NativeComponent $screen): ?NavBar
    {
        $bar = NavBar::make()->displayMode('inline');

        if ($screen instanceof Home) {
            // Title stays as a fallback for Jump apps that ignore the
            // custom titleView slot (otherwise the bar shows nothing).
            $bar->title('Pinkary')->titleView(view('native.brand-lockup'));
        } else {
            $bar->title($screen->navTitle());
        }

        if ($screen instanceof Detail
            || $screen instanceof Compose
            || ($screen instanceof Profile && $screen->username !== '')) {
            $bar->back();
        }

        if ($screen instanceof Notifications) {
            $bar->action(
                NavAction::make('ignore-all')
                    ->label('Ignore all')
                    ->press('markAllRead')
                    ->a11yLabel('Ignore all notifications')
            );
        }

        $bar->action(
            NavAction::make('profile')
                ->icon(ios: Ios::PersonFill, android: Android::Person)
                ->press('openOwnProfile')
                ->a11yLabel('Your profile')
        );

        return $bar;
    }

    public function tabBar(NativeComponent $screen): ?TabBar
    {
        return TabBar::make()
            ->activeColor('#EC4899')
            ->labelVisibility('unlabeled')
            ->add(Tab::link('Feed', '/feed', ios: Ios::HouseFill, android: Android::Home))
            ->add(Tab::link('Search', '/search', ios: Ios::Magnifyingglass, android: Android::Search))
            ->add(Tab::link('Bookmarks', '/bookmarks', ios: Ios::BookmarkFill, android: Android::Bookmark))
            ->add(Tab::link('Notifications', '/notifications', ios: Ios::BellFill, android: Android::Notifications))
            ->add(Tab::link('Profile', '/profile', ios: Ios::PersonFill, android: Android::Person));
    }

    // Drawer restored with the real sidebar (scroll-view root removed —
    // that was the instant-close culprit). Overlay still off.
    public function drawer(NativeComponent $screen): ?Drawer
    {
        return Drawer::make(view('native.sidebar', [
            'user' => CurrentUser::get(),
        ]))->modal();
    }

    // Single compose entry lives in the floating overlay (including
    // guests — Compose redirects to login), so screens carry no FAB.
    public function floatingOverlay(NativeComponent $screen): ?FloatingOverlay
    {
        if ($screen instanceof Compose) {
            return null;
        }

        return FloatingOverlay::make(view('native.compose-fab'))->offset(88);
    }
}
