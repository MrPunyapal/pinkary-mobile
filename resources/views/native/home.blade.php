<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        <native:pressable class="w-full px-4 py-3 bg-theme-surface border-b border-theme-outline-variant" @tap="openSearch" a11y-label="Search users or hashtags">
            <native:row class="w-full items-center gap-3 px-3 py-2.5 rounded-md bg-theme-surface-variant border border-theme-outline-variant">
                <native:icon :ios="\App\Icons\Ios::Magnifyingglass" :android="\App\Icons\AndroidOutlined::Search" :size="18" color="#64748B" dark-color="#94A3B8" />
                <native:text class="text-sm text-theme-on-surface-variant">Search users or hashtags...</native:text>
            </native:row>
        </native:pressable>

        <native:row class="w-full items-center justify-center gap-1 px-4 py-2.5 bg-theme-surface border-b border-theme-outline-variant">
            <native:pressable class="px-4 py-1.5 rounded-full {{ $tab === 'recent' ? 'bg-theme-primary' : '' }}" @tap="showRecent" a11y-label="Show Recent feed">
                <native:text class="text-sm {{ $tab === 'recent' ? 'text-white font-semibold' : 'text-theme-on-surface-variant' }}">Recent</native:text>
            </native:pressable>
            <native:pressable class="px-4 py-1.5 rounded-full {{ $tab === 'following' ? 'bg-theme-primary' : '' }}" @tap="showFollowing" a11y-label="Show Following feed">
                <native:text class="text-sm {{ $tab === 'following' ? 'text-white font-semibold' : 'text-theme-on-surface-variant' }}">Following</native:text>
            </native:pressable>
            <native:pressable class="px-4 py-1.5 rounded-full {{ $tab === 'trending' ? 'bg-theme-primary' : '' }}" @tap="showTrending" a11y-label="Show Trending feed">
                <native:text class="text-sm {{ $tab === 'trending' ? 'text-white font-semibold' : 'text-theme-on-surface-variant' }}">Trending</native:text>
            </native:pressable>
        </native:row>

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
            <native:column class="w-full items-center gap-2 px-5 py-8">
                <native:text class="text-lg font-medium text-theme-on-surface">{{ $emptyTitle }}</native:text>
                <native:text class="text-sm text-center text-theme-on-surface-variant">{{ $emptySubtitle }}</native:text>
            </native:column>
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

<native:fab icon="edit" @tap="compose" />
