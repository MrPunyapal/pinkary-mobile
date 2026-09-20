@props(['link' => null])

{{-- Fixed-height avatar-lane bridges. Heights are explicit so the
    line does not depend on stretch inside unbounded scroll content. --}}
@if ($link !== null && $link !== '')
    <native:row class="w-full items-center gap-3">
        <x-thread-rail variant="dotted" />
        <native:pressable class="py-3" @tap="openDetail('{{ $link }}')" a11y-label="View more comments">
            <native:text class="text-sm font-medium text-theme-primary">View more comments</native:text>
        </native:pressable>
    </native:row>
@else
    <native:row class="w-full items-start gap-3 h-8 shrink-0">
        <x-thread-rail variant="bridge" />
    </native:row>
@endif
