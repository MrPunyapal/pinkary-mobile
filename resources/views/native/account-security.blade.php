<native:scroll-view class="bg-theme-background h-full w-full">
    <native:column class="w-full gap-0">
        <x-page-heading title="Account security" :back="true" />

        <native:column class="w-full gap-5 px-5 py-6">
            @if ($loading)
                <native:text class="text-theme-on-surface-variant text-sm">Loading account security…</native:text>
            @else
                @if ($errorMessage !== '')
                    <native:column class="bg-theme-destructive/15 border-theme-destructive w-full rounded-md border p-4">
                        <native:text class="text-theme-destructive text-sm">{{ $errorMessage }}</native:text>
                    </native:column>
                @endif

                @if ($successMessage !== '')
                    <native:column class="bg-theme-primary/10 border-theme-primary/30 w-full rounded-md border p-4">
                        <native:text class="text-theme-primary text-sm">{{ $successMessage }}</native:text>
                    </native:column>
                @endif

                <native:column class="bg-theme-surface border-theme-outline-variant w-full gap-3 rounded-md border p-4">
                    <native:text class="text-theme-on-surface text-lg font-semibold">Email verification</native:text>
                    <native:text class="text-theme-on-surface-variant text-sm">{{ $email }}</native:text>
                    <native:text class="{{ $emailVerified ? 'text-theme-primary' : 'text-theme-on-surface-variant' }} text-sm">
                        {{ $emailVerified ? 'Your email address is verified.' : 'Your email address is not verified.' }}
                    </native:text>
                    @if ($emailVerifiedAt)
                        <native:text class="text-theme-on-surface-variant text-xs">Verified {{ $emailVerifiedAt }}</native:text>
                    @endif
                    @if ($verificationMessage !== '')
                        <native:text class="text-theme-on-surface-variant text-sm">{{ $verificationMessage }}</native:text>
                    @endif
                    <native:row class="w-full flex-wrap items-center gap-3">
                        @unless ($emailVerified)
                            <native:button variant="primary" :disabled="$busy" @tap="sendVerificationEmail">
                                {{ $busy ? 'Sending…' : 'Send verification email' }}
                            </native:button>
                        @endunless
                        <native:pressable
                            class="py-2"
                            @tap="refreshStatus"
                            a11y-label="Refresh account verification status"
                        >
                            <native:text class="text-theme-primary text-sm font-medium">Refresh status</native:text>
                        </native:pressable>
                    </native:row>
                    <native:text class="text-theme-on-surface-variant text-xs">Open the signed link from your email, then refresh this status.</native:text>
                </native:column>

                <native:column class="bg-theme-surface border-theme-outline-variant w-full gap-4 rounded-md border p-4">
                    <native:text class="text-theme-on-surface text-lg font-semibold">Change password</native:text>
                    <x-auth-field
                        label="Current password"
                        model="currentPassword"
                        :password="true"
                        :show-password="$showPassword"
                    />
                    <x-auth-field
                        label="New password"
                        model="newPassword"
                        :password="true"
                        :show-password="$showPassword"
                    />
                    <x-auth-field
                        label="Confirm new password"
                        model="passwordConfirmation"
                        :password="true"
                        :show-password="$showPassword"
                    />
                    <native:button variant="primary" :disabled="$busy" @tap="changePassword">
                        {{ $busy ? 'Updating…' : 'Update password' }}
                    </native:button>
                </native:column>

                <native:column class="bg-theme-surface border-theme-outline-variant w-full gap-4 rounded-md border p-4">
                    <native:row class="w-full items-center justify-between gap-3">
                        <native:text class="text-theme-on-surface text-lg font-semibold">Two-factor authentication</native:text>
                        <native:text class="{{ $twoFactorEnabled ? 'text-theme-primary' : 'text-theme-on-surface-variant' }} text-sm font-medium">
                            {{ ! $twoFactorStatusLoaded ? 'Unavailable' : ($twoFactorEnabled ? 'On' : ($twoFactorPending ? 'Setup in progress' : 'Off')) }}
                        </native:text>
                    </native:row>

                    @if (! $twoFactorStatusLoaded)
                        <native:text class="text-theme-on-surface-variant text-sm"
                            >Two-factor status could not be loaded. Refresh the account status before making
                            changes.</native:text>
                    @elseif ($twoFactorEnabled)
                        <native:text class="text-theme-on-surface-variant text-sm">Your account requires an authenticator code at sign-in.</native:text>
                        <x-auth-field
                            label="Current password to access recovery codes"
                            model="recoveryCodesPassword"
                            :password="true"
                            :show-password="$showPassword"
                        />
                        <native:row class="w-full flex-wrap items-center gap-2">
                            <native:button variant="secondary" :disabled="$busy" @tap="showRecoveryCodes"
                                >Show recovery codes</native:button>
                            <native:button variant="secondary" :disabled="$busy" @tap="regenerateRecoveryCodes"
                                >Replace recovery codes</native:button>
                        </native:row>

                        @if (count($recoveryCodes) > 0)
                            <native:column class="bg-theme-surface-variant border-theme-outline-variant w-full gap-2 rounded-md border p-3">
                                <native:text class="text-theme-on-surface text-sm font-semibold">Save these codes somewhere safe</native:text>
                                @foreach ($recoveryCodes as $recoveryCode)
                                    <native:text
                                        native:key="recovery-code-{{ $loop->index }}"
                                        class="text-theme-on-surface font-mono text-sm"
                                    >{{ $recoveryCode }}</native:text>
                                @endforeach
                                <native:pressable
                                    class="self-start py-2"
                                    @tap="hideRecoveryCodes"
                                    a11y-label="Hide recovery codes"
                                >
                                    <native:text class="text-theme-primary text-sm font-medium">Hide codes</native:text>
                                </native:pressable>
                            </native:column>
                        @endif

                        <native:column class="border-theme-outline-variant w-full gap-3 border-t pt-4">
                            <native:text class="text-theme-on-surface text-sm font-semibold">Turn off two-factor authentication</native:text>
                            <x-auth-field
                                label="Current password"
                                model="disableTwoFactorPassword"
                                :password="true"
                                :show-password="$showPassword"
                            />
                            <native:button variant="secondary" :disabled="$busy" @tap="disableTwoFactor"
                                >Turn off two-factor</native:button>
                        </native:column>
                    @elseif ($twoFactorPending)
                        <native:text class="text-theme-on-surface-variant text-sm"
                            >Scan this code in your authenticator app, then confirm with a fresh six-digit
                            code.</native:text>
                        @if ($twoFactorQrCode !== '')
                            <native:image
                                src="{{ $twoFactorQrCode }}"
                                alt="Authenticator setup QR code"
                                class="h-48 w-48 self-center"
                            />
                        @endif
                        @if ($twoFactorSecret !== '')
                            <native:text class="text-theme-on-surface-variant text-xs">Setup key: {{ $twoFactorSecret }}</native:text>
                        @endif
                        <x-auth-field
                            label="Current password"
                            model="twoFactorPassword"
                            :password="true"
                            :show-password="$showPassword"
                        />
                        <native:outlined-text-input
                            label="Six-digit authenticator code"
                            keyboard="numeric"
                            native:model="twoFactorCode"
                        />
                        <native:button variant="primary" :disabled="$busy" @tap="confirmTwoFactor"
                            >Confirm setup</native:button>
                    @else
                        <native:text class="text-theme-on-surface-variant text-sm">Use an authenticator app to add another layer of sign-in protection.</native:text>
                        <x-auth-field
                            label="Current password"
                            model="twoFactorPassword"
                            :password="true"
                            :show-password="$showPassword"
                        />
                        <native:button variant="primary" :disabled="$busy" @tap="startTwoFactor"
                            >Set up two-factor authentication</native:button>
                    @endif
                </native:column>

                <native:column class="bg-theme-surface border-theme-outline-variant w-full gap-4 rounded-md border p-4">
                    <native:text class="text-theme-on-surface text-lg font-semibold">GitHub connection</native:text>
                    @if ($githubConnected)
                        <native:text class="text-theme-on-surface-variant text-sm">Connected as {{ '@'.$githubUsername }}</native:text>
                        <native:text class="text-theme-on-surface-variant text-sm">{{ $profileVerified ? 'Your profile is verified.' : 'A connected GitHub account can be used to request profile verification.' }}</native:text>
                        @if ($confirmGithubDisconnect)
                            <native:text class="text-theme-on-surface-variant text-sm">Disconnect {{ '@'.$githubUsername }} from Pinkary?</native:text>
                            <native:row class="w-full flex-wrap items-center gap-2">
                                <native:button
                                    variant="destructive"
                                    :disabled="$githubBusy"
                                    @tap="disconnectGithub"
                                >{{ $githubBusy ? 'Disconnecting…' : 'Confirm disconnect' }}</native:button>
                                <native:pressable
                                    class="px-3 py-2"
                                    @tap="cancelDisconnectGithub"
                                    a11y-label="Cancel GitHub disconnect"
                                >
                                    <native:text class="text-theme-primary text-sm font-medium">Cancel</native:text>
                                </native:pressable>
                            </native:row>
                        @else
                            <native:button variant="secondary" :disabled="$githubBusy" @tap="askDisconnectGithub"
                                >Disconnect GitHub</native:button>
                        @endif
                    @elseif ($githubHandoffId !== '')
                        <native:button variant="primary" :disabled="$githubBusy" @tap="pollGithubHandoff">
                            {{ $githubBusy ? 'Checking…' : 'Check connection status' }}
                        </native:button>
                    @elseif ($githubEligible)
                        <native:text class="text-theme-on-surface-variant text-sm"
                            >Connect your GitHub account using a short-lived, account-bound browser
                            authorization.</native:text>
                        <native:button variant="primary" :disabled="$githubBusy" @tap="connectGithub">
                            {{ $githubBusy ? 'Opening GitHub…' : 'Connect GitHub' }}
                        </native:button>
                    @else
                        <native:text class="text-theme-on-surface-variant text-sm">{{ $githubMessage !== '' ? $githubMessage : 'GitHub connection is unavailable right now.' }}</native:text>
                    @endif
                    @if ($githubMessage !== '' && ($githubConnected || $githubEligible || $githubHandoffId !== ''))
                        <native:text class="text-theme-on-surface-variant text-xs">{{ $githubMessage }}</native:text>
                    @endif
                    <native:pressable
                        class="self-start py-1"
                        @tap="refreshGithubStatus"
                        a11y-label="Refresh GitHub connection status"
                    >
                        <native:text class="text-theme-primary text-sm font-medium">Refresh GitHub status</native:text>
                    </native:pressable>
                </native:column>

                <native:column class="bg-theme-surface border-theme-destructive w-full gap-4 rounded-md border p-4">
                    <native:text class="text-theme-destructive text-lg font-semibold">Delete account</native:text>
                    <native:text class="text-theme-on-surface-variant text-sm"
                        >This permanently deletes your Pinkary account. Your password is required, and the action cannot
                        be undone.</native:text>
                    <x-auth-field
                        label="Current password"
                        model="deletePassword"
                        :password="true"
                        :show-password="$showPassword"
                    />
                    <native:outlined-text-input label="Type DELETE to confirm" native:model="deleteConfirmation" />
                    <native:button variant="destructive" :disabled="$busy" @tap="deleteAccount">
                        {{ $busy ? 'Deleting…' : 'Delete account permanently' }}
                    </native:button>
                </native:column>
            @endif
        </native:column>
    </native:column>
</native:scroll-view>
