@props(['author'])

<native:row class="w-full items-center gap-3">
    @if (data_get($author, 'avatar'))
        <native:image src="{{ data_get($author, 'avatar') }}" alt="{{ data_get($author, 'username', '') }}" class="w-10 h-10 rounded-full border border-theme-outline" />
    @else
        <native:column class="w-10 h-10 items-center justify-center rounded-full bg-theme-surface-variant border border-theme-outline">
            <native:text class="text-sm text-theme-on-surface-variant">{{ strtoupper(substr((string) data_get($author, 'name', '?'), 0, 1)) }}</native:text>
        </native:column>
    @endif

    <native:column class="flex-1 gap-0.5">
        <native:row class="w-full items-center gap-1.5">
            <native:text class="text-sm font-semibold text-theme-on-surface">{{ data_get($author, 'name') }}</native:text>
            @if (data_get($author, 'verified'))
                <native:text class="text-xs text-theme-primary">✓</native:text>
            @endif
        </native:row>
        <native:text class="text-xs text-theme-on-surface-variant">{{ '@'.data_get($author, 'username') }}</native:text>
    </native:column>
</native:row>
