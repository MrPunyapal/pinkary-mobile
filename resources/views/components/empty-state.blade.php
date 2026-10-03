@props(['title', 'subtitle' => '', 'padding' => 'py-5'])

<native:column class="w-full items-center gap-2 px-4 {{ $padding }}">
    <native:text class="text-lg font-medium text-center text-theme-on-surface">{{ $title }}</native:text>
    @if ($subtitle !== '')
        <native:text class="text-sm text-center text-theme-on-surface-variant">{{ $subtitle }}</native:text>
    @endif
</native:column>
