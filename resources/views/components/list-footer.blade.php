@props(['loading' => false, 'error' => '', 'tap' => 'loadMore', 'endText' => null, 'hasMore' => false])

@if ($loading)
    <native:column class="text-theme-on-surface-variant w-full items-center px-5 py-5">
        <native:activity-indicator />
    </native:column>
@elseif ($error !== '')
    <native:column class="w-full items-center gap-2 px-5 py-5">
        <native:text class="text-theme-destructive text-center text-sm">{{ $error }}</native:text>
        <native:pressable class="py-1" @tap="{{ $tap }}" a11y-label="Try again">
            <native:text class="text-theme-primary text-sm font-medium">Try again</native:text>
        </native:pressable>
    </native:column>
@elseif ($endText !== null && ! $hasMore)
    <native:column class="w-full items-center px-5 py-5">
        <native:text class="text-theme-on-surface-variant text-center text-base">{{ $endText }}</native:text>
    </native:column>
@endif
