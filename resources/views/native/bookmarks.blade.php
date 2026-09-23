<native:list on-end-reached="loadMore" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        <x-page-heading title="Bookmarks" />

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

@if ($menuPostId !== '')
    @php
        $menuPost = $this->menuPostData();
        $menuPinned = (bool) data_get($menuPost, 'pinned', false);
        $menuLikesCount = (int) data_get($menuPost, 'metrics.likes', 0);
    @endphp
    <native:bottom-sheet visible @dismiss="closeMenu">
        <native:column class="w-full gap-1 px-5 py-4 bg-theme-surface">
            @if ($menuError !== '')
                <native:text class="text-sm text-theme-destructive">{{ $menuError }}</native:text>
            @endif

            @if ($menuStep === 'confirm-delete')
                <native:column class="w-full gap-2 py-2">
                    <native:text class="text-base font-semibold text-theme-on-surface">Delete this post?</native:text>
                    <native:text class="text-sm text-theme-on-surface-variant">This removes the post from Pinkary.</native:text>
                    <native:row class="w-full items-center gap-3 pt-2">
                        <native:button variant="secondary" @tap="menuBack">Cancel</native:button>
                        <native:button variant="primary" :disabled="$menuBusy" @tap="ignoreMenuPost">{{ $menuBusy ? 'Deleting…' : 'Delete' }}</native:button>
                    </native:row>
                </native:column>
            @elseif ($menuStep === 'likers')
                <native:column class="w-full gap-1 py-2">
                    <native:row class="w-full items-center gap-3">
                        <native:pressable class="py-1" @tap="menuBack" a11y-label="Back to menu">
                            <native:text class="text-sm font-semibold text-theme-primary">Back</native:text>
                        </native:pressable>
                        <native:text class="text-base font-semibold text-theme-on-surface">Likes</native:text>
                    </native:row>
                    @if ($likersError !== '')
                        <native:text class="text-sm text-theme-destructive">{{ $likersError }}</native:text>
                    @endif
                    @foreach ($likers as $liker)
                        <x-user-row :user="$liker" />
                    @endforeach
                    @if ($likersHasMore)
                        <x-load-more tap="loadMoreLikers" :error="$likersError" />
                    @endif
                </native:column>
            @else
                @if (! $this->menuDeniedFor($menuPostId, 'pin'))
                    <native:pressable class="w-full py-3" @tap="togglePinMenu" a11y-label="{{ $menuPinned ? 'Unpin this post' : 'Pin this post' }}">
                        <native:row class="w-full items-center gap-3">
                            <native:icon :ios="\App\Icons\Ios::Pin" :android="\App\Icons\Android::PushPin" :size="18" color="#EC4899" />
                            <native:text class="text-base text-theme-on-surface">{{ $menuPinned ? 'Unpin' : 'Pin' }}</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                @if (! $this->menuDeniedFor($menuPostId, 'delete'))
                    <native:pressable class="w-full py-3" @tap="askDeleteMenu" a11y-label="Delete this post">
                        <native:row class="w-full items-center gap-3">
                            <native:icon :ios="\App\Icons\Ios::Trash" :android="\App\Icons\AndroidOutlined::Delete" :size="18" color="#64748B" />
                            <native:text class="text-base text-theme-destructive">Delete</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                @if ($menuLikesCount > 0 && ! $this->menuDeniedFor($menuPostId, 'likes'))
                    <native:pressable class="w-full py-3" @tap="openLikersMenu" a11y-label="View likes">
                        <native:row class="w-full items-center gap-3">
                            <native:icon :ios="\App\Icons\Ios::Heart" :android="\App\Icons\AndroidOutlined::FavoriteBorder" :size="18" color="#EC4899" />
                            <native:text class="text-base text-theme-on-surface">View likes</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                <native:pressable class="w-full py-3" @tap="shareMenuPost" a11y-label="Share this post">
                    <native:row class="w-full items-center gap-3">
                        <native:icon :ios="\App\Icons\Ios::SquareAndArrowUp" :android="\App\Icons\AndroidOutlined::Share" :size="18" color="#64748B" />
                        <native:text class="text-base text-theme-on-surface">Share</native:text>
                    </native:row>
                </native:pressable>
                <native:pressable class="w-full py-3" @tap="shareToXMenu" a11y-label="Post to X">
                    <native:row class="w-full items-center gap-3">
                        <native:text class="text-base text-theme-on-surface">Post to X</native:text>
                    </native:row>
                </native:pressable>
                <native:pressable class="w-full py-3" @tap="closeMenu" a11y-label="Close menu">
                    <native:row class="w-full items-center gap-3">
                        <native:text class="text-base text-theme-on-surface-variant">Cancel</native:text>
                    </native:row>
                </native:pressable>
            @endif
        </native:column>
    </native:bottom-sheet>
@endif
