@props(['user'])

@php
    $verified = (bool) data_get($user, 'verification.profile', false);
    $company = (bool) data_get($user, 'verification.company', false);
    $sealColor = $company ? '#EAB308' : '#EC4899';
    $username = (string) data_get($user, 'username', '');

    $isMe = (bool) data_get($user, 'is_me', false);
    $following = (bool) data_get($user, 'followed_by_me', false);
    $followsMe = (bool) data_get($user, 'follows_me', false);
    $followBusy = (bool) data_get($user, '_busy', false);
    $followLabel = $following ? 'Unfollow' : ($followsMe ? 'Follow Back' : 'Follow');
@endphp

<native:row class="bg-theme-background border-theme-outline-variant w-full items-center border-b">
    <native:pressable
        class="min-w-0 flex-1 py-4 pl-4"
        @tap="openProfile('{{ $username }}')"
        a11y-label="Open {{ '@'.$username }}’s profile"
    >
        <native:row class="w-full items-center gap-3">
            <x-avatar :user="$user" company-radius="md" :link="false" />
            <native:column class="min-w-0 flex-1 gap-0.5">
                <native:row class="items-center gap-1.5">
                    <native:text
                        class="text-theme-on-surface text-sm font-medium"
                        max-lines="1"
                    >{{ data_get($user, 'name') }}</native:text>
                    @if ($verified)
                        <native:icon
                            :ios="\App\Icons\Ios::CheckmarkSealFill"
                            :android="\App\Icons\Android::Verified"
                            :size="14"
                            :color="$sealColor"
                        />
                    @endif
                </native:row>
                <native:text
                    class="text-theme-on-surface-variant text-sm"
                    max-lines="1"
                >{{ '@'.$username }}</native:text>
            </native:column>
        </native:row>
    </native:pressable>

    @unless ($isMe || $username === '')
        <native:pressable
            class="shrink-0 mr-4 px-3 py-1.5 rounded-full {{ $following ? 'border border-theme-outline-variant' : 'bg-theme-primary' }} {{ $followBusy ? 'opacity-40' : '' }}"
            @tap="toggleFollow('{{ $username }}')"
            a11y-label="{{ $followLabel }}"
        >
            <native:text class="text-xs font-medium {{ $following ? 'text-theme-on-surface-variant' : 'text-white' }}">{{ $followLabel }}</native:text>
        </native:pressable>
    @endunless
</native:row>
