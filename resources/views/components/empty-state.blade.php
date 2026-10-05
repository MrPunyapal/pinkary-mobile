@props(['title', 'subtitle' => '', 'padding' => 'py-5'])

<native:column class="w-full items-center gap-2 px-4 {{ $padding }}">
    <native:text class="text-theme-on-surface text-center text-lg font-medium">{{ $title }}</native:text>
    @if ($subtitle !== '')
        <native:text class="text-theme-on-surface-variant text-center text-sm">{{ $subtitle }}</native:text>
    @endif
</native:column>
