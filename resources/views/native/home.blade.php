<native:top-bar title="Feed">
    <native:top-bar-action id="profile" label="Profile" @tap="profile" />
</native:top-bar>

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0 safe-area-bottom">
        <native:column class="w-full px-5 py-5 gap-1 bg-theme-surface border-b border-theme-outline-variant">
            <native:text class="text-2xl font-semibold text-theme-on-surface">Feed</native:text>
            <native:text class="text-sm text-theme-on-surface-variant">Thoughtful questions and answers from Pinkary.</native:text>
        </native:column>

        <native:row class="w-full px-5 py-3 gap-2 bg-theme-surface border-b border-theme-outline-variant">
            <native:button :variant="$tab === 'recent' ? 'primary' : 'secondary'" @tap="showRecent">Recent</native:button>
            <native:button :variant="$tab === 'following' ? 'primary' : 'secondary'" @tap="showFollowing">Following</native:button>
            <native:button :variant="$tab === 'trending' ? 'primary' : 'secondary'" @tap="showTrending">Trending</native:button>
        </native:row>

        @if ($loading)
            <native:column class="w-full items-center gap-3 px-5 py-10">
                <native:activity-indicator />
                <native:text class="text-sm text-theme-on-surface-variant">Loading the latest conversations…</native:text>
            </native:column>
        @elseif ($errorMessage !== '')
            <native:column class="w-full mx-4 my-5 p-4 rounded-lg bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
            </native:column>
        @elseif (count($posts) === 0)
            <native:column class="w-full items-center gap-2 px-5 py-10">
                <native:text class="text-lg font-semibold text-theme-on-surface">There are no questions to show.</native:text>
                <native:text class="text-sm text-center text-theme-on-surface-variant">Come back once new conversations are published.</native:text>
            </native:column>
        @else
            @foreach ($posts as $post)
                <x-post :post="$post" />
            @endforeach
        @endif
    </native:column>
</native:scroll-view>
