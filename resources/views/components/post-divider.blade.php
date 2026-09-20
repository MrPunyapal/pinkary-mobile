@props(['link' => null])

{{-- The "View more comments" link renders inside the post's content
    column (indented past the avatar lane, like the web). It renders
    nothing when there is no link. --}}
@if ($link !== null && $link !== '')
    <native:pressable class="w-full py-2" @tap="openDetail('{{ $link }}')" a11y-label="View more comments">
        <native:text class="text-sm font-medium text-theme-primary">View more comments</native:text>
    </native:pressable>
@endif
