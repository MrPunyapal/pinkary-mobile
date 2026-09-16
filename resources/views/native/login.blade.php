<native:top-bar title="Log in" back />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full px-6 py-6 gap-5 safe-area-bottom">
        <native:column class="w-full gap-2">
            <native:text class="text-3xl font-bold text-theme-on-background">Welcome back</native:text>
            <native:text class="text-base text-theme-on-surface-variant">Log in to continue to Pinkary.</native:text>
        </native:column>

        @if ($errorMessage !== '')
            <native:column class="w-full p-4 rounded-xl bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
            </native:column>
        @endif

        <native:column class="w-full gap-4">
            <native:outlined-text-input label="Email" keyboard="email" native:model="email" />
            <native:outlined-text-input label="Password" secure native:model="password" />
        </native:column>

        <native:button variant="primary" :disabled="$busy" @tap="authenticate">{{ $busy ? 'Logging in…' : 'Log in' }}</native:button>

        <native:pressable class="w-full items-center py-2" @tap="register">
            <native:text class="text-base text-theme-primary">New to Pinkary? Create an account</native:text>
        </native:pressable>
    </native:column>
</native:scroll-view>
