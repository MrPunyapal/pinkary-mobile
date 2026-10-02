<?php

declare(strict_types=1);

namespace App\NativeLayout;

use App\Icons\Android;
use App\Icons\Ios;
use App\NativeComponents\Compose;
use App\NativeComponents\Login;
use App\NativeComponents\Notifications;
use App\NativeComponents\Register;
use App\Support\AccountStore;
use App\Support\UnreadCount;
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

    private const string ACTIVE_COLOR = '#EC4899';

    // Web idle tint (../pinkary/resources/views/layouts/navigation.blade.php:8):
    // #64748B light / #94A3B8 dark. iOS 26 keeps the system tint for
    // unselected tab items (vendor/nativephp/mobile/src/Edge/Layouts/
    // Builders/TabBar.php:118-125), so this is Android parity and iOS
    // follows the OS.
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

        // Web guest branch (../pinkary/resources/views/layouts/navigation.blade.php:292-319):
        // Feed / Log in / Register / Menu. Profile, Bookmarks and
        // Notifications all require a token, so offering them to guests
        // would be three dead controls that bounce to login.
        //
        // AccountStore, not CurrentUser: the profile snapshot is filled in
        // asynchronously after a cold start, so a signed-in user would see
        // the guest bar flash until it lands.
        if (AccountStore::activeId() === null) {
            return $bar
                ->add(Tab::link('Feed', '/feed', ios: Ios::HouseFill, android: Android::Home))
                ->add(Tab::action('Log in', ios: Ios::ArrowRightSquare, android: Android::Login)->press('openLogin'))
                ->add(Tab::action('Register', ios: Ios::PersonBadgePlus, android: Android::PersonAdd)->press('openRegister'))
                ->add($this->menuTab());
        }

        $notifications = Tab::link('Notifications', '/notifications', ios: Ios::BellFill, android: Android::Notifications);

        // Web parity: the bell carries the pending count, capped like
        // the web's nav count (`> 20 ? '20+'`). Refreshed on feed entry
        // and notification visits; cleared on logout. The web tints the
        // bell with the user's own `right_color`, which the API's
        // UserResource does not expose, so the brand pink stands in until
        // the API returns it.
        $badge = $this->unreadBadge();

        if ($badge !== null) {
            $notifications->badge($badge, self::ACTIVE_COLOR);
        }

        // Web parity (../pinkary/resources/views/layouts/navigation.blade.php:254-290):
        // Home, Profile, Bookmarks, Notifications, and the Menu dropdown.
        return $bar
            ->add(Tab::link('Feed', '/feed', ios: Ios::HouseFill, android: Android::Home))
            ->add(Tab::link('Profile', '/profile', ios: Ios::PersonFill, android: Android::Person))
            ->add(Tab::link('Bookmarks', '/bookmarks', ios: Ios::BookmarkFill, android: Android::Bookmark))
            ->add($notifications)
            ->add($this->menuTab());
    }

    // Topbar drawer removed: NativePHP's drawer host hardcodes a floating ☰
    // affordance at TopStart. Removing it from the layout eliminates the
    // hamburger button from all topbars, while the Menu tab in the bottom
    // bar provides access to menu/settings options matching web parity.
    public function drawer(NativeComponent $screen): ?Drawer
    {
        return null;
    }

    // Web parity: the compose FAB lives inside `@auth`
    // (../pinkary/resources/views/layouts/app.blade.php:129), so guests
    // never see it. Compose itself and the auth cards carry their own
    // primary action, so they need no floating overlay.
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

        // The web fades the FAB out while the menu sheet is open
        // (../pinkary/resources/views/layouts/app.blade.php:131-133).
        $menuOpen = property_exists($screen, 'appMenuOpen') && $screen->appMenuOpen;

        return FloatingOverlay::make(view('native.compose-fab', ['appMenuOpen' => $menuOpen]))
            ->offset(self::FAB_OFFSET);
    }

    /**
     * The bars-menu sheet, identical for guests and signed-in users
     * (web: the dropdown lives outside the `@auth` branch).
     */
    private function menuTab(): Tab
    {
        return Tab::action('Menu', ios: Ios::Line3Horizontal, android: Android::Menu)->press('openAppMenu');
    }

    /**
     * Pending notification count formatted for the bell badge, capped at 20+.
     */
    private function unreadBadge(): ?string
    {
        $unread = UnreadCount::get();

        if (! is_int($unread) || $unread <= 0) {
            return null;
        }

        return $unread > self::BADGE_CAP ? self::BADGE_CAP.'+' : (string) $unread;
    }
}
