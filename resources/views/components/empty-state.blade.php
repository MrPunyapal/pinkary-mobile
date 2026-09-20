@props(['title', 'subtitle' => ''])

<native:column class="w-full p-4">
    <native:column class="w-full items-center gap-2 px-6 py-10 rounded-md bg-theme-surface border border-dashed border-theme-outline">
        <native:text class="text-lg font-medium text-theme-on-surface">{{ $title }}</native:text>
        @if ($subtitle !== '')
            <native:text class="text-sm text-center text-theme-on-surface-variant">{{ $subtitle }}</native:text>
        @endif
        {{ $slot }}
    </native:column>
</native:column>
