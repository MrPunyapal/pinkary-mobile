{{-- Feed is a native:list: pull-to-refresh + on-end-reached auto-load (web intersect parity). Footer is spinner/error/end-copy only. --}}
<native:list on-refresh="refreshFeed" on-end-reached="loadMore" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        {{-- App header: Centered Pinkary brand with search action --}}
        <native:column class="w-full bg-theme-surface border-b border-theme-outline-variant justify-center">
            <native:row class="w-full items-center justify-between px-4 py-4 min-h-14">
                {{-- Left spacer keeps brand centered --}}
                <native:column class="w-9 h-9 shrink-0" />

                <native:text class="text-xl font-bold text-center text-theme-primary" max-lines="1">Pinkary</native:text>

                <native:pressable class="w-9 h-9 items-center justify-center rounded-full" @tap="openSearch" a11y-label="Search">
                    <native:icon :ios="\App\Icons\Ios::Magnifyingglass" :android="\App\Icons\Android::Search" :size="20" color="#64748B" dark-color="#94A3B8" />
                </native:pressable>
            </native:row>
        </native:column>

        <x-feed-tabs :tab="$tab" />

        @if ($loading)
            <native:column class="w-full items-center gap-3 px-5 py-10">
                <native:activity-indicator />
                <native:text class="text-sm text-theme-on-surface-variant">Loading the latest conversations…</native:text>
            </native:column>
        @elseif ($errorMessage !== '')
            <native:column class="mx-4 my-5 p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry" a11y-label="Try again">
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

            <x-list-footer :loading="$loadingMore" :error="$loadMoreError" tap="loadMore" :has-more="$hasMore" end-text="There are no more questions to load, or you have scrolled too far." />
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
