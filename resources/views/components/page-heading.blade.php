@props(['title'])

<native:row class="w-full items-center gap-3 px-4 py-4 bg-theme-surface border-b border-theme-outline-variant">
    <native:text class="flex-1 min-w-0 text-2xl font-semibold text-theme-on-surface">{{ $title }}</native:text>
    {{ $slot }}
</native:row>
