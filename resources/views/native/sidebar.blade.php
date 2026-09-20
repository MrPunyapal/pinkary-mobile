@php
    $user = $user ?? null;
    $name = is_array($user) ? (string) ($user['name'] ?? '') : '';
    $username = is_array($user) ? (string) ($user['username'] ?? '') : '';
    $avatar = is_array($user) ? ($user['avatar'] ?? null) : null;
@endphp

{{-- Drawer content must not use a scroll-view root (instant-close on
    the Jump app); a plain column is enough for four short rows. --}}
<native:column class="w-full gap-0 bg-theme-surface">
    <native:pressable class="w-full px-5 py-6 bg-theme-surface border-b border-theme-outline-variant" @tap="openOwnProfile" a11y-label="Open your profile">
            <native:row class="w-full items-center gap-3">
                <x-avatar :user="['name' => $name, 'username' => $username, 'avatar' => $avatar]" :link="false" />
                <native:column class="flex-1 gap-0.5 min-w-0">
                    <native:text class="text-base font-semibold text-theme-on-surface">{{ $name !== '' ? $name : 'Pinkary' }}</native:text>
                    @if ($username !== '')
                        <native:text class="text-sm text-theme-on-surface-variant">{{ '@'.$username }}</native:text>
                    @endif
                </native:column>
            </native:row>
        </native:pressable>

        <native:pressable class="w-full px-5 py-3.5" @tap="openSettings" a11y-label="Settings">
            <native:row class="w-full items-center gap-3">
                <native:icon :ios="\App\Icons\Ios::Gearshape" :android="\App\Icons\AndroidOutlined::Settings" :size="20" color="#64748B" dark-color="#94A3B8" />
                <native:text class="text-sm font-medium text-theme-on-surface">Settings</native:text>
            </native:row>
        </native:pressable>

        <native:pressable class="w-full px-5 py-3.5" @tap="openSource" a11y-label="Source code">
            <native:row class="w-full items-center gap-3">
                <native:icon :ios="\App\Icons\Ios::ChevronLeftForwardslashChevronRight" :android="\App\Icons\AndroidOutlined::Code" :size="20" color="#64748B" dark-color="#94A3B8" />
                <native:text class="text-sm font-medium text-theme-on-surface">Source code</native:text>
            </native:row>
        </native:pressable>

        <native:pressable class="w-full px-5 py-3.5" @tap="logout" a11y-label="Log out">
            <native:row class="w-full items-center gap-3">
                <native:icon :ios="\App\Icons\Ios::RectanglePortraitAndArrowRight" :android="\App\Icons\Android::Logout" :size="20" color="#B91C1C" />
                <native:text class="text-sm font-medium text-theme-destructive">Log out</native:text>
            </native:row>
        </native:pressable>
</native:column>
