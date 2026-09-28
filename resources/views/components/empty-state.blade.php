@props(['title', 'subtitle' => '', 'padding' => 'py-5'])

{{-- Web parity: the empty feed is PLAIN centred copy — no card, no border,
    no background. livewire/feed.blade.php:25 sits at py-5 while the
    following/trending tabs sit at py-8, so `padding` is caller-controlled.

    No slot: the web's empty states are copy only, and the two screens that
    used to pass a "Back to Feed" pressable no longer do — the Feed tab is
    the way back. --}}
<native:column class="w-full items-center gap-2 px-4 {{ $padding }}">
    <native:text class="text-lg font-medium text-center text-theme-on-surface">{{ $title }}</native:text>
    @if ($subtitle !== '')
        <native:text class="text-sm text-center text-theme-on-surface-variant">{{ $subtitle }}</native:text>
    @endif
</native:column>
