@if ($appMenuOpen)
    @php
        $menuUser = \App\Support\CurrentUser::get();
        $isLoggedIn = $menuUser !== null;
        $menuName = is_array($menuUser) ? (string) ($menuUser['name'] ?? '') : '';
        $menuUsername = is_array($menuUser) ? (string) ($menuUser['username'] ?? '') : '';
        $menuAvatar = is_array($menuUser) ? ($menuUser['avatar'] ?? null) : null;
    @endphp
    <native:bottom-sheet visible @dismiss="closeAppMenu">
        <native:column class="w-full gap-1 px-5 py-4 bg-theme-surface">
            @if ($isLoggedIn)
                <native:pressable class="w-full py-3 border-b border-theme-outline-variant" @tap="openOwnProfile" a11y-label="Open your profile">
                    <native:row class="w-full items-center gap-3">
                        <x-avatar :user="['name' => $menuName, 'username' => $menuUsername, 'avatar' => $menuAvatar]" :size="10" :link="false" />
                        <native:column class="flex-1 min-w-0">
                            <native:text class="text-base font-semibold text-theme-on-surface">{{ $menuName !== '' ? $menuName : 'Pinkary' }}</native:text>
                            @if ($menuUsername !== '')
                                <native:text class="text-sm text-theme-on-surface-variant">{{ '@'.$menuUsername }}</native:text>
                            @endif
                        </native:column>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="openSettings" a11y-label="Settings">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::Gearshape" :android="\App\Icons\AndroidOutlined::Settings" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-base text-theme-on-surface">Settings</native:text>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="openSource" a11y-label="Source code">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::ChevronLeftForwardslashChevronRight" :android="\App\Icons\AndroidOutlined::Code" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-base text-theme-on-surface">Source code</native:text>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="logout" a11y-label="Log out">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::RectanglePortraitAndArrowRight" :android="\App\Icons\Android::Logout" :size="20" color="#B91C1C" />
                        <native:text class="text-base font-medium text-theme-destructive">Log out</native:text>
                    </native:row>
                </native:pressable>
            @else
                <native:pressable class="w-full py-3" @tap="openLogin" a11y-label="Log in">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::ArrowRightSquare" :android="\App\Icons\Android::Login" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-base text-theme-on-surface">Log in</native:text>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="openRegister" a11y-label="Register">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::PersonBadgePlus" :android="\App\Icons\Android::PersonAdd" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-base text-theme-on-surface">Register</native:text>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="openSource" a11y-label="Source code">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::ChevronLeftForwardslashChevronRight" :android="\App\Icons\AndroidOutlined::Code" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-base text-theme-on-surface">Source code</native:text>
                    </native:row>
                </native:pressable>
            @endif

            <native:pressable class="w-full py-3 border-t border-theme-outline-variant" @tap="closeAppMenu" a11y-label="Cancel">
                <native:row class="w-full items-center justify-center">
                    <native:text class="text-base font-semibold text-theme-on-surface-variant">Cancel</native:text>
                </native:row>
            </native:pressable>
        </native:column>
    </native:bottom-sheet>
@endif
