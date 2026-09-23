@if ($appMenuOpen)
    @php
        $menuUser = \App\Support\CurrentUser::get();
        $isLoggedIn = $menuUser !== null;
        $menuAccounts = \App\Support\AccountStore::list();
        $menuActiveId = \App\Support\AccountStore::activeId();
        $menuActiveUsername = '';
        foreach ($menuAccounts as $menuAccountItem) {
            if (($menuAccountItem['id'] ?? null) === $menuActiveId) {
                $menuActiveUsername = (string) ($menuAccountItem['username'] ?? '');
                break;
            }
        }
        if ($menuActiveUsername === '' && is_array($menuUser)) {
            $menuActiveUsername = (string) ($menuUser['username'] ?? '');
        }
        $menuLogoutLabel = $menuActiveUsername !== '' ? 'Log out @'.$menuActiveUsername : 'Log out';
    @endphp
    <native:bottom-sheet visible @dismiss="closeAppMenu">
        <native:column class="w-full gap-1 px-5 py-4 bg-theme-surface">
            @if ($isLoggedIn)
                {{-- Multi-login switcher (web parity): every stored account
                    lists here, active checked — tapping it opens your
                    profile, the rest switch on tap. Tokens never reach
                    this template. --}}
                <native:column class="w-full gap-1 py-2">
                    @foreach ($menuAccounts as $menuAccount)
                        @php
                            $menuAccountActive = $menuAccount['id'] === $menuActiveId;
                            $menuAccountName = (string) ($menuAccount['name'] ?? '');
                            $menuAccountUsername = (string) ($menuAccount['username'] ?? '');
                        @endphp
                        <native:row class="w-full items-center gap-2">
                            <native:pressable class="flex-1 min-w-0 py-2" @tap="{{ $menuAccountActive ? 'openOwnProfile' : "switchAccount('".$menuAccount['id']."')" }}" a11y-label="{{ $menuAccountActive ? 'Open your profile' : 'Switch to '.($menuAccountUsername !== '' ? '@'.$menuAccountUsername : 'this account') }}">
                                <native:row class="w-full items-center gap-3">
                                    <x-avatar :user="['name' => $menuAccountName, 'username' => $menuAccountUsername, 'avatar' => $menuAccount['avatar']]" :size="8" :link="false" />
                                    <native:column class="flex-1 min-w-0">
                                        <native:text class="text-sm font-semibold text-theme-on-surface" max-lines="1">{{ $menuAccountName !== '' ? $menuAccountName : ($menuAccountUsername !== '' ? '@'.$menuAccountUsername : 'Pinkary') }}</native:text>
                                        @if ($menuAccountUsername !== '')
                                            <native:text class="text-xs text-theme-on-surface-variant" max-lines="1">{{ '@'.$menuAccountUsername }}</native:text>
                                        @endif
                                    </native:column>
                                    @if ($menuAccountActive)
                                        <native:icon :ios="\App\Icons\Ios::Checkmark" :android="\App\Icons\Android::Check" :size="18" color="#EC4899" />
                                    @endif
                                </native:row>
                            </native:pressable>
                            @unless ($menuAccountActive)
                                <native:pressable class="p-2" @tap="removeAccount('{{ $menuAccount['id'] }}')" a11y-label="Remove {{ $menuAccountUsername !== '' ? '@'.$menuAccountUsername : 'this account' }}">
                                    <native:icon :ios="\App\Icons\Ios::Trash" :android="\App\Icons\AndroidOutlined::Delete" :size="16" color="#64748B" dark-color="#94A3B8" />
                                </native:pressable>
                            @endunless
                        </native:row>
                    @endforeach
                    <native:pressable class="w-full py-3" @tap="addAccount" a11y-label="Add account">
                        <native:row class="w-full items-center gap-3">
                            <native:icon :ios="\App\Icons\Ios::PersonBadgePlus" :android="\App\Icons\Android::PersonAdd" :size="20" color="#64748B" dark-color="#94A3B8" />
                            <native:text class="text-base text-theme-on-surface">Add account</native:text>
                        </native:row>
                    </native:pressable>
                </native:column>

                <native:pressable class="w-full py-3" @tap="openSettings" a11y-label="Settings">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::Gearshape" :android="\App\Icons\AndroidOutlined::Settings" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-base text-theme-on-surface">Settings</native:text>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="openSource" a11y-label="Source code">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::ChevronLeftForwardslashChevronRight" :android="\App\Icons\AndroidOutlined::Code" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-base text-theme-on-surface">Source code</native:text>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="logout" a11y-label="{{ $menuLogoutLabel }}">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::RectanglePortraitAndArrowRight" :android="\App\Icons\Android::Logout" :size="20" color="#B91C1C" />
                        <native:text class="text-base font-medium text-theme-destructive" max-lines="1">{{ $menuLogoutLabel }}</native:text>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="logoutAll" a11y-label="Log out of all accounts">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::RectanglePortraitAndArrowRight" :android="\App\Icons\Android::Logout" :size="20" color="#B91C1C" />
                        <native:text class="text-base font-medium text-theme-destructive">Log out of all accounts</native:text>
                    </native:row>
                </native:pressable>
            @else
                <native:pressable class="w-full py-3" @tap="openLogin" a11y-label="Log in">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::ArrowRightSquare" :android="\App\Icons\Android::Login" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-base text-theme-on-surface">Log in</native:text>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="openRegister" a11y-label="Register">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::PersonBadgePlus" :android="\App\Icons\Android::PersonAdd" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-base text-theme-on-surface">Register</native:text>
                    </native:row>
                </native:pressable>

                <native:pressable class="w-full py-3" @tap="openSource" a11y-label="Source code">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::ChevronLeftForwardslashChevronRight" :android="\App\Icons\AndroidOutlined::Code" :size="20" color="#64748B" dark-color="#94A3B8" />
                        <native:text class="text-base text-theme-on-surface">Source code</native:text>
                    </native:row>
                </native:pressable>
            @endif

            <native:pressable class="w-full py-3" @tap="closeAppMenu" a11y-label="Cancel">
                <native:row class="w-full items-center justify-center">
                    <native:text class="text-base font-semibold text-theme-on-surface-variant">Cancel</native:text>
                </native:row>
            </native:pressable>
        </native:column>
    </native:bottom-sheet>
@endif
