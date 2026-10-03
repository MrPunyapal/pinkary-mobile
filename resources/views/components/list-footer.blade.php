@props(['loading' => false, 'error' => '', 'tap' => 'loadMore', 'endText' => null, 'hasMore' => false])

@if ($loading)
    <native:column class="w-full items-center px-5 py-5 text-theme-on-surface-variant">
        <native:activity-indicator />
    </native:column>
@elseif ($error !== '')
    <native:column class="w-full items-center gap-2 px-5 py-5">
        <native:text class="text-sm text-center text-theme-destructive">{{ $error }}</native:text>
        <native:pressable class="py-1" @tap="{{ $tap }}" a11y-label="Try again">
            <native:text class="text-sm font-medium text-theme-primary">Try again</native:text>
        </native:pressable>
    </native:column>
@elseif ($endText !== null && ! $hasMore)
    <native:column class="w-full items-center px-5 py-5">
        <native:text class="text-base text-center text-theme-on-surface-variant">{{ $endText }}</native:text>
    </native:column>
@endif
