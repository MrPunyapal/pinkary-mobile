{{-- Web parity (livewire/questions/placeholder.blade.php): h-2 rounded bars
    for the header (w-20 + w-16) and the body (w-10/12, full, w-32), then a
    bottom action row — three h-4 w-4 dots on the left, a w-16 h-2 bar and a
    dot on the right. --}}
<native:column class="w-full gap-2.5 px-4 py-4 bg-theme-background border-b border-theme-outline-variant">
    <native:row class="w-full items-center gap-3">
        <native:column class="w-10 h-10 rounded-full bg-theme-surface-variant shrink-0" />
        <native:column class="flex-1 gap-2 min-w-0">
            <native:column class="w-20 h-2 rounded bg-theme-surface-variant" />
            <native:column class="w-16 h-2 rounded bg-theme-surface-variant" />
        </native:column>
    </native:row>
    <native:column class="w-full gap-2.5">
        <native:column class="w-5/6 h-2 rounded bg-theme-outline-variant" />
        <native:column class="w-full h-2 rounded bg-theme-outline-variant" />
        <native:column class="w-32 h-2 rounded bg-theme-outline-variant" />
    </native:column>
    <native:row class="w-full items-center justify-between">
        <native:row class="items-center gap-2">
            <native:column class="w-4 h-4 rounded-full bg-theme-outline-variant" />
            <native:column class="w-4 h-4 rounded-full bg-theme-outline-variant" />
            <native:column class="w-4 h-4 rounded-full bg-theme-outline-variant" />
        </native:row>
        <native:row class="items-center gap-2">
            <native:column class="w-16 h-2 rounded bg-theme-outline-variant" />
            <native:column class="w-4 h-4 rounded-full bg-theme-outline-variant" />
        </native:row>
    </native:row>
</native:column>
