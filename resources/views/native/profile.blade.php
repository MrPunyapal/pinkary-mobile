<native:top-bar title="Profile">
    <native:top-bar-action id="logout" label="Log out" @tap="logout" />
</native:top-bar>

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full px-6 py-6 gap-5 safe-area-bottom">
        @if ($loading)
            <native:column class="w-full gap-3">
                <native:text class="text-base text-theme-on-surface-variant">Loading your profile…</native:text>
                <native:activity-indicator />
            </native:column>
        @elseif ($errorMessage !== '')
            <native:column class="w-full p-4 rounded-xl bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
            </native:column>
        @else
            <native:column class="w-full gap-2 p-5 bg-theme-surface rounded-3xl border border-theme-outline">
                <native:text class="text-3xl font-bold text-theme-on-surface">{{ data_get($profile, 'name') }}</native:text>
                <native:text class="text-base text-theme-primary">{{ '@'.data_get($profile, 'username') }}</native:text>
                @if (data_get($profile, 'bio'))
                    <native:text class="text-base leading-relaxed text-theme-on-surface-variant">{{ data_get($profile, 'bio') }}</native:text>
                @endif
            </native:column>

            <native:column class="w-full gap-3 p-5 bg-theme-surface rounded-3xl border border-theme-outline">
                <native:text class="text-sm font-semibold uppercase tracking-widest text-theme-on-surface-variant">Account</native:text>
                <native:text class="text-base text-theme-on-surface">{{ data_get($profile, 'email') }}</native:text>
                <native:text class="text-sm text-theme-on-surface-variant">Member since {{ data_get($profile, 'member_since.human') }}</native:text>
            </native:column>
        @endif
    </native:column>
</native:scroll-view>
