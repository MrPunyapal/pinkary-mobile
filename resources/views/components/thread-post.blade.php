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
    <x-post :post="$post" :in-thread="false" :expanded="$isExpanded(data_get($post, 'id'))" />
@else
    {{-- One connected card like the web's x-thread: the frame and the
        single bottom border live here, chained posts render bare and the
        rail bridges stitch their avatars into one continuous line. --}}
    <native:column class="w-full gap-0 px-4 py-3 bg-theme-background border-b border-theme-outline-variant">
        @foreach ($threadPosts as $index => $ancestor)
            <x-post :post="$ancestor" :in-thread="true" :bare="true" :expanded="$isExpanded(data_get($ancestor, 'id'))" />
            @if ($linkAfterFirst && $index === 0)
                <x-post-divider :link="$threadMoreId" />
            @else
                <x-post-divider />
            @endif
        @endforeach
        <x-post :post="$post" :in-thread="false" :bare="true" :expanded="$isExpanded(data_get($post, 'id'))" />
    </native:column>
@endif
