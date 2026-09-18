@props(['link' => null])

@if ($link !== null && $link !== '')
    <native:row class="w-full items-stretch gap-3 px-5 bg-theme-surface">
        <native:column class="items-center shrink-0 w-10">
            <native:column class="flex-1 border-l border-dotted border-theme-outline" />
        </native:column>
        <native:pressable class="py-3" @tap="openDetail('{{ $link }}')" a11y-label="View more comments">
            <native:text class="text-sm font-medium text-theme-primary">View more comments</native:text>
        </native:pressable>
    </native:row>
@else
    <native:row class="w-full items-stretch gap-3 px-5 h-8 shrink-0 bg-theme-surface">
        <native:column class="items-center shrink-0 w-10">
            <native:column class="w-0.5 flex-1 bg-theme-outline-variant" />
        </native:column>
    </native:row>
@endif
