@props(['post'])

@php
    $threadPosts = array_values(array_filter(
        (array) data_get($post, 'thread.posts', []),
        fn (mixed $ancestor): bool => is_array($ancestor) && ($ancestor['id'] ?? null) !== null
    ));
    $threadMore = (bool) data_get($post, 'thread.more', false);
    $threadMoreId = (string) data_get($post, 'thread.more_id', '');
    // The "View more" divider follows the root post, like the web's
    // x-thread — never a lone parent whose root is gone.
    $linkAfterFirst = $threadMore && $threadMoreId !== ''
        && ($threadPosts[0]['id'] ?? null) === data_get($post, 'thread.root_id');
@endphp

<native:column class="w-full gap-0">
    @foreach ($threadPosts as $index => $ancestor)
        <x-post :post="$ancestor" :in-thread="true" />
        @if ($linkAfterFirst && $index === 0)
            <x-post-divider :link="$threadMoreId" />
        @else
            <x-post-divider />
        @endif
    @endforeach
    <x-post :post="$post" :in-thread="false" />
</native:column>
