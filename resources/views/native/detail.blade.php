@php $viewerUsername = (string) data_get(\App\Support\AccountStore::active(), 'username', ''); @endphp
<native:list
    native:key="detail-list"
    on-end-reached="loadMoreComments"
    :end-reached-buffer="1"
    class="bg-theme-background h-full w-full"
>
    <x-page-heading title="Post" :back="true" />

    @if ($refreshing ?? false)
        <x-refresh-bar />
    @endif

    @if ($loading)
        <x-skeleton-feed />
    @elseif ($errorMessage !== '')
        <native:column class="bg-theme-destructive/15 border-theme-destructive mx-4 my-5 gap-3 rounded-md border p-4">
            <native:text class="text-theme-destructive text-sm">{{ $errorMessage }}</native:text>
            <native:pressable class="self-start py-1" @tap="retry" a11y-label="Try again">
                <native:text class="text-theme-destructive text-sm font-semibold">Try again</native:text>
            </native:pressable>
        </native:column>
    @else
        <x-thread-post
            :post="$post"
            :expanded-ids="$expanded"
            :viewer-username="$viewerUsername"
            :full="true"
            :active-youtube-post-id="$activeYoutubePostId"
            :youtube-embed-url="$youtubeEmbedUrl"
        />

        @if ($voteError !== '')
            <native:column class="bg-theme-destructive/15 border-theme-destructive w-full gap-3 border-b p-4">
                <native:text class="text-theme-destructive text-sm">{{ $voteError }}</native:text>
            </native:column>
        @endif

        @if ($this->canComment())
            <native:column class="bg-theme-surface border-theme-outline-variant w-full gap-3 border-b px-5 py-4">
                @if ($commentError !== '')
                    <native:column class="bg-theme-destructive/15 border-theme-destructive w-full rounded-md border p-4">
                        <native:text class="text-theme-destructive text-sm">{{ $commentError }}</native:text>
                    </native:column>
                @endif

                <native:column class="w-full gap-1">
                    <native:outlined-text-input
                        label="Write a comment..."
                        placeholder="Write a comment..."
                        multiline
                        native:model="comment"
                    />
                    @if (mb_strlen($comment) > 800)
                        <native:text class="text-xs text-right {{ mb_strlen($comment) >= 1000 ? 'text-theme-destructive' : 'text-theme-on-surface-variant' }}">{{ mb_strlen($comment) }} / 1000</native:text>
                    @endif
                </native:column>

                <x-captcha-check
                    :required="$captchaRequired"
                    :loading="$captchaLoading"
                    :completed="$captchaCompleted"
                    :url="$captchaUrl"
                    :error="$captchaError"
                />

                <native:button
                    variant="primary"
                    :disabled="$commentBusy || $captchaLoading"
                    @tap="postComment"
                >{{ $commentBusy ? 'Posting…' : 'Reply' }}</native:button>
            </native:column>
        @endif

        @if (count($comments) > 0 || $commentsFailed)
            @forelse ($comments as $comment)
                <x-post
                    :post="$comment"
                    :viewer-username="$viewerUsername"
                    :active-youtube-post-id="$activeYoutubePostId"
                    :youtube-embed-url="$youtubeEmbedUrl"
                    :comment="true"
                    :expanded="in_array((string) data_get($comment, 'id'), array_map('strval', $expanded), true)"
                />
            @empty
                @if ($commentsFailed)
                    <native:column class="w-full items-center gap-2 px-5 py-10">
                        <native:text class="text-theme-destructive text-center text-sm">Comments could not be loaded.</native:text>
                        <native:pressable class="py-1" @tap="retry" a11y-label="Try again">
                            <native:text class="text-theme-destructive text-sm font-semibold">Try again</native:text>
                        </native:pressable>
                    </native:column>
                @endif
            @endforelse

            <x-list-footer
                :loading="$loadingMoreComments"
                :error="$loadMoreCommentsError"
                tap="loadMoreComments"
                :has-more="$hasMoreComments"
            />
        @endif
    @endif

    <native:column class="h-20 w-full" />
</native:list>

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
