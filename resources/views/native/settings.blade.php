@php
    // Web parity (layouts/navigation:342): Settings on the web IS the
    // profile editor, so the row leads there. The logout label follows the
    // account rule — name the account when others are stored, plain
    // "Log Out" otherwise.
    $accountCount = \App\Support\AccountStore::count();
    $username = (string) data_get($profile, 'username', '');
    $logoutLabel = $accountCount > 1 && $username !== '' ? 'Log out of @'.$username : 'Log Out';
@endphp

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        <x-page-heading title="Settings" size="3xl" />

        @if ($refreshing ?? false)
            <x-refresh-bar />
        @endif

        <native:column class="w-full px-4 py-4 gap-4">
        @if ($loading)
            <x-skeleton-settings />
        @elseif ($errorMessage !== '')
            <native:column class="w-full p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry">
                    <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                </native:pressable>
            </native:column>
        @else
            <native:column class="w-full gap-3 p-5 rounded-md bg-theme-surface border border-theme-outline-variant">
                <native:row class="w-full items-center gap-3">
                    <x-avatar :user="$profile" :size="10" :link="false" />
                    <native:column class="flex-1 min-w-0">
                        <native:text class="text-base font-semibold text-theme-on-surface" max-lines="1">{{ data_get($profile, 'name') }}</native:text>
                        <native:text class="text-sm text-theme-on-surface-variant" max-lines="1">{{ '@'.data_get($profile, 'username') }}</native:text>
                    </native:column>
                </native:row>
                @if (data_get($profile, 'email'))
                    <native:text class="text-sm text-theme-on-surface">Signed in as {{ data_get($profile, 'email') }}</native:text>
                @endif
            </native:column>

            <native:pressable class="w-full p-4 rounded-md bg-theme-surface border border-theme-outline-variant" @tap="editProfile" a11y-label="Edit profile">
                <native:text class="text-sm font-medium text-theme-on-surface">Edit profile</native:text>
            </native:pressable>

            <native:pressable class="w-full p-4 rounded-md bg-theme-surface border border-theme-outline-variant" @tap="openOwnProfile" a11y-label="View profile">
                <native:text class="text-sm font-medium text-theme-on-surface">View profile</native:text>
            </native:pressable>

            {{-- Source code stays in the app menu sheet only. --}}

            <native:pressable class="w-full p-4 rounded-md bg-theme-surface border border-theme-destructive" @tap="logout" a11y-label="{{ $logoutLabel }}">
                <native:text class="text-sm font-semibold text-theme-destructive">{{ $logoutLabel }}</native:text>
            </native:pressable>
        @endif

        <native:column class="w-full h-20" />
        </native:column>
    </native:column>
</native:scroll-view>

@include('native.app-menu-sheet')
