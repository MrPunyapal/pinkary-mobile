@props(['postId'])

<native:row class="w-full justify-start">
    <native:pressable class="py-0.5" @tap="showMore('{{ $postId }}')" a11y-label="View more">
        <native:text class="text-theme-primary text-sm font-medium">View more</native:text>
    </native:pressable>
</native:row>
