@props(['post', 'expandedIds' => [], 'viewerUsername' => null])

@php
    $threadPosts = array_values(array_filter(
        (array) data_get($post, 'thread.posts', []),
        fn (mixed $ancestor): bool => is_array($ancestor) && ($ancestor['id'] ?? null) !== null
    ));
    $threadMore = (bool) data_get($post, 'thread.more', false);
    $threadMoreId = (string) data_get($post, 'thread.more_id', '');
    $linkAfterFirst = $threadMore && $threadMoreId !== ''
        && ($threadPosts[0]['id'] ?? null) === data_get($post, 'thread.root_id');

    $expandedList = array_map('strval', (array) $expandedIds);
    $isExpanded = fn (mixed $id): bool => in_array((string) $id, $expandedList, true);
@endphp

@if (count($threadPosts) === 0)
    <x-post :post="$post" :viewer-username="$viewerUsername" :expanded="$isExpanded(data_get($post, 'id'))" />
@else
    <native:column native:key="thread-{{ data_get($post, 'id') }}" class="w-full gap-0 bg-theme-surface border-b border-theme-outline-variant">
        @foreach ($threadPosts as $index => $ancestor)
            <x-post
                :post="$ancestor"
                :viewer-username="$viewerUsername"
                :bare="true"
                :rail="true"
                :rail-top="$index > 0"
                :flush-top="$index > 0"
                :more-link="$linkAfterFirst && $index === 0 ? $threadMoreId : null"
                :expanded="$isExpanded(data_get($ancestor, 'id'))" />
        @endforeach
        <x-post :post="$post" :viewer-username="$viewerUsername" :bare="true" :rail="false" :rail-top="true" :flush-top="true" :expanded="$isExpanded(data_get($post, 'id'))" />
    </native:column>
@endif
