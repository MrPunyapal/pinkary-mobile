@props([
    'user' => [],
    'size' => 10,
    'anonymous' => false,
    'companyRadius' => '2xl',
    'link' => true,
])

@php
    $box = match ((int) $size) {
        7 => 'w-7 h-7',
        8 => 'w-8 h-8',
        20 => 'w-20 h-20',
        default => 'w-10 h-10',
    };
    $initialSize = match ((int) $size) {
        7 => 'text-xs',
        20 => 'text-2xl',
        default => 'text-sm',
    };
    $company = (bool) data_get($user, 'company_verified')
        || (bool) data_get($user, 'verification.company');
    $radius = $anonymous
        ? 'rounded-full'
        : ($company ? 'rounded-'.$companyRadius : 'rounded-full');
    $username = (string) data_get($user, 'username', '');
    $name = (string) data_get($user, 'name', '?');
    $avatar = data_get($user, 'avatar');
    $initial = strtoupper(substr($name !== '' ? $name : '?', 0, 1));
    $openProfile = $link && ! $anonymous && $username !== '';
    $border = (int) $size === 20
        ? 'border-4 border-white'
        : 'border border-theme-outline';
@endphp

@if ($anonymous)
    <native:column class="{{ $box }} items-center justify-center rounded-full border border-dashed border-theme-outline">
        <native:text class="{{ $initialSize }} text-theme-on-surface-variant">?</native:text>
    </native:column>
@elseif ($openProfile)
    <native:pressable @tap="openProfile('{{ $username }}')" a11y-label="Open {{ '@'.$username }}’s profile">
        @if (is_string($avatar) && $avatar !== '')
            <x-lazy-image src="{{ $avatar }}" alt="{{ $username }}" class="{{ $box }} object-cover {{ $radius }} {{ $border }}" />
        @else
            <native:column class="{{ $box }} items-center justify-center {{ $radius }} bg-theme-avatar {{ $border }}">
                <native:text class="{{ $initialSize }} text-theme-on-avatar">{{ $initial }}</native:text>
            </native:column>
        @endif
    </native:pressable>
@elseif (is_string($avatar) && $avatar !== '')
    <x-lazy-image src="{{ $avatar }}" alt="{{ $username !== '' ? $username : $name }}" class="{{ $box }} object-cover {{ $radius }} {{ $border }}" />
@else
    <native:column class="{{ $box }} items-center justify-center {{ $radius }} bg-theme-avatar {{ $border }}">
        <native:text class="{{ $initialSize }} text-theme-on-avatar">{{ $initial }}</native:text>
    </native:column>
@endif
