<native:scroll-view class="bg-theme-background h-full w-full">
    <native:column class="safe-area w-full items-center gap-6 px-5 py-6">
        <x-pinkary-logo height="12" />

        <native:column class="bg-theme-surface border-theme-outline-variant w-full gap-5 rounded-md border p-5">
            <native:column class="w-full gap-2">
                <native:text class="text-theme-on-surface text-2xl font-semibold">{{ $addingAccount ? 'Add Account' : 'Log in' }}</native:text>
                <native:text class="text-theme-on-surface-variant text-sm leading-relaxed">{{ $addingAccount ? 'Sign in with another Pinkary account to switch between them easily.' : 'Continue to your Pinkary account.' }}</native:text>
            </native:column>

            @if ($addingAccount && $activeAccountUsername !== '')
                <native:row class="bg-theme-surface-variant border-theme-outline-variant w-full items-center gap-3 rounded-md border p-3">
                    <x-avatar
                        :user="['username' => $activeAccountUsername, 'name' => $activeAccountUsername]"
                        :size="8"
                        :link="false"
                    />
                    <native:column class="min-w-0 flex-1 gap-0.5">
                        <native:text class="text-theme-on-surface-variant text-xs">Currently signed in as</native:text>
                        <native:text
                            class="text-theme-on-surface text-sm font-semibold"
                            max-lines="1"
                        >{{ '@'.$activeAccountUsername }}</native:text>
                    </native:column>
                </native:row>
            @endif

            @if ($errorMessage !== '')
                <native:column class="bg-theme-destructive/15 border-theme-destructive w-full rounded-md border p-4">
                    <native:text class="text-theme-destructive text-sm">{{ $errorMessage }}</native:text>
                </native:column>
            @endif

            <native:column class="w-full gap-5">
                @if ($needsTwoFactor)
                    <native:text class="text-theme-on-surface text-sm">
                        {{ $usingRecoveryCode ? 'Enter one of your recovery codes.' : 'Enter the 6-digit code from your authenticator app.' }}
                    </native:text>

                    <native:outlined-text-input
                        :label="$usingRecoveryCode ? 'Recovery code' : 'Code'"
                        :keyboard="$usingRecoveryCode ? 'text' : 'numeric'"
                        native:model="twoFactorCode"
                        :supporting="$errorMessage"
                        :error="$errorMessage !== ''"
                    />

                    <native:pressable
                        class="self-end py-1"
                        @tap="toggleTwoFactorMethod"
                        a11y-label="{{ $usingRecoveryCode ? 'Use an authenticator code' : 'Use a recovery code' }}"
                    >
                        <native:text class="text-theme-accent text-sm font-medium">{{ $usingRecoveryCode ? 'Use an authenticator code' : 'Use a recovery code' }}</native:text>
                    </native:pressable>

                    <native:pressable class="self-end py-1" @tap="cancelTwoFactor" a11y-label="Use a different account">
                        <native:text class="text-theme-accent text-sm font-medium">Use a different account</native:text>
                    </native:pressable>
                @else
                    <x-auth-field label="Email" model="email" keyboard="email" :error="$fieldErrors['email'] ?? ''" />
                    <x-auth-field
                        label="Password"
                        model="password"
                        :password="true"
                        :show-password="$showPassword"
                        :error="$fieldErrors['password'] ?? ''"
                    />

                    <native:row class="w-full items-center justify-end">
                        <native:pressable class="py-1" @tap="forgotPassword" a11y-label="Forgot password?">
                            <native:text class="text-theme-primary text-sm font-medium">Forgot password?</native:text>
                        </native:pressable>
                    </native:row>
                @endif
            </native:column>

            @if ($needsTwoFactor)
                <native:button
                    class="w-full"
                    variant="primary"
                    :disabled="$busy"
                    @tap="submitTwoFactor"
                >{{ $busy ? 'Checking…' : 'Verify' }}</native:button>
            @else
                <native:pressable
                    class="w-full h-11 items-center justify-center rounded-md bg-theme-primary {{ $busy ? 'opacity-50' : '' }}"
                    @tap="authenticate"
                    a11y-label="{{ $addingAccount ? 'Add Account' : 'Log in' }}"
                >
                    <native:text class="text-sm font-semibold text-white">{{ $busy ? 'Logging in…' : ($addingAccount ? 'Add Account' : 'Log in') }}</native:text>
                </native:pressable>
            @endif

            <native:column class="border-theme-outline-variant w-full border-t pt-6">
                <native:pressable class="w-full items-center py-3" @tap="register">
                    <native:text class="text-theme-accent text-center text-sm">New to Pinkary? Create an account</native:text>
                </native:pressable>
            </native:column>
        </native:column>
    </native:column>
</native:scroll-view>
