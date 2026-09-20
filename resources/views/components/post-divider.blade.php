@props(['link' => null])

{{-- Thread separation lives in the thread-post card's own borders, so a
    plain divider renders nothing. Only the "View more comments" link is
    an element — plain pressable/text with no avatar lane or bridge
    segment that must stitch across posts on old Jump apps. --}}
@if ($link !== null && $link !== '')
    <native:pressable class="w-full py-2" @tap="openDetail('{{ $link }}')" a11y-label="View more comments">
        <native:text class="text-sm font-medium text-theme-primary">View more comments</native:text>
    </native:pressable>
@endif
