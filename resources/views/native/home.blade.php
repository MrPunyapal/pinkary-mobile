@php $viewerUsername = (string) data_get(\App\Support\AccountStore::active(), 'username', ''); @endphp
<native:column class="bg-theme-surface h-full w-full">
    <native:column class="bg-theme-surface w-full justify-center">
        <native:row class="min-h-14 w-full items-center gap-3 px-4 py-4">
            <native:text class="text-theme-on-surface flex-1 text-xl font-bold" max-lines="1">Pinkary</native:text>

            <native:pressable
                class="h-9 w-9 items-center justify-center rounded-full"
                @tap="openSearch"
                a11y-label="Search"
            >
                <native:icon
                    :ios="\App\Icons\Ios::Magnifyingglass"
                    :android="\App\Icons\Android::Search"
                    :size="20"
                    color="#64748B"
                    dark-color="#94A3B8"
                />
            </native:pressable>
        </native:row>
        <native:rect class="bg-theme-outline-variant h-px w-full" />
    </native:column>

    <x-feed-tabs :tab="$tab" />

    @if ($refreshing ?? false)
        <x-refresh-bar />
    @endif

    {{-- Only post rows belong in this list; native pagination counts child views. --}}
    <native:list
        native:key="feed-list"
        on-refresh="refreshFeed"
        on-end-reached="prefetchMore"
        :end-reached-buffer="1"
        class="bg-theme-surface w-full flex-1"
    >
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
            @php
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
                <native:column class="bg-theme-surface-variant mx-4 my-6 w-full items-center gap-3 rounded-md p-6">
                    <native:text class="text-theme-on-surface w-full text-center text-xl font-semibold" max-lines="2"
                        >Sign in for a personalized feed</native:text>
                    <native:text class="text-theme-on-surface-variant w-full text-center text-sm leading-relaxed"
                        >Log in or sign up to access personalized content. Following is already supported. You just need
                        an account to see posts from the people you follow.</native:text>
                    <native:row class="w-full items-center justify-center gap-3 pt-2">
                        <native:pressable
                            class="bg-theme-primary rounded-md px-4 py-2"
                            @tap="openLogin"
                            a11y-label="Log in"
                        >
                            <native:text class="text-sm font-semibold text-white">Log In</native:text>
                        </native:pressable>
                        <native:pressable
                            class="bg-theme-primary rounded-md px-4 py-2"
                            @tap="openRegister"
                            a11y-label="Register"
                        >
                            <native:text class="text-sm font-semibold text-white">Register</native:text>
                        </native:pressable>
                    </native:row>
                </native:column>
            @else
                <x-empty-state :title="$emptyTitle" :subtitle="$emptySubtitle" />
            @endif
        @else
            @foreach ($posts as $post)
                <x-thread-post :post="$post" :expanded-ids="$expanded" :viewer-username="$viewerUsername" />
            @endforeach

        @endif
    </native:list>
    @if (! $loading && $errorMessage === '' && count($posts) > 0)
        <x-list-footer
            :loading="$loadingMore"
            :error="$loadMoreError"
            tap="loadMore"
            :has-more="$hasMore"
            end-text="You're all caught up."
        />
    @endif
</native:column>

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
