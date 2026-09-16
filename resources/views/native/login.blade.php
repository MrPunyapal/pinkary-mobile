<native:top-bar title="Log in" back />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full px-5 py-8 gap-8 safe-area-bottom">
        <x-pinkary-logo class="w-full" />

        <native:column class="w-full p-6 gap-6 rounded-lg bg-theme-surface border border-theme-outline-variant">
            <native:column class="w-full gap-2">
                <native:text class="text-2xl font-semibold text-theme-on-surface">Log in</native:text>
                <native:text class="text-base text-theme-on-surface-variant">Continue to your Pinkary account.</native:text>
            </native:column>

            @if ($errorMessage !== '')
                <native:column class="w-full p-4 rounded-lg bg-theme-destructive/15 border border-theme-destructive">
                    <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                </native:column>
            @endif

            <native:column class="w-full gap-5">
                <native:outlined-text-input label="Email" keyboard="email" native:model="email" />
                <native:outlined-text-input label="Password" secure native:model="password" />
            </native:column>

            <native:button variant="primary" :disabled="$busy" @tap="authenticate">{{ $busy ? 'Logging in…' : 'Log in' }}</native:button>

            <native:column class="w-full pt-6 border-t border-theme-outline-variant">
                <native:pressable class="w-full items-center py-3" @tap="register">
                    <native:text class="text-sm text-center text-theme-accent">New to Pinkary? Create an account</native:text>
                </native:pressable>
            </native:column>
        </native:column>
    </native:column>
</native:scroll-view>
