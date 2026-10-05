<?php

declare(strict_types=1);

namespace App\NativeLayout;

use App\Icons\Android;
use App\Icons\Ios;
use App\NativeComponents\Compose;
use App\NativeComponents\Login;
use App\NativeComponents\Register;
use App\Support\AccountStore;
use App\Support\UnreadCount;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\UI\Builders\FloatingOverlay;
use Native\Mobile\UI\Concerns\HasFloatingOverlay;
use Native\Mobile\UI\Concerns\HasLayoutDrawer;

final class MobileLayout extends NativeLayout
{
    use HasFloatingOverlay;
    use HasLayoutDrawer;

    private const string ACTIVE_COLOR = '#EC4899';

    // iOS uses the system tint for unselected tab items.
    private const string IDLE_COLOR = '#64748B';

    private const int BADGE_CAP = 20;

    private const int FAB_OFFSET = 88;

    public function tabBar(NativeComponent $screen): ?TabBar
    {
        if ($screen instanceof Login || $screen instanceof Register) {
            return null;
        }

        $bar = TabBar::make()
            ->activeColor(self::ACTIVE_COLOR)
            ->textColor(self::IDLE_COLOR)
            ->labelVisibility('unlabeled');

        // Read auth state from secure storage; profile hydration is asynchronous.
        if (AccountStore::activeId() === null) {
            return $bar
                ->add(Tab::link('Feed', '/feed', ios: Ios::HouseFill, android: Android::Home))
                ->add(Tab::action('Log in', ios: Ios::ArrowRightSquare, android: Android::Login)->press('openLogin'))
                ->add(Tab::action('Register', ios: Ios::PersonBadgePlus, android: Android::PersonAdd)->press('openRegister'))
                ->add($this->menuTab());
        }

        $notifications = Tab::link('Notifications', '/notifications', ios: Ios::BellFill, android: Android::Notifications);

        $badge = $this->unreadBadge();

        if ($badge !== null) {
            $notifications->badge($badge, self::ACTIVE_COLOR);
        }

        return $bar
            ->add(Tab::link('Feed', '/feed', ios: Ios::HouseFill, android: Android::Home))
            ->add(Tab::link('Profile', '/profile', ios: Ios::PersonFill, android: Android::Person))
            ->add(Tab::link('Bookmarks', '/bookmarks', ios: Ios::BookmarkFill, android: Android::Bookmark))
            ->add($notifications)
            ->add($this->menuTab());
    }

    public function drawer(NativeComponent $screen): null
    {
        return null;
    }

    public function floatingOverlay(NativeComponent $screen): ?FloatingOverlay
    {
        if ($screen instanceof Compose
            || $screen instanceof Login
            || $screen instanceof Register) {
            return null;
        }

        if (AccountStore::activeId() === null) {
            return null;
        }

        $menuOpen = property_exists($screen, 'appMenuOpen') && $screen->appMenuOpen;

        return FloatingOverlay::make(view('native.compose-fab', ['appMenuOpen' => $menuOpen]))
            ->offset(self::FAB_OFFSET);
    }

    private function menuTab(): Tab
    {
        return Tab::action('Menu', ios: Ios::Line3Horizontal, android: Android::Menu)->press('openAppMenu');
    }

    private function unreadBadge(): ?string
    {
        $unread = UnreadCount::get();

        if (! is_int($unread) || $unread <= 0) {
            return null;
        }

        return $unread > self::BADGE_CAP ? self::BADGE_CAP.'+' : (string) $unread;
    }
}
