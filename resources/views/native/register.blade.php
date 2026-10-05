<native:scroll-view class="bg-theme-background h-full w-full">
    <native:column class="safe-area w-full items-center gap-6 px-5 py-6">
        <x-pinkary-logo height="12" />

        <native:column class="bg-theme-surface border-theme-outline-variant w-full gap-5 rounded-md border p-5">
            <native:column class="w-full gap-2">
                <native:text class="text-theme-on-surface text-2xl font-semibold">Create your account</native:text>
                <native:text class="text-theme-on-surface-variant text-sm leading-relaxed">Set up your Pinkary profile and start sharing your links and conversations.</native:text>
            </native:column>

            @if ($errorMessage !== '')
                <native:column class="bg-theme-destructive/15 border-theme-destructive w-full rounded-md border p-4">
                    <native:text class="text-theme-destructive text-sm">{{ $errorMessage }}</native:text>
                </native:column>
            @endif

            <native:column class="w-full gap-4">
                <x-auth-field label="Name" model="name" :error="$fieldErrors['name'] ?? ''" />
                <x-auth-field label="Username" model="username" :error="$fieldErrors['username'] ?? ''" />
                <x-auth-field label="Email" model="email" keyboard="email" :error="$fieldErrors['email'] ?? ''" />
                <x-auth-field
                    label="Password"
                    model="password"
                    :password="true"
                    :show-password="$showPassword"
                    :error="$fieldErrors['password'] ?? ''"
                />
                <x-auth-field
                    label="Confirm password"
                    model="passwordConfirmation"
                    :password="true"
                    :show-password="$showPassword"
                    :error="$fieldErrors['passwordConfirmation'] ?? ''"
                />

                {{-- Native text cannot contain pressable children; render legal links separately. --}}
                <native:row class="w-full items-start gap-2">
                    <native:checkbox
                        a11y-label="I agree to the Pinkary terms of service and privacy policy"
                        native:model="terms"
                    />
                    <native:column class="min-w-0 flex-1 gap-1">
                        <native:text class="text-theme-on-surface-variant text-xs leading-relaxed">By signing up, I confirm that I am at least 18 years old and accept the</native:text>
                        <native:row class="w-full flex-wrap items-center gap-2">
                            <native:pressable class="py-0.5" @tap="openTerms" a11y-label="Open the Terms of Service">
                                <native:text class="text-theme-primary text-xs font-medium">Terms of Service</native:text>
                            </native:pressable>
                            <native:text class="text-theme-on-surface-variant text-sm">and</native:text>
                            <native:pressable class="py-0.5" @tap="openPrivacy" a11y-label="Open the Privacy Policy">
                                <native:text class="text-theme-primary text-xs font-medium">Privacy Policy</native:text>
                            </native:pressable>
                            <native:text class="text-theme-on-surface-variant text-sm">.</native:text>
                        </native:row>
                    </native:column>
                </native:row>
            </native:column>

            <x-captcha-check
                :required="$captchaRequired"
                :loading="$captchaLoading"
                :completed="$captchaCompleted"
                :url="$captchaUrl"
                :error="$captchaError"
            />

            <native:pressable
                class="w-full h-11 items-center justify-center rounded-md bg-theme-primary {{ $busy ? 'opacity-50' : '' }}"
                @tap="createAccount"
                a11y-label="Register"
            >
                <native:text class="text-sm font-semibold text-white">{{ $busy ? 'Creating account…' : 'Register' }}</native:text>
            </native:pressable>

            <native:column class="border-theme-outline-variant w-full border-t pt-6">
                <native:row class="w-full items-center justify-center gap-1 py-3">
                    <native:text class="text-theme-on-surface-variant text-sm">Already have an account?</native:text>
                    <native:pressable class="py-0.5" @tap="login" a11y-label="Sign in here">
                        <native:text class="text-theme-primary text-sm font-medium">Sign in here</native:text>
                    </native:pressable>
                </native:row>
            </native:column>
        </native:column>
    </native:column>
</native:scroll-view>
