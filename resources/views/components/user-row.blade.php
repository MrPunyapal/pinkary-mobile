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

    // No follow pill here yet: this row is rendered by the search screen
    // and the post likers sheet, and neither host component exposes
    // toggleFollow(username) — a pill would be a dead control.
    // native/follow-list.blade.php (which does own that method) is the
    // pattern to copy once a host gains one.
@endphp

<native:pressable @tap="openProfile('{{ $username }}')" a11y-label="Open {{ '@'.$username }}’s profile">
    <native:row class="w-full items-center gap-3 px-4 py-4 bg-theme-background border-b border-theme-outline-variant">
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
