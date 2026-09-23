<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        <x-page-heading title="Settings" />

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
                    <native:text class="text-sm text-theme-on-surface">{{ data_get($profile, 'email') }}</native:text>
                @endif
                @if (data_get($profile, 'member_since') || data_get($profile, 'member_since.human'))
                    <native:text class="text-sm text-theme-on-surface-variant">Member since {{ data_get($profile, 'member_since.human', data_get($profile, 'member_since')) }}</native:text>
                @endif
            </native:column>

            <native:pressable class="w-full p-4 rounded-md bg-theme-surface border border-theme-outline-variant" @tap="openOwnProfile" a11y-label="View profile">
                <native:text class="text-sm font-medium text-theme-on-surface">View profile</native:text>
            </native:pressable>

            <native:pressable class="w-full p-4 rounded-md bg-theme-surface border border-theme-outline-variant" @tap="openSource" a11y-label="Source code">
                <native:text class="text-sm font-medium text-theme-on-surface">Source code</native:text>
            </native:pressable>

            <native:pressable class="w-full p-4 rounded-md bg-theme-surface border border-theme-destructive" @tap="logout" a11y-label="Log out">
                <native:text class="text-sm font-semibold text-theme-destructive">Log out</native:text>
            </native:pressable>
        @endif

        <native:column class="w-full h-20" />
        </native:column>
    </native:column>
</native:scroll-view>

@include('native.app-menu-sheet')
