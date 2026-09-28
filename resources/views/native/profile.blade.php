@php
    $isMe = (bool) data_get($profile, 'is_me', false);
    $followed = (bool) data_get($profile, 'followed_by_me', false);
@endphp

<native:list on-end-reached="loadMoreQuestions" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        @if ($username !== '')
            <x-page-heading :title="'@'.(data_get($profile, 'username', $username) ?: $username)" :back="true" />
        @else
            <x-page-heading title="Profile" size="3xl" />
        @endif

        @if ($refreshing ?? false)
            <x-refresh-bar />
        @endif

        @if ($loading)
            <native:column class="w-full px-4 py-4 gap-4">
                <x-skeleton-profile />
            </native:column>
        @elseif ($errorMessage !== '')
            <native:column class="mx-4 my-5 p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry" a11y-label="Try again">
                    <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                </native:pressable>
            </native:column>
        @else
            <native:column class="w-full px-4 pt-4">
                <x-profile-card :profile="$profile" :is-me="$isMe" :followed="$followed" :follow-busy="$followBusy" :show-avatar="$showAvatar" :link-error="$linkError" />
            </native:column>

            {{-- Posts are full-bleed like the feed. The card above owns
                the padded profile chrome. --}}
            <native:column class="w-full gap-0 mt-4">
                @if ($questionsLoading)
                    <x-skeleton-feed />
                @elseif ($questionsError !== '')
                    <native:column class="mx-4 my-5 p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                        <native:text class="text-sm text-theme-destructive">{{ $questionsError }}</native:text>
                        <native:pressable class="self-start py-1" @tap="retryQuestions" a11y-label="Try again">
                            <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                        </native:pressable>
                    </native:column>
                @elseif (count($questions) === 0)
                    <x-empty-state title="No posts yet." :subtitle="$isMe ? 'Share your first update to get started.' : ''" />
                @else
                    @foreach ($questions as $post)
                        <x-thread-post :post="$post" :expanded-ids="$expanded" />
                    @endforeach

                    <x-list-footer :loading="$loadingMoreQuestions" :error="$loadMoreQuestionsError" tap="loadMoreQuestions" :has-more="$hasMoreQuestions" />
                @endif
            </native:column>
        @endif

        <native:column class="w-full h-20" />
    </native:column>
</native:list>

@if (($showQr ?? false) && $isMe)
    @php
        $qrTheme = isDark() ? 'dark' : 'light';
        $qrProfileUrl = rtrim((string) config('services.pinkary.url'), '/').'/@'.data_get($profile, 'username', '');
        $qrImageUrl = rtrim((string) config('services.pinkary.url'), '/').'/api/v1/users/'.data_get($profile, 'username', '').'/qr-code?theme='.$qrTheme;
    @endphp
    <native:bottom-sheet visible @dismiss="toggleQr">
        <native:column class="w-full gap-3 px-5 py-4 bg-theme-surface">
            <native:column class="w-full items-center gap-2 {{ $qrTheme === 'dark' ? 'bg-[#050d1b]' : 'bg-white' }} p-3 rounded-md">
                <native:image src="{{ $qrImageUrl }}" alt="Profile QR code" class="w-56 h-56 rounded-md" />
                <native:text class="text-sm text-center {{ $qrTheme === 'dark' ? 'text-slate-300' : 'text-slate-600' }}">{{ $qrProfileUrl }}</native:text>
            </native:column>
            <native:button variant="secondary" @tap="toggleQr">Close</native:button>
        </native:column>
    </native:bottom-sheet>
@endif

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
