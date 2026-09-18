<?php

declare(strict_types=1);

namespace App\NativeLayout;

use App\Icons\Android;
use App\Icons\Ios;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

final class MobileLayout extends NativeLayout
{
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
}
