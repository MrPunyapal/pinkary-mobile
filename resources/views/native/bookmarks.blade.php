<native:list on-end-reached="loadMore" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        <x-page-heading title="Bookmarks" size="3xl" />

        @if ($refreshing ?? false)
            <x-refresh-bar />
        @endif

        @if ($loading)
            <x-skeleton-feed />
        @elseif ($errorMessage !== '')
            <native:column class="mx-4 my-5 p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry" a11y-label="Try again">
                    <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                </native:pressable>
            </native:column>
        @elseif (count($posts) === 0)
            <x-empty-state title="No bookmarks yet." subtitle="Posts you save for later will show up here.">
                <native:pressable class="px-4 py-2 rounded-full bg-theme-primary" @tap="goFeed" a11y-label="Back to Feed">
                    <native:text class="text-sm font-semibold text-white">Back to Feed</native:text>
                </native:pressable>
            </x-empty-state>
        @else
            @foreach ($posts as $post)
                <x-thread-post :post="$post" :expanded-ids="$expanded" />
            @endforeach

            <x-list-footer :loading="$loadingMore" :error="$loadMoreError" tap="loadMore" :has-more="$hasMore" end-text="There are no more bookmarks to load, or you have scrolled too far." />
        @endif

        <native:column class="w-full h-20" />
    </native:column>
</native:list>

@php
    $menuPost = $menuPostId !== '' ? $this->menuPostData() : null;
    $menuPinned = (bool) data_get($menuPost, 'pinned', false);
    $canPinMenu = $menuPostId !== '' && $this->canPinMenuPost();
    $canEditMenu = $menuPostId !== '' && $this->canEditMenuPost();
    $canDeleteMenu = $menuPostId !== '' && $this->canDeleteMenuPost();
    $canViewMenuLikes = $menuPostId !== '' && $this->canViewMenuLikes();
@endphp
@include('native.post-menu-sheet')

@include('native.app-menu-sheet')
