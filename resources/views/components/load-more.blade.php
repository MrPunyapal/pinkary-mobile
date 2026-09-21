@props(['tap' => 'loadMore', 'label' => 'Load more', 'loading' => false, 'error' => ''])

{{-- Web parity (components/load-more-button): the web auto-loads on
    intersect and only ever shows a spinner — never a button. Native has
    no scroll-end event, so the same moment is a slim tap target with the
    same copy, spinner while loading, no button chrome. --}}
<native:column class="w-full items-center gap-2 px-5 py-5">
    @if ($error !== '')
        <native:text class="text-sm text-center text-theme-destructive">{{ $error }}</native:text>
    @endif
    <native:pressable class="py-1" @tap="{{ $tap }}" a11y-label="{{ $loading ? 'Loading' : $label }}">
        @if ($loading)
            <native:row class="items-center gap-2">
                <native:activity-indicator />
                <native:text class="text-sm font-medium text-theme-primary">Loading…</native:text>
            </native:row>
        @else
            <native:text class="text-sm font-medium text-theme-primary">{{ $label }}</native:text>
        @endif
    </native:pressable>
</native:column>
