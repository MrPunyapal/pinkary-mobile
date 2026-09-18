<native:top-bar title="Bookmarks" />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        @if ($loading)
            <native:column class="w-full items-center gap-3 px-5 py-10">
                <native:activity-indicator />
                <native:text class="text-sm text-theme-on-surface-variant">Loading your bookmarks…</native:text>
            </native:column>
        @elseif ($errorMessage !== '')
            <native:column class="w-full mx-4 my-5 p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry">
                    <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                </native:pressable>
            </native:column>
        @elseif (count($posts) === 0)
            <native:column class="w-full items-center gap-2 px-5 py-10">
                <native:text class="text-lg font-medium text-theme-on-surface">No bookmarks yet.</native:text>
                <native:text class="text-sm text-center text-theme-on-surface-variant">Posts you save for later will show up here.</native:text>
            </native:column>
        @else
            @foreach ($posts as $post)
                <x-post :post="$post" />
            @endforeach

            @if ($hasMore)
                <native:column class="w-full items-center gap-2 px-5 py-5">
                    @if ($loadMoreError !== '')
                        <native:text class="text-sm text-center text-theme-destructive">{{ $loadMoreError }}</native:text>
                    @endif
                    <native:button variant="secondary" :disabled="$loadingMore" @tap="loadMore">{{ $loadingMore ? 'Loading…' : 'Load more' }}</native:button>
                </native:column>
            @else
                <native:column class="w-full items-center px-5 py-5">
                    <native:text class="text-sm text-center text-theme-on-surface-variant">There are no more bookmarks to load, or you have scrolled too far.</native:text>
                </native:column>
            @endif
        @endif

        <native:column class="w-full h-20" />
    </native:column>
</native:scroll-view>
