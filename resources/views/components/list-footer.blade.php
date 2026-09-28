@props(['loading' => false, 'error' => '', 'tap' => 'loadMore', 'endText' => null, 'hasMore' => false])

{{-- Infinite-scroll footer for native:list on-end-reached: spinner while
    loading, error + retry when a page fails, optional end-of-list copy
    when exhausted. Never a Load more button — pagination is automatic. --}}
@if ($loading)
    {{-- Web parity (components/load-more-button.blade.php:2-5): the paging
        state is a bare spinning icon in slate-600/slate-400 — no label. --}}
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
        {{-- Web parity: no size class on the end-of-list copy, so it renders
            at the 16px body size. --}}
        <native:text class="text-base text-center text-theme-on-surface-variant">{{ $endText }}</native:text>
    </native:column>
@endif
