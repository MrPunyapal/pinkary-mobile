@props(['tap' => 'loadMore', 'label' => 'Load more', 'loading' => false, 'error' => ''])

<native:column class="w-full items-center gap-2 px-5 py-5">
    @if ($error !== '')
        <native:text class="text-theme-destructive text-center text-sm">{{ $error }}</native:text>
    @endif
    <native:pressable class="py-1" @tap="{{ $tap }}" a11y-label="{{ $loading ? 'Loading' : $label }}">
        @if ($loading)
            <native:row class="items-center gap-2">
                <native:activity-indicator />
                <native:text class="text-theme-primary text-sm font-medium">Loading…</native:text>
            </native:row>
        @else
            <native:text class="text-theme-primary text-sm font-medium">{{ $label }}</native:text>
        @endif
    </native:pressable>
</native:column>
