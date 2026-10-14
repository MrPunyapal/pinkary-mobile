@php
    $accountCount = \App\Support\AccountStore::count();
    $username = (string) data_get($profile, 'username', '');
    $logoutLabel = $accountCount > 1 && $username !== '' ? 'Log out of @'.$username : 'Log Out';
@endphp

<native:scroll-view class="bg-theme-background h-full w-full">
    <native:column class="w-full gap-0">
        <x-page-heading title="Settings" size="3xl" />

        @if ($refreshing ?? false)
            <x-refresh-bar />
        @endif

        <native:column class="w-full gap-4 px-4 py-4">
            @if ($loading)
                <x-skeleton-settings />
            @elseif ($errorMessage !== '')
                <native:column class="bg-theme-destructive/15 border-theme-destructive w-full gap-3 rounded-md border p-4">
                    <native:text class="text-theme-destructive text-sm">{{ $errorMessage }}</native:text>
                    <native:pressable class="self-start py-1" @tap="retry">
                        <native:text class="text-theme-destructive text-sm font-semibold">Try again</native:text>
                    </native:pressable>
                </native:column>
            @else
                <native:column class="bg-theme-surface border-theme-outline-variant w-full gap-3 rounded-md border p-4">
                    <native:text class="text-theme-on-surface text-base font-semibold">Appearance</native:text>
                    <native:text class="text-theme-on-surface-variant text-sm">Choose how Pinkary looks on this device.</native:text>
                    <native:select label="Appearance" :options="$appearanceOptions" native:model="appearance" />
                    <native:button variant="secondary" @tap="saveAppearance">Apply</native:button>
                    @if ($appearanceMessage !== '')
                        <native:text class="text-theme-on-surface-variant text-sm">{{ $appearanceMessage }}</native:text>
                    @endif
                </native:column>

                <native:column class="bg-theme-surface border-theme-outline-variant w-full gap-3 rounded-md border p-5">
                    <native:row class="w-full items-center gap-3">
                        <x-avatar :user="$profile" :size="10" :link="false" />
                        <native:column class="min-w-0 flex-1">
                            <native:text
                                class="text-theme-on-surface text-base font-semibold"
                                max-lines="1"
                            >{{ data_get($profile, 'name') }}</native:text>
                            <native:text
                                class="text-theme-on-surface-variant text-sm"
                                max-lines="1"
                            >{{ '@'.data_get($profile, 'username') }}</native:text>
                        </native:column>
                    </native:row>
                    @if (data_get($profile, 'email'))
                        <native:text class="text-theme-on-surface text-sm">Signed in as {{ data_get($profile, 'email') }}</native:text>
                    @endif
                </native:column>

                <native:pressable
                    class="bg-theme-surface border-theme-outline-variant w-full rounded-md border p-4"
                    @tap="editProfile"
                    a11y-label="Edit profile"
                >
                    <native:text class="text-theme-on-surface text-sm font-medium">Edit profile</native:text>
                </native:pressable>

                <native:pressable
                    class="bg-theme-surface border-theme-outline-variant w-full rounded-md border p-4"
                    @tap="openAccountSecurity"
                    a11y-label="Account and security"
                >
                    <native:text class="text-theme-on-surface text-sm font-medium">Account and security</native:text>
                </native:pressable>

                <native:pressable
                    class="bg-theme-surface border-theme-outline-variant w-full rounded-md border p-4"
                    @tap="openOwnProfile"
                    a11y-label="View profile"
                >
                    <native:text class="text-theme-on-surface text-sm font-medium">View profile</native:text>
                </native:pressable>

                <native:pressable
                    class="bg-theme-surface border-theme-destructive w-full rounded-md border p-4"
                    @tap="logout"
                    a11y-label="{{ $logoutLabel }}"
                >
                    <native:text class="text-theme-destructive text-sm font-semibold">{{ $logoutLabel }}</native:text>
                </native:pressable>
            @endif

            <native:column class="h-20 w-full" />
        </native:column>
    </native:column>
</native:scroll-view>

@include('native.app-menu-sheet')
