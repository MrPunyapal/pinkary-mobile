<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full items-center px-5 py-6 gap-6 safe-area">
        {{-- Web guest card: the wordmark sits above the card at h-12. --}}
        <x-pinkary-logo height="12" />

        <native:column class="w-full p-5 gap-5 rounded-md bg-theme-surface border border-theme-outline-variant">
            <native:column class="w-full gap-2">
                <native:text class="text-2xl font-semibold text-theme-on-surface">Create your account</native:text>
                <native:text class="text-sm leading-relaxed text-theme-on-surface-variant">Set up your Pinkary profile and start sharing your links and conversations.</native:text>
            </native:column>

            @if ($errorMessage !== '')
                <native:column class="w-full p-4 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                    <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                </native:column>
            @endif

            <native:column class="w-full gap-4">
                <x-auth-field label="Name" model="name" :error="$fieldErrors['name'] ?? ''" />
                <x-auth-field label="Username" model="username" :error="$fieldErrors['username'] ?? ''" />
                <x-auth-field label="Email" model="email" keyboard="email" :error="$fieldErrors['email'] ?? ''" />
                <x-auth-field label="Password" model="password" :password="true" :show-password="$showPassword" :error="$fieldErrors['password'] ?? ''" />
                <x-auth-field label="Confirm password" model="passwordConfirmation" :password="true" :show-password="$showPassword" :error="$fieldErrors['passwordConfirmation'] ?? ''" />

                {{-- Web parity (auth/register:101-124): the 18+ consent with
                    both legal pages as links. Native text cannot hold a
                    pressable, so the sentence and its two links sit in a
                    column — flat, no nested slots. --}}
                <native:row class="w-full items-start gap-2">
                    <native:checkbox a11y-label="I agree to the Pinkary terms of service and privacy policy" native:model="terms" />
                    <native:column class="flex-1 gap-1 min-w-0">
                        <native:text class="text-xs leading-relaxed text-theme-on-surface-variant">By signing up, I confirm that I am at least 18 years old and accept the</native:text>
                        <native:row class="w-full items-center gap-2 flex-wrap">
                            <native:pressable class="py-0.5" @tap="openTerms" a11y-label="Open the Terms of Service">
                                <native:text class="text-xs font-medium text-theme-primary">Terms of Service</native:text>
                            </native:pressable>
                            <native:text class="text-sm text-theme-on-surface-variant">and</native:text>
                            <native:pressable class="py-0.5" @tap="openPrivacy" a11y-label="Open the Privacy Policy">
                                <native:text class="text-xs font-medium text-theme-primary">Privacy Policy</native:text>
                            </native:pressable>
                            <native:text class="text-sm text-theme-on-surface-variant">.</native:text>
                        </native:row>
                    </native:column>
                </native:row>
            </native:column>

            <x-captcha-check :required="$captchaRequired" :loading="$captchaLoading" :completed="$captchaCompleted" :url="$captchaUrl" :error="$captchaError" />

            <native:pressable class="w-full h-11 items-center justify-center rounded-md bg-theme-primary {{ $busy ? 'opacity-50' : '' }}" @tap="createAccount" a11y-label="Register">
                <native:text class="text-sm font-semibold text-white">{{ $busy ? 'Creating account…' : 'Register' }}</native:text>
            </native:pressable>

            <native:column class="w-full pt-6 border-t border-theme-outline-variant">
                <native:row class="w-full items-center justify-center gap-1 py-3">
                    <native:text class="text-sm text-theme-on-surface-variant">Already have an account?</native:text>
                    <native:pressable class="py-0.5" @tap="login" a11y-label="Sign in here">
                        <native:text class="text-sm font-medium text-theme-primary">Sign in here</native:text>
                    </native:pressable>
                </native:row>
            </native:column>
        </native:column>
    </native:column>
</native:scroll-view>
