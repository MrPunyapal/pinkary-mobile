<native:list on-end-reached="loadMoreComments" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        <x-page-heading title="Post" :back="true" />
        @if ($loading)
            <native:column class="w-full items-center gap-3 px-5 py-10">
                <native:activity-indicator />
                <native:text class="text-sm text-theme-on-surface-variant">Loading this post…</native:text>
            </native:column>
        @elseif ($errorMessage !== '')
            <native:column class="mx-4 my-5 p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry">
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

                    <native:button variant="primary" :disabled="$commentBusy" @tap="postComment">{{ $commentBusy ? 'Posting…' : 'Reply' }}</native:button>
                </native:column>
            @endif

            @if (count($comments) > 0 || $commentsFailed)
                @forelse ($comments as $comment)
                    <x-post :post="$comment" :expanded="in_array((string) data_get($comment, 'id'), array_map('strval', $expanded), true)" />
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

                 @if ($loadMoreCommentsError !== '')
                     <native:column class="w-full items-center px-5 py-5">
                         <native:text class="text-sm text-center text-theme-destructive">{{ $loadMoreCommentsError }}</native:text>
                     </native:column>
                 @endif
            @endif
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
            @elseif ($menuStep === 'edit')
                <native:column class="w-full gap-3 py-2">
                    <native:text class="text-base font-semibold text-theme-on-surface">Edit answer</native:text>
                    <native:column class="w-full gap-1">
                        <native:outlined-text-input label="Answer" placeholder="Write your answer..." multiline native:model="editAnswer" />
                        @if (mb_strlen($editAnswer) > 800)
                            <native:text class="text-xs text-right {{ mb_strlen($editAnswer) >= 1000 ? 'text-theme-destructive' : 'text-theme-on-surface-variant' }}">{{ mb_strlen($editAnswer) }} / 1000</native:text>
                        @endif
                    </native:column>
                    <native:row class="w-full items-center gap-3">
                        <native:button variant="secondary" @tap="menuBack">Cancel</native:button>
                        <native:button variant="primary" :disabled="$menuBusy" @tap="saveAnswerMenu">{{ $menuBusy ? 'Saving…' : 'Save answer' }}</native:button>
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
                @if (! $this->menuDeniedFor($menuPostId, 'edit'))
                    <native:pressable class="w-full py-3" @tap="startEditMenu" a11y-label="Edit answer">
                        <native:row class="w-full items-center gap-3">
                            <native:icon :ios="\App\Icons\Ios::Pencil" :android="\App\Icons\AndroidOutlined::Edit" :size="18" color="#64748B" />
                            <native:text class="text-base text-theme-on-surface">Edit answer</native:text>
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
