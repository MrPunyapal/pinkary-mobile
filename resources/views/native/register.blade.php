<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full items-center px-5 py-8 gap-8 safe-area safe-area-bottom">
        <x-pinkary-logo height="56" />

        <native:column class="w-full p-6 gap-6 rounded-md bg-theme-surface border border-theme-outline-variant shadow-lg">
            <native:column class="w-full gap-2">
                <native:text class="text-2xl font-semibold text-theme-on-surface">Create your account</native:text>
                <native:text class="text-base text-theme-on-surface-variant">Join Pinkary and start sharing what you are building.</native:text>
            </native:column>

            @if ($errorMessage !== '')
                <native:column class="w-full p-4 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                    <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                </native:column>
            @endif

            <native:column class="w-full gap-4">
                <native:outlined-text-input label="Name" native:model="name" />
                <native:outlined-text-input label="Username" native:model="username" />
                <native:outlined-text-input label="Email" keyboard="email" native:model="email" />
                <native:outlined-text-input label="Password" secure native:model="password" />
                <native:outlined-text-input label="Confirm password" secure native:model="passwordConfirmation" />
                <native:checkbox label="I agree to the Pinkary terms." native:model="terms" />
            </native:column>

            <native:button variant="primary" :disabled="$busy" @tap="createAccount">{{ $busy ? 'Creating account…' : 'Create account' }}</native:button>

            <native:column class="w-full pt-6 border-t border-theme-outline-variant">
                <native:pressable class="w-full items-center py-3" @tap="login">
                    <native:text class="text-sm text-center text-theme-accent">Already have an account? Log in</native:text>
                </native:pressable>
            </native:column>
        </native:column>
    </native:column>
</native:scroll-view>
