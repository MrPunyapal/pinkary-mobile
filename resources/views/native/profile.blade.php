@php
    $isMe = (bool) data_get($profile, 'is_me', false);
    $followed = (bool) data_get($profile, 'followed_by_me', false);
    $viewerUsername = (string) data_get(\App\Support\AccountStore::active(), 'username', '');
@endphp

<native:list
    native:key="profile-list"
    on-end-reached="loadMoreQuestions"
    :end-reached-buffer="1"
    class="bg-theme-background h-full w-full"
>
    @if ($username !== '')
        <x-page-heading :title="'@'.(data_get($profile, 'username', $username) ?: $username)" :back="true" />
    @else
        <x-page-heading title="Profile" size="3xl" />
    @endif

    @if ($refreshing ?? false)
        <x-refresh-bar />
    @endif

    @if ($loading)
        <native:column class="w-full gap-4 px-4 py-4">
            <x-skeleton-profile />
        </native:column>
    @elseif ($errorMessage !== '')
        <native:column class="bg-theme-destructive/15 border-theme-destructive mx-4 my-5 gap-3 rounded-md border p-4">
            <native:text class="text-theme-destructive text-sm">{{ $errorMessage }}</native:text>
            <native:pressable class="self-start py-1" @tap="retry" a11y-label="Try again">
                <native:text class="text-theme-destructive text-sm font-semibold">Try again</native:text>
            </native:pressable>
        </native:column>
    @else
        <native:column class="w-full px-4 pt-4">
            <x-profile-card
                :profile="$profile"
                :is-me="$isMe"
                :followed="$followed"
                :follow-busy="$followBusy"
                :show-avatar="$showAvatar"
                :link-error="$linkError"
                :bio-error="$bioError"
            />
        </native:column>

        <native:rect class="h-4 w-full" />
        @if ($questionsLoading)
            <x-skeleton-feed />
        @elseif ($questionsError !== '')
            <native:column class="bg-theme-destructive/15 border-theme-destructive mx-4 my-5 gap-3 rounded-md border p-4">
                <native:text class="text-theme-destructive text-sm">{{ $questionsError }}</native:text>
                <native:pressable class="self-start py-1" @tap="retryQuestions" a11y-label="Try again">
                    <native:text class="text-theme-destructive text-sm font-semibold">Try again</native:text>
                </native:pressable>
            </native:column>
        @elseif (count($questions) === 0)
            <x-empty-state title="No posts yet." :subtitle="$isMe ? 'Share your first update to get started.' : ''" />
        @else
            @foreach ($questions as $post)
                <x-thread-post
                    :post="$post"
                    :feed-item-id="$this->feedItemIdentity($post)"
                    :expanded-ids="$expanded"
                    :viewer-username="$viewerUsername"
                    :active-youtube-post-id="$activeYoutubePostId"
                    :youtube-embed-url="$youtubeEmbedUrl"
                />
            @endforeach

            <x-list-footer
                :loading="$loadingMoreQuestions"
                :error="$loadMoreQuestionsError"
                tap="loadMoreQuestions"
                :has-more="$hasMoreQuestions"
            />
        @endif
    @endif

    <native:column class="h-20 w-full" />
</native:list>

@if (($showQr ?? false) && $isMe)
    @php
        $qrTheme = isDark() ? 'dark' : 'light';
        $qrUsername = (string) data_get($profile, 'username', '');
        $qrProfileUrl = mb_rtrim((string) config('services.pinkary.url'), '/').'/@'.rawurlencode($qrUsername);
        $qrImageUrl = mb_rtrim((string) config('services.pinkary.url'), '/').'/api/v1/users/'.rawurlencode($qrUsername).'/qr-code?theme='.rawurlencode($qrTheme);
    @endphp
    <native:bottom-sheet visible @dismiss="toggleQr">
        <native:column class="bg-theme-surface w-full gap-3 px-5 py-4">
            <native:column class="w-full items-center gap-2 {{ $qrTheme === 'dark' ? 'bg-[#050d1b]' : 'bg-white' }} p-3 rounded-md">
                <native:image src="{{ $qrImageUrl }}" alt="Profile QR code" class="h-56 w-56 rounded-md" />
                <native:text class="text-sm text-center {{ $qrTheme === 'dark' ? 'text-slate-300' : 'text-slate-600' }}">{{ $qrProfileUrl }}</native:text>
                @if ($qrErrorMessage !== '')
                    <native:text class="text-theme-destructive text-sm">{{ $qrErrorMessage }}</native:text>
                @endif
            </native:column>
            <native:button variant="primary" :disabled="$qrBusy" @tap="exportQrCode('{{ $qrTheme }}')">
                {{ $qrBusy ? 'Preparing QR code…' : 'Save or share QR code' }}
            </native:button>
            <native:button variant="secondary" @tap="toggleQr">Close</native:button>
        </native:column>
    </native:bottom-sheet>
@endif

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
