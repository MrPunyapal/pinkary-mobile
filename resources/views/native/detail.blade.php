<native:top-bar title="Post" back />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        @if ($loading)
            <native:column class="w-full items-center gap-3 px-5 py-10">
                <native:activity-indicator />
                <native:text class="text-sm text-theme-on-surface-variant">Loading this post…</native:text>
            </native:column>
        @elseif ($errorMessage !== '')
            <native:column class="w-full mx-4 my-5 p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry">
                    <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                </native:pressable>
            </native:column>
        @else
            <x-thread-post :post="$post" />

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

                <native:button variant="primary" :disabled="$commentBusy" @tap="postComment">{{ $commentBusy ? 'Posting…' : 'Reply' }}</native:button>
            </native:column>

            @if (count($comments) > 0 || $commentsFailed)
                <native:column class="w-full px-2 py-4">
                    <native:column class="w-full gap-3 p-4 rounded-md bg-theme-bubble border border-theme-outline-variant">
                        @forelse ($comments as $comment)
                            <x-post :post="$comment" />
                        @empty
                            @if ($commentsFailed)
                                <native:column class="w-full items-center gap-2 px-5 py-10">
                                    <native:text class="text-sm text-center text-theme-destructive">Comments could not be loaded.</native:text>
                                    <native:pressable class="py-1" @tap="retry">
                                        <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                                    </native:pressable>
                                </native:column>
                            @endif
                        @endforelse

                        @if ($hasMoreComments)
                            <native:column class="w-full items-center gap-2 px-5 py-5">
                                @if ($loadMoreCommentsError !== '')
                                    <native:text class="text-sm text-center text-theme-destructive">{{ $loadMoreCommentsError }}</native:text>
                                @endif
                                <native:button variant="secondary" :disabled="$loadingMoreComments" @tap="loadMoreComments">{{ $loadingMoreComments ? 'Loading…' : 'Load more comments' }}</native:button>
                            </native:column>
                        @endif
                    </native:column>
                </native:column>
            @endif
        @endif

        <native:column class="w-full h-20" />
    </native:column>
</native:scroll-view>
