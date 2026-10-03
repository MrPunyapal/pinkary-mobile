@props([
    'user' => [],
    'size' => 10,
    'anonymous' => false,
    'companyRadius' => '2xl',
    'link' => true,
    'border' => true,
])

@php
    $box = match ((int) $size) {
        7 => 'w-7 h-7',
        8 => 'w-8 h-8',
        20 => 'w-20 h-20',
        24 => 'w-24 h-24',
        default => 'w-10 h-10',
    };
    $initialSize = match ((int) $size) {
        7 => 'text-xs',
        20, 24 => 'text-2xl',
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
    $ring = match (true) {
        ! $border => '',
        in_array((int) $size, [20, 24], true) => 'border-4 border-white',
        default => 'border border-theme-outline-variant',
    };
@endphp

@if ($anonymous)
    {{-- The native renderer does not support dashed borders. --}}
    <native:column class="{{ $box }} items-center justify-center rounded-full {{ $ring !== '' ? $ring : 'border border-theme-outline-variant' }}">
        <native:text class="{{ $initialSize }} text-theme-on-surface-variant">?</native:text>
    </native:column>
@elseif ($openProfile)
    <native:pressable @tap="openProfile('{{ $username }}')" a11y-label="Open {{ '@'.$username }}’s profile">
        @if (is_string($avatar) && $avatar !== '')
            <x-lazy-image src="{{ $avatar }}" alt="{{ $username }}" class="{{ $box }} object-cover {{ $radius }} {{ $ring }}" />
        @else
            <native:column class="{{ $box }} items-center justify-center {{ $radius }} bg-theme-avatar {{ $ring }}">
                <native:text class="{{ $initialSize }} text-theme-on-avatar">{{ $initial }}</native:text>
            </native:column>
        @endif
    </native:pressable>
@elseif (is_string($avatar) && $avatar !== '')
    <x-lazy-image src="{{ $avatar }}" alt="{{ $username !== '' ? $username : $name }}" class="{{ $box }} object-cover {{ $radius }} {{ $ring }}" />
@else
    <native:column class="{{ $box }} items-center justify-center {{ $radius }} bg-theme-avatar {{ $ring }}">
        <native:text class="{{ $initialSize }} text-theme-on-avatar">{{ $initial }}</native:text>
    </native:column>
@endif
