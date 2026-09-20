@props(['variant' => 'overlay'])

{{-- Overlay: a fill-height line inside a native:stack, sized by the
    post body — no flex-1 inside wrap-content. Bridge/dotted are
    fixed-height segments between posts. --}}
@if ($variant === 'bridge')
    <native:column class="w-10 h-8 items-center shrink-0">
        <native:column class="w-0.5 h-8 bg-theme-outline" />
    </native:column>
@elseif ($variant === 'dotted')
    <native:column class="w-10 h-12 items-center shrink-0">
        <native:column class="w-0.5 h-12 bg-theme-outline" />
    </native:column>
@else
    <native:column class="absolute left-5 top-0 bottom-0 w-0.5 h-full bg-theme-outline" />
@endif
