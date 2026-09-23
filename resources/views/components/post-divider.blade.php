@props(['link' => null])

{{-- Web parity (components/post-divider with link): the "View more
    comments" row carries a dashed rail in the avatar lane next to the
    pink link, then a solid stub continuing to the next segment.
    Dotted/dashed borders do not parse in this vendor version, so the
    dashes are short bars at the rail's own 2px weight (w-0.5) and the
    continuation is a solid stub whose -mb-2 reaches into the next
    segment's top pad — one connected line through the divider. Renders
    nothing without a link. --}}
@if ($link !== null && $link !== '')
    <native:row class="w-full items-center gap-3 px-4 py-1.5">
        <native:column class="w-10 items-center shrink-0 gap-1">
            @for ($i = 0; $i < 3; $i++)
                <native:column class="w-0.5 h-2 bg-theme-outline shrink-0" />
            @endfor
            <native:column class="w-0.5 h-4 bg-theme-outline shrink-0" />
        </native:column>
        <native:pressable class="flex-1 justify-center" @tap="openDetail('{{ $link }}')" a11y-label="View more comments">
            <native:text class="text-sm font-medium text-theme-primary">View more comments</native:text>
        </native:pressable>
    </native:row>
@endif
