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

    // Username chaining a continuation to its parent post. Thread posts
    // carry the author on both ends; prefer the answerer, fall back to
    // the asker. Returns '' when the parent names nobody.
    $parentUsername = fn (mixed $parent): string => (string) (data_get($parent, 'to.username') ?? data_get($parent, 'from.username') ?? '');
@endphp

@if (count($threadPosts) === 0)
    <x-post :post="$post" :expanded="$isExpanded(data_get($post, 'id'))" />
@else
    {{-- One connected card like the web's x-thread, but with no drawn
        rails: chained posts are separated by the card's own borders
        (full-width rules that cannot gap) and chained by "Replying to"
        affordances (the super-native tweet-detail pattern). Plain
        column/row/text/pressable only — no absolute overlays, stretch
        lanes, or fixed bridge segments that must stitch across posts
        on old Jump apps. --}}
    <native:column class="w-full gap-0 px-4 py-3 bg-theme-background border-b border-theme-outline-variant">
        @foreach ($threadPosts as $index => $ancestor)
            <native:column class="w-full gap-0 pb-3 mb-3 border-b border-theme-outline-variant">
                <x-post :post="$ancestor" :reply-to="$index === 0 ? null : $parentUsername($threadPosts[$index - 1])" :bare="true" :expanded="$isExpanded(data_get($ancestor, 'id'))" />
                @if ($linkAfterFirst && $index === 0)
                    <x-post-divider :link="$threadMoreId" />
                @endif
            </native:column>
        @endforeach
        <x-post :post="$post" :reply-to="$parentUsername($threadPosts[count($threadPosts) - 1])" :bare="true" :expanded="$isExpanded(data_get($post, 'id'))" />
    </native:column>
@endif
