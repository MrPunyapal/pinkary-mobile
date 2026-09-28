<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full items-center px-5 py-8 gap-8 safe-area safe-area-bottom">
        {{-- Web guest card: the wordmark sits above the card at h-12. --}}
        <x-pinkary-logo height="12" />

        <native:column class="w-full p-6 gap-6 rounded-md bg-theme-surface border border-theme-outline-variant shadow-lg">
            <native:column class="w-full gap-2">
                <native:text class="text-2xl font-semibold text-theme-on-surface">{{ $addingAccount ? 'Add Account' : 'Log in' }}</native:text>
                <native:text class="text-base text-theme-on-surface-variant">{{ $addingAccount ? 'Sign in with another Pinkary account to switch between them easily.' : 'Continue to your Pinkary account.' }}</native:text>
            </native:column>

            @if ($addingAccount && $activeAccountUsername !== '')
                {{-- Web parity (auth/login:15-29): who stays signed in while
                    the next account is added. Flat — no nested slots. --}}
                <native:row class="w-full items-center gap-3 p-3 rounded-md bg-theme-surface-variant border border-theme-outline-variant">
                    <x-avatar :user="['username' => $activeAccountUsername, 'name' => $activeAccountUsername]" :size="8" :link="false" />
                    <native:column class="flex-1 gap-0.5 min-w-0">
                        <native:text class="text-xs text-theme-on-surface-variant">Currently signed in as</native:text>
                        <native:text class="text-sm font-semibold text-theme-on-surface" max-lines="1">{{ '@'.$activeAccountUsername }}</native:text>
                    </native:column>
                </native:row>
            @endif

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

                {{-- Web parity (auth/login:63-82), minus "Remember me":
                     TokenStorage already keeps the session in the device
                     keychain, so a checkbox here could not do anything. --}}
                <native:row class="w-full items-center justify-end">
                    <native:pressable class="py-1" @tap="forgotPassword" a11y-label="Forgot password?">
                        <native:text class="text-sm font-medium text-theme-primary">Forgot password?</native:text>
                    </native:pressable>
                </native:row>
            </native:column>

            <native:button variant="primary" :disabled="$busy" @tap="authenticate">{{ $busy ? 'Logging in…' : ($addingAccount ? 'Add Account' : 'Log in') }}</native:button>

            <native:column class="w-full pt-6 border-t border-theme-outline-variant">
                <native:pressable class="w-full items-center py-3" @tap="register">
                    <native:text class="text-sm text-center text-theme-accent">New to Pinkary? Create an account</native:text>
                </native:pressable>
            </native:column>
        </native:column>
    </native:column>
</native:scroll-view>
