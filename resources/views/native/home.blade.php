{{-- Feed is a native:list: pull-to-refresh + on-end-reached auto-load (web intersect parity). Footer is spinner/error/end-copy only. --}}
<native:list on-refresh="refreshFeed" on-end-reached="loadMore" class="w-full h-full bg-theme-surface">
    <native:column class="w-full gap-0">
        {{-- App header: wordmark left, search action trailing (web parity:
            ../pinkary/resources/views/layouts/app.blade.php:83-95 is a
            left-identity / right-action row; the web wordmark ink is
            #0F172A, not the brand pink). --}}
        <native:column class="w-full bg-theme-surface border-b border-theme-outline-variant justify-center">
            <native:row class="w-full items-center gap-3 px-4 py-4 min-h-14">
                <native:text class="flex-1 text-xl font-bold text-theme-on-surface" max-lines="1">Pinkary</native:text>

                <native:pressable class="w-9 h-9 items-center justify-center rounded-full" @tap="openSearch" a11y-label="Search">
                    <native:icon :ios="\App\Icons\Ios::Magnifyingglass" :android="\App\Icons\Android::Search" :size="20" color="#64748B" dark-color="#94A3B8" />
                </native:pressable>
            </native:row>
        </native:column>

        <x-feed-tabs :tab="$tab" />

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
            @php
                // Guests get a sign-in card on Following, not "your feed is
                // empty": pinkary/resources/views/home/following.blade.php:20-38.
                // AccountStore is the auth source of truth; the web's card is
                // dashed-bordered, but border-dashed does not parse in this
                // renderer, so the fill carries the card instead of a solid
                // border the web never draws.
                $guestFollowing = $tab === 'following' && \App\Support\AccountStore::activeId() === null;

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

            @if ($guestFollowing)
                <native:column class="w-full gap-3 mx-4 my-6 p-6 rounded-md bg-theme-surface-variant items-center">
                    <native:text class="w-full text-xl font-semibold text-theme-on-surface text-center" max-lines="2">Sign in for a personalized feed</native:text>
                    <native:text class="w-full text-sm leading-relaxed text-theme-on-surface-variant text-center">Log in or sign up to access personalized content. Following is already supported. You just need an account to see posts from the people you follow.</native:text>
                    <native:row class="w-full items-center justify-center gap-3 pt-2">
                        <native:pressable class="px-4 py-2 rounded-md bg-theme-primary" @tap="openLogin" a11y-label="Log in">
                            <native:text class="text-sm font-semibold text-white">Log In</native:text>
                        </native:pressable>
                        <native:pressable class="px-4 py-2 rounded-md bg-theme-primary" @tap="openRegister" a11y-label="Register">
                            <native:text class="text-sm font-semibold text-white">Register</native:text>
                        </native:pressable>
                    </native:row>
                </native:column>
            @else
                <x-empty-state :title="$emptyTitle" :subtitle="$emptySubtitle" />
            @endif
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
