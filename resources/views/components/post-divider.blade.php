@props(['link' => null])

{{-- Web parity (components/post-divider with link): the "View more
    comments" row is min-h-12 (48px) with a full-height border-l
    border-dotted rail in the avatar lane next to the pink link, then a solid
    stub continuing to the next segment.
    Dotted/dashed borders do not parse in this vendor version, so the
    dashes are short bars at the rail's own 2px weight (w-0.5) and the
    continuation is a solid stub whose -mb-2 reaches into the next
    segment's top pad — one connected line through the divider. Renders
    nothing without a link.

    px-2 matches the per-thread inset the web wraps every post in
    (pinkary/resources/views/livewire/feed.blade.php:6), so this rail
    lands in the same lane as the avatar it continues. --}}

{{-- Web parity (components/post-divider with link): the "View more
    comments" row is min-h-12 (48px) with a full-height border-l
    border-dotted rail in the avatar lane next to the pink link, then a solid
    stub continuing to the next segment.
    Dotted/dashed borders do not parse in this vendor version, so the
    dashes are short bars at the rail's own 2px weight (w-0.5) and the
    continuation is a solid stub whose -mb-2 reaches into the next
    segment's top pad — one connected line through the divider. Renders
    nothing without a link. --}}
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
    {{-- Web parity: the plain rail row is h-8 (32px) with a solid
        w-0.5 bg-slate-300 line spanning it. --}}
    <native:row class="w-full items-center gap-3 px-2 py-0">
        <native:column class="w-10 items-center shrink-0">
            <native:rect class="w-0.5 h-8 bg-theme-outline shrink-0 -my-1" />
        </native:column>
    </native:row>
@endif
