@props(['post', 'expandedIds' => []])

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

    $expandedList = array_map('strval', (array) $expandedIds);
    $isExpanded = fn (mixed $id): bool => in_array((string) $id, $expandedList, true);
@endphp

@if (count($threadPosts) === 0)
    <x-post :post="$post" :expanded="$isExpanded(data_get($post, 'id'))" />
@else
    {{-- Flat web-style thread: chained posts are plain rows whose avatar
        lanes draw a flex-1 rail to the row's bottom edge (responsive,
        no estimated height, no extra space after the post). Segments
        stack with no gaps or boxes; the container owns the single
        full-bleed divider, like the web. --}}
    <native:column class="w-full gap-0 bg-theme-background border-b border-theme-outline-variant">
        @foreach ($threadPosts as $index => $ancestor)
            <x-post
                :post="$ancestor"
                :bare="true"
                :rail="true"
                :flush-top="$index > 0"
                :more-link="$linkAfterFirst && $index === 0 ? $threadMoreId : null"
                :expanded="$isExpanded(data_get($ancestor, 'id'))" />
        @endforeach
        <x-post :post="$post" :bare="true" :rail="false" :flush-top="true" :expanded="$isExpanded(data_get($post, 'id'))" />
    </native:column>
@endif
