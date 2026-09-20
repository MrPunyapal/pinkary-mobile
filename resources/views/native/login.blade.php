<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full items-center px-5 py-8 gap-8 safe-area safe-area-bottom">
        <x-pinkary-logo height="56" />

        <native:column class="w-full p-6 gap-6 rounded-md bg-theme-surface border border-theme-outline-variant shadow-lg">
            <native:column class="w-full gap-2">
                <native:text class="text-2xl font-semibold text-theme-on-surface">Log in</native:text>
                <native:text class="text-base text-theme-on-surface-variant">Continue to your Pinkary account.</native:text>
            </native:column>

            @if ($errorMessage !== '')
                <native:column class="w-full p-4 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                    <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                </native:column>
            @endif

            <native:column class="w-full gap-5">
                <native:outlined-text-input label="Email" keyboard="email" native:model="email" :supporting="$fieldErrors['email'] ?? ''" :error="isset($fieldErrors['email'])" />
                <native:outlined-text-input label="Password" :secure="! $showPassword" native:model="password" :supporting="$fieldErrors['password'] ?? ''" :error="isset($fieldErrors['password'])" />
                <native:pressable class="self-end py-1" @tap="togglePasswordVisibility" a11y-label="{{ $showPassword ? 'Hide password' : 'Show password' }}">
                    <native:text class="text-sm font-medium text-theme-accent">{{ $showPassword ? 'Hide password' : 'Show password' }}</native:text>
                </native:pressable>
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
