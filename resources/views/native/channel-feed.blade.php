@php $viewerUsername = (string) data_get(\App\Support\AccountStore::active(), 'username', ''); @endphp
<native:list
    native:key="channel-{{ $channelSlug }}-feed"
    on-end-reached="loadMore"
    :end-reached-buffer="1"
    class="bg-theme-background h-full w-full"
>
    <x-page-heading :title="$channelName" :back="true" />

    @if ($loading)
        <x-skeleton-feed />
    @elseif ($errorMessage !== '')
        <native:column class="bg-theme-destructive/15 border-theme-destructive mx-4 my-5 gap-3 rounded-md border p-4">
            <native:text class="text-theme-destructive text-sm">{{ $errorMessage }}</native:text>
            <native:pressable class="self-start py-1" @tap="retry" a11y-label="Try again">
                <native:text class="text-theme-destructive text-sm font-semibold">Try again</native:text>
            </native:pressable>
        </native:column>
    @elseif (count($posts) === 0)
        <x-empty-state
            title="No posts in this channel yet."
            :subtitle="'There are no public posts in '.$channelName.' yet.'"
        />
    @else
        @foreach ($posts as $post)
            <x-thread-post
                :post="$post"
                :feed-item-id="$this->feedItemIdentity($post)"
                :expanded-ids="$expanded"
                :viewer-username="$viewerUsername"
                :active-youtube-post-id="$activeYoutubePostId"
                :youtube-embed-url="$youtubeEmbedUrl"
            />
        @endforeach

        <x-list-footer
            :loading="$loadingMore"
            :error="$loadMoreError"
            tap="loadMore"
            :has-more="$hasMore"
            end-text="You're all caught up."
        />
    @endif
</native:list>

@php
    $menuPost = $menuPostId !== '' ? $this->menuPostData() : null;
    $menuPinned = (bool) data_get($menuPost, 'pinned', false);
    $canPinMenu = $menuPostId !== '' && $this->canPinMenuPost();
    $canEditMenu = $menuPostId !== '' && $this->canEditMenuPost();
    $canAnswerMenu = $menuPostId !== '' && $this->canAnswerMenuPost();
    $canDeleteMenu = $menuPostId !== '' && $this->canDeleteMenuPost();
    $canViewMenuLikes = $menuPostId !== '' && $this->canViewMenuLikes();
@endphp
@include('native.post-menu-sheet')

@include('native.app-menu-sheet')
