@if ($appMenuOpen)
    @php
        $menuUser = \App\Support\CurrentUser::get();
        $isLoggedIn = $menuUser !== null;
        $menuAccounts = \App\Support\AccountStore::list();
        $menuActiveId = \App\Support\AccountStore::activeId();
        $menuHasMultipleAccounts = count($menuAccounts) > 1;
        $menuUsernameFor = function (string $id) use ($menuAccounts): string {
            foreach ($menuAccounts as $menuAccountItem) {
                if (($menuAccountItem['id'] ?? null) === $id) {
                    return (string) ($menuAccountItem['username'] ?? '');
                }
            }

            return '';
        };
        $menuActiveUsername = $menuUsernameFor((string) $menuActiveId);
        if ($menuActiveUsername === '' && is_array($menuUser)) {
            $menuActiveUsername = (string) ($menuUser['username'] ?? '');
        }

        // Web parity (../pinkary/resources/views/layouts/navigation.blade.php:357
        // and :383): several accounts get "Log out of @username", a single
        // account gets a bare "Log Out".
        $menuLogoutLabel = $menuHasMultipleAccounts && $menuActiveUsername !== ''
            ? 'Log out of @'.$menuActiveUsername
            : 'Log Out';

        $menuConfirmAction = $confirmAction ?? '';
        $menuConfirmHandle = $menuUsernameFor($confirmAccountId ?? '');
        $menuConfirmHeading = $menuConfirmAction === 'logout-all'
            ? 'Log out of all accounts?'
            : 'Log out of account?';
        $menuConfirmBody = $menuConfirmAction === 'logout-all'
            ? 'Are you sure you want to log out of all accounts? You can log back into them at any time.'
            : 'Are you sure you want to log out of '.($menuConfirmHandle !== '' ? '@'.$menuConfirmHandle : 'this account').'? You can log back into this account at any time.';
    @endphp
    <native:bottom-sheet visible @dismiss="cancelConfirm">
        {{-- The hairline mirrors the web sheet edge (border-slate-200/80). --}}
        <native:column class="w-full gap-1 px-5 py-4 bg-theme-surface border-t border-theme-outline-variant">
            @if ($menuConfirmAction !== '')
                {{-- Web parity: destructive rows need the second tap
                    (../pinkary/resources/views/layouts/app.blade.php:261-293). --}}
                <native:column class="w-full gap-2 py-2">
                    <native:text class="text-base font-semibold text-theme-on-surface">{{ $menuConfirmHeading }}</native:text>
                    <native:text class="text-sm text-theme-on-surface-variant">{{ $menuConfirmBody }}</native:text>
                    <native:row class="w-full items-center gap-3 pt-2">
                        <native:button variant="secondary" @tap="cancelConfirm">Cancel</native:button>
                        <native:button variant="destructive" @tap="confirm">Log Out</native:button>
                    </native:row>
                </native:column>
            @elseif ($isLoggedIn)
                {{-- Web order (../pinkary/resources/views/layouts/navigation.blade.php:331-346):
                    About, Source code, Settings, then the account switcher. --}}
                <native:pressable class="w-full py-3" @tap="openAbout" a11y-label="About">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::InfoCircle" :android="\App\Icons\AndroidOutlined::Info" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-sm font-medium text-theme-on-surface">About</native:text>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="openSource" a11y-label="Source code">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::ChevronLeftForwardslashChevronRight" :android="\App\Icons\AndroidOutlined::Code" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-sm font-medium text-theme-on-surface">Source code</native:text>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="openSettings" a11y-label="Settings">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::Gearshape" :android="\App\Icons\AndroidOutlined::Settings" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-sm font-medium text-theme-on-surface">Settings</native:text>
                    </native:row>
                </native:pressable>

                {{-- Web order (../pinkary/resources/views/layouts/navigation.blade.php:344):
                    settings, then the account switcher, then the log-outs
                    behind a divider. --}}
                <native:column class="w-full gap-1 py-2">
                    <native:text class="px-3 py-1 text-xs font-semibold tracking-wider uppercase text-theme-on-surface-variant">Accounts</native:text>

                    @foreach ($menuAccounts as $menuAccount)
                        @php
                            $menuAccountActive = $menuAccount['id'] === $menuActiveId;
                            $menuAccountName = (string) ($menuAccount['name'] ?? '');
                            $menuAccountUsername = (string) ($menuAccount['username'] ?? '');
                            $menuAccountHandle = $menuAccountUsername !== '' ? '@'.$menuAccountUsername : 'this account';
                        @endphp
                        <native:row class="w-full items-center gap-2">
                            <native:pressable class="flex-1 min-w-0 py-2" @tap="{{ $menuAccountActive ? 'openOwnProfile' : "switchAccount('".$menuAccount['id']."')" }}" a11y-label="{{ $menuAccountActive ? 'Open your profile' : 'Switch to '.$menuAccountHandle }}">
                                <native:row class="w-full items-center gap-3">
                                    <x-avatar :user="['name' => $menuAccountName, 'username' => $menuAccountUsername, 'avatar' => $menuAccount['avatar']]" :size="6" :link="false" />
                                    <native:text class="flex-1 min-w-0 text-sm font-medium text-theme-on-surface" max-lines="1">{{ $menuAccountHandle }}</native:text>
                                    @if ($menuAccountActive)
                                        <native:icon :ios="\App\Icons\Ios::Checkmark" :android="\App\Icons\Android::Check" :size="16" color="#EC4899" />
                                    @endif
                                </native:row>
                            </native:pressable>
                            @unless ($menuAccountActive)
                                {{-- Web parity: removing an account is guarded by the
                                    same modal as logging out (switch-account.blade.php:41-56). --}}
                                <native:pressable class="p-2" @tap="askRemoveAccount('{{ $menuAccount['id'] }}')" a11y-label="Log out of {{ $menuAccountHandle }}">
                                    <native:icon :ios="\App\Icons\Ios::Xmark" :android="\App\Icons\Android::Close" :size="16" color="#64748B" dark-color="#94A3B8" />
                                </native:pressable>
                            @endunless
                        </native:row>
                    @endforeach

                    <native:pressable class="w-full py-3" @tap="addAccount" a11y-label="Add an existing account">
                        <native:row class="w-full items-center gap-3">
                            <native:icon :ios="\App\Icons\Ios::Plus" :android="\App\Icons\Android::Add" :size="20" color="#64748B" dark-color="#94A3B8" />
                            <native:text class="text-sm font-medium text-theme-on-surface">Add an existing account</native:text>
                        </native:row>
                    </native:pressable>
                </native:column>

                <native:row class="w-full items-center border-t border-theme-outline-variant py-2">
                    <native:pressable class="flex-1 py-3" @tap="askLogout" a11y-label="{{ $menuLogoutLabel }}">
                        <native:row class="w-full items-center gap-3">
                            <native:icon :ios="\App\Icons\Ios::RectanglePortraitAndArrowRight" :android="\App\Icons\Android::Logout" :size="20" color="#B91C1C" dark-color="#F87171" />
                            <native:text class="text-sm font-medium text-theme-destructive" max-lines="1">{{ $menuLogoutLabel }}</native:text>
                        </native:row>
                    </native:pressable>
                </native:row>

                {{-- Web parity: "log out of all accounts" only exists with
                    several accounts (navigation.blade.php:347-386). --}}
                @if ($menuHasMultipleAccounts)
                    <native:pressable class="w-full py-3" @tap="askLogoutAll" a11y-label="Log out of all accounts">
                        <native:row class="w-full items-center gap-3">
                            <native:icon :ios="\App\Icons\Ios::RectanglePortraitAndArrowRight" :android="\App\Icons\Android::Logout" :size="20" color="#B91C1C" dark-color="#F87171" />
                            <native:text class="text-sm font-medium text-theme-destructive">Log out of all accounts</native:text>
                        </native:row>
                    </native:pressable>
                @endif
            @else
                <native:pressable class="w-full py-3" @tap="openLogin" a11y-label="Log in">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::ArrowRightSquare" :android="\App\Icons\Android::Login" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-sm font-medium text-theme-on-surface">Log in</native:text>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="openRegister" a11y-label="Register">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::PersonBadgePlus" :android="\App\Icons\Android::PersonAdd" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-sm font-medium text-theme-on-surface">Register</native:text>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="openAbout" a11y-label="About">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::InfoCircle" :android="\App\Icons\AndroidOutlined::Info" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-sm font-medium text-theme-on-surface">About</native:text>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="openSource" a11y-label="Source code">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::ChevronLeftForwardslashChevronRight" :android="\App\Icons\AndroidOutlined::Code" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-sm font-medium text-theme-on-surface">Source code</native:text>
                    </native:row>
                </native:pressable>
            @endif
        </native:column>
    </native:bottom-sheet>
@endif
