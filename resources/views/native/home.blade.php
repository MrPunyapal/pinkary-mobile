<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        <x-feed-tabs :tab="$tab" />

        @if ($loading)
            <native:column class="w-full items-center gap-3 px-5 py-10">
                <native:activity-indicator />
                <native:text class="text-sm text-theme-on-surface-variant">Loading the latest conversations…</native:text>
            </native:column>
        @elseif ($errorMessage !== '')
            <native:column class="w-full mx-4 my-5 p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry">
                    <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                </native:pressable>
            </native:column>
        @elseif (count($posts) === 0)
            @php
                $emptyTitle = match ($tab) {
                    'following' => 'Your following feed is empty.',
                    'trending' => 'There are no trending questions right now.',
                    default => 'There are no questions to show.',
                };
                $emptySubtitle = match ($tab) {
                    'following' => 'Follow more people or check back once they publish new questions.',
                    'trending' => 'Check back soon as new questions and discussions gain traction.',
                    default => 'Try switching to another feed view or come back once new posts are published.',
                };
            @endphp
            <x-empty-state :title="$emptyTitle" :subtitle="$emptySubtitle" />
        @else
            @foreach ($posts as $post)
                <x-thread-post :post="$post" :expanded-ids="$expanded" />
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
                    <native:text class="text-sm text-center text-theme-on-surface-variant">There are no more questions to load, or you have scrolled too far.</native:text>
                </native:column>
            @endif
        @endif

        <native:column class="w-full h-20" />
    </native:column>
</native:scroll-view>
