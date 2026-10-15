@props(['post', 'expandedIds' => [], 'viewerUsername' => null, 'feedItemId' => null, 'full' => false, 'activeYoutubePostId' => '', 'youtubeEmbedUrl' => ''])

@php
    $threadPosts = array_values(array_filter(
        (array) data_get($post, 'thread.posts', []),
        fn (mixed $ancestor): bool => is_array($ancestor) && ($ancestor['id'] ?? null) !== null
    ));
    $threadMore = (bool) data_get($post, 'thread.more', false);
    $threadMoreId = (string) data_get($post, 'thread.more_id', '');
    $feedItemId = $feedItemId ?: (string) data_get($post, 'id', '');
    $linkAfterFirst = $threadMore && $threadMoreId !== ''
        && ($threadPosts[0]['id'] ?? null) === data_get($post, 'thread.root_id');

    $expandedList = array_map('strval', (array) $expandedIds);
    $isExpanded = fn (mixed $id): bool => in_array((string) $id, $expandedList, true);
@endphp

@if (count($threadPosts) === 0)
    <x-post
        :post="$post"
        :feed-item-id="$feedItemId"
        :viewer-username="$viewerUsername"
        :active-youtube-post-id="$activeYoutubePostId"
        :youtube-embed-url="$youtubeEmbedUrl"
        :full="$full"
        :expanded="$isExpanded(data_get($post, 'id'))"
    />
@else
    <native:column
        native:key="thread-{{ $feedItemId }}"
        class="bg-theme-surface border-theme-outline-variant w-full gap-0 border-b"
    >
        @foreach ($threadPosts as $index => $ancestor)
            <x-post
                :post="$ancestor"
                :feed-item-id="$feedItemId"
                :full="$full"
                :viewer-username="$viewerUsername"
                :active-youtube-post-id="$activeYoutubePostId"
                :youtube-embed-url="$youtubeEmbedUrl"
                :bare="true"
                :rail="true"
                :rail-top="$index > 0"
                :flush-top="$index > 0"
                :more-link="$linkAfterFirst && $index === 0 ? $threadMoreId : null"
                :expanded="$isExpanded(data_get($ancestor, 'id'))"
            />
        @endforeach
        <x-post
            :post="$post"
            :feed-item-id="$feedItemId"
            :viewer-username="$viewerUsername"
            :active-youtube-post-id="$activeYoutubePostId"
            :youtube-embed-url="$youtubeEmbedUrl"
            :full="$full"
            :bare="true"
            :rail="false"
            :rail-top="true"
            :flush-top="true"
            :expanded="$isExpanded(data_get($post, 'id'))"
        />
    </native:column>
@endif
