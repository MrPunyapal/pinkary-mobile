@props(['link' => null])

{{-- Dashed borders are unsupported; draw the segmented rail with native views. --}}

@if ($link !== null && $link !== '')
    <native:row class="w-full items-center gap-3 px-2 py-0">
        <native:column class="w-10 items-center shrink-0">
            <native:rect class="w-0.5 h-2 bg-theme-outline shrink-0 -mt-1" />
            <native:rect class="w-0.5 h-1.5 bg-theme-outline shrink-0 my-1" />
            <native:rect class="w-0.5 h-1.5 bg-theme-outline shrink-0 my-1" />
            <native:rect class="w-0.5 h-1.5 bg-theme-outline shrink-0 my-1" />
            <native:rect class="w-0.5 h-2 bg-theme-outline shrink-0 -mb-1" />
        </native:column>
        <native:pressable class="flex-1 justify-center py-1" @tap="openDetail('{{ $link }}')" a11y-label="View more comments">
            <native:text class="text-sm font-medium text-theme-primary">View more comments</native:text>
        </native:pressable>
    </native:row>
@else
    <native:row class="w-full items-center gap-3 px-2 py-0">
        <native:column class="w-10 items-center shrink-0">
            <native:rect class="w-0.5 h-8 bg-theme-outline shrink-0 -my-1" />
        </native:column>
    </native:row>
@endif
