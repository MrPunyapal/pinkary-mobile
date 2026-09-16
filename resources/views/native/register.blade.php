<native:top-bar title="Create account" back />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full px-6 py-6 gap-5 safe-area-bottom">
        <native:column class="w-full gap-2">
            <native:text class="text-3xl font-bold text-theme-on-background">Join Pinkary</native:text>
            <native:text class="text-base text-theme-on-surface-variant">Create your account to start sharing.</native:text>
        </native:column>

        @if ($errorMessage !== '')
            <native:column class="w-full p-4 rounded-xl bg-theme-destructive/15 border border-theme-destructive">
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

        <native:pressable class="w-full items-center py-2" @tap="login">
            <native:text class="text-base text-theme-primary">Already have an account? Log in</native:text>
        </native:pressable>
    </native:column>
</native:scroll-view>
