<native:list on-end-reached="loadMoreComments" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        <x-page-heading title="Post" :back="true" />
        @if ($loading)
            <native:column class="w-full items-center gap-3 px-5 py-10">
                <native:activity-indicator />
                <native:text class="text-sm text-theme-on-surface-variant">Loading this postâ€¦</native:text>
            </native:column>
        @elseif ($errorMessage !== '')
            <native:column class="mx-4 my-5 p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry" a11y-label="Try again">
                    <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                </native:pressable>
            </native:column>
        @else
            <x-thread-post :post="$post" :expanded-ids="$expanded" />

            @if ($voteError !== '')
                <native:column class="w-full p-4 gap-3 bg-theme-destructive/15 border-b border-theme-destructive">
                    <native:text class="text-sm text-theme-destructive">{{ $voteError }}</native:text>
                </native:column>
            @endif

            @if ($this->canComment())
                <native:column class="w-full px-5 py-4 gap-3 bg-theme-surface border-b border-theme-outline-variant">
                    @if ($commentError !== '')
                        <native:column class="w-full p-4 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                            <native:text class="text-sm text-theme-destructive">{{ $commentError }}</native:text>
                        </native:column>
                    @endif

                    <native:column class="w-full gap-1">
                        <native:outlined-text-input label="Write a comment..." placeholder="Write a comment..." multiline native:model="comment" />
                        @if (mb_strlen($comment) > 800)
                            <native:text class="text-xs text-right {{ mb_strlen($comment) >= 1000 ? 'text-theme-destructive' : 'text-theme-on-surface-variant' }}">{{ mb_strlen($comment) }} / 1000</native:text>
                        @endif
                    </native:column>

                    <native:button variant="primary" :disabled="$commentBusy" @tap="postComment">{{ $commentBusy ? 'Postingâ€¦' : 'Reply' }}</native:button>
                </native:column>
            @endif

            @if (count($comments) > 0 || $commentsFailed)
                @forelse ($comments as $comment)
                    <x-post :post="$comment" :comment="true" :expanded="in_array((string) data_get($comment, 'id'), array_map('strval', $expanded), true)" />
                @empty
                    @if ($commentsFailed)
                        <native:column class="w-full items-center gap-2 px-5 py-10">
                            <native:text class="text-sm text-center text-theme-destructive">Comments could not be loaded.</native:text>
                            <native:pressable class="py-1" @tap="retry" a11y-label="Try again">
                                <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                            </native:pressable>
                        </native:column>
                    @endif
                @endforelse

                <x-list-footer :loading="$loadingMoreComments" :error="$loadMoreCommentsError" tap="loadMoreComments" :has-more="$hasMoreComments" />
            @endif
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

