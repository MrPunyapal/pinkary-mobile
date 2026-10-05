@props(['link' => null])

{{-- Dashed borders are unsupported; draw the segmented rail with native views. --}}

@if ($link !== null && $link !== '')
    <native:row class="w-full items-center gap-3 px-2 py-0">
        <native:column class="w-10 shrink-0 items-center">
            <native:rect class="bg-theme-outline -mt-1 h-2 w-0.5 shrink-0" />
            <native:rect class="bg-theme-outline my-1 h-1.5 w-0.5 shrink-0" />
            <native:rect class="bg-theme-outline my-1 h-1.5 w-0.5 shrink-0" />
            <native:rect class="bg-theme-outline my-1 h-1.5 w-0.5 shrink-0" />
            <native:rect class="bg-theme-outline -mb-1 h-2 w-0.5 shrink-0" />
        </native:column>
        <native:pressable
            class="flex-1 justify-center py-1"
            @tap="openDetail('{{ $link }}')"
            a11y-label="View more comments"
        >
            <native:text class="text-theme-primary text-sm font-medium">View more comments</native:text>
        </native:pressable>
    </native:row>
@else
    <native:row class="w-full items-center gap-3 px-2 py-0">
        <native:column class="w-10 shrink-0 items-center">
            <native:rect class="bg-theme-outline -my-1 h-8 w-0.5 shrink-0" />
        </native:column>
    </native:row>
@endif
