@props([
    'postId',
    'expanded' => false,
])

<native:row class="w-full justify-end">
    <native:pressable class="py-0.5" @tap="toggleShowMore('{{ $postId }}')" a11y-label="{{ $expanded ? 'Show less' : 'Show more' }}">
        <native:text class="text-sm font-medium text-theme-primary">{{ $expanded ? 'Show less' : 'Show more' }}</native:text>
    </native:pressable>
</native:row>
