@props(['user'])

{{-- UserResource ships the badge under `verification` (profile / company)
     — never `verified` / `company_verified`, which is why the old keys never
     matched. Web parity (livewire/links/index:143-147): the seal means
     "profile verified"; the company seal is the amber variant and only
     appears alongside it. --}}
@php
    $verified = (bool) data_get($user, 'verification.profile', false);
    $company = (bool) data_get($user, 'verification.company', false);
    $sealColor = $company ? '#EAB308' : '#EC4899';
    $username = (string) data_get($user, 'username', '');

    // Web parity (livewire/home/users.blade.php:71-77 and
    // livewire/likes/index.blade.php:61-67): this row carries the web's
    // follow button, and components/follow-button.blade.php:7 drops it on
    // your own row. Both hosts — Search results and the post likers sheet
    // (through TogglesPostReactions) — own toggleFollow($username), so the
    // pill is live on either.
    //
    // The state is `followed_by_me` (App\Http\Resources\UserResource:37);
    // the `followed` key the search endpoint sends is a different field
    // (the follow response's own flag) and never renders a row state.
    // `_busy` is written by that method: the label stays put while the
    // request is in flight, so the row never reflows.
    $isMe = (bool) data_get($user, 'is_me', false);
    $following = (bool) data_get($user, 'followed_by_me', false);
    $followBusy = (bool) data_get($user, '_busy', false);
@endphp

<native:row class="w-full items-center bg-theme-background border-b border-theme-outline-variant">
    <native:pressable class="flex-1 min-w-0 py-4 pl-4" @tap="openProfile('{{ $username }}')" a11y-label="Open {{ '@'.$username }}’s profile">
        <native:row class="w-full items-center gap-3">
            <x-avatar :user="$user" company-radius="md" :link="false" />
            <native:column class="flex-1 gap-0.5 min-w-0">
                <native:row class="items-center gap-1.5">
                    <native:text class="text-sm font-medium text-theme-on-surface" max-lines="1">{{ data_get($user, 'name') }}</native:text>
                    @if ($verified)
                        <native:icon :ios="\App\Icons\Ios::CheckmarkSealFill" :android="\App\Icons\Android::Verified" :size="14" :color="$sealColor" />
                    @endif
                </native:row>
                <native:text class="text-sm text-theme-on-surface-variant" max-lines="1">{{ '@'.$username }}</native:text>
            </native:column>
        </native:row>
    </native:pressable>

    @unless ($isMe || $username === '')
        <native:pressable class="shrink-0 mr-4 px-3 py-1.5 rounded-full {{ $following ? 'border border-theme-outline-variant' : 'bg-theme-primary' }} {{ $followBusy ? 'opacity-40' : '' }}" @tap="toggleFollow('{{ $username }}')" a11y-label="{{ $following ? 'Unfollow' : 'Follow' }}">
            <native:text class="text-xs font-medium {{ $following ? 'text-theme-on-surface-variant' : 'text-white' }}">{{ $following ? 'Unfollow' : 'Follow' }}</native:text>
        </native:pressable>
    @endunless
</native:row>
