@php $viewerUsername = (string) data_get(\App\Support\AccountStore::active(), 'username', ''); @endphp
<native:list
    native:key="detail-list"
    on-end-reached="loadMoreComments"
    :end-reached-buffer="1"
    class="safe-area bg-theme-background h-full w-full"
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
                        native:key="detail-comment-{{ $id }}"
                        label="Write a comment..."
                        placeholder="Write a comment..."
                        multiline
                        native:model="comment"
                        :selection-debounce-ms="400"
                        @selectionChange="updateMentionSelection(-1)"
                    />
                    @if ($mentionPostIndex === -1)
                        @if ($mentionKind === 'hashtag')
                            <x-hashtag-suggestions :hashtags="$mentionResults" />
                        @else
                            <x-mention-suggestions :users="$mentionResults" />
                        @endif
                    @endif
                    <x-image-preview :images="$this->imagesForPost(-1)" class="mt-2 flex flex-wrap gap-2" />
                    <native:row class="w-full items-center gap-2">
                        <x-composer-image-picker
                            :post-index="-1"
                            label="Add an image to your reply"
                            :enabled="$this->supportsImagePicker()"
                            :can-add="$this->canAddImage()"
                        />
                        <native:pressable
                            class="h-8 w-8 items-center justify-center"
                            @tap="toggleCommentPoll"
                            :a11y-label="$commentIsPoll ? 'Remove reply poll' : 'Create reply poll'"
                        >
                            <native:icon
                                :ios="\App\Icons\Ios::ChartBar"
                                :android="\App\Icons\AndroidOutlined::InsertChart"
                                :size="16"
                                :color="$commentIsPoll ? '#EC4899' : '#64748B'"
                                dark-color="#94A3B8"
                            />
                        </native:pressable>
                        @if ($uploadingImages)
                            <native:text class="text-theme-on-surface-variant text-xs">Uploading image…</native:text>
                        @endif
                    </native:row>

                    @if ($commentIsPoll)
                        <native:column class="w-full gap-2 pl-3.5">
                            @foreach ($commentPollOptions as $index => $option)
                                <native:row class="w-full items-center gap-2">
                                    <native:column class="border-theme-outline h-3.5 w-3.5 shrink-0 rounded-full border" />
                                    <native:column class="min-w-0 flex-1">
                                        <native:outlined-text-input
                                            placeholder="Option {{ $index + 1 }}"
                                            :value="$option"
                                            @change="updateCommentPollOption({{ $index }})"
                                        />
                                    </native:column>
                                    <native:text class="text-theme-on-surface-variant shrink-0 text-xs">{{ mb_strlen($option) }}/{{ \App\NativeComponents\Detail::MAX_REPLY_POLL_OPTION_LENGTH }}</native:text>
                                    @if (count($commentPollOptions) > \App\NativeComponents\Detail::MIN_REPLY_POLL_OPTIONS)
                                        <native:pressable
                                            class="shrink-0 p-1"
                                            @tap="removeCommentPollOption({{ $index }})"
                                            a11y-label="Remove option {{ $index + 1 }}"
                                        >
                                            <native:icon
                                                :ios="\App\Icons\Ios::Xmark"
                                                :android="\App\Icons\AndroidOutlined::Close"
                                                :size="16"
                                                color="#64748B"
                                                dark-color="#94A3B8"
                                            />
                                        </native:pressable>
                                    @endif
                                </native:row>
                            @endforeach

                            @if (count($commentPollOptions) < \App\NativeComponents\Detail::MAX_REPLY_POLL_OPTIONS)
                                <native:pressable
                                    class="border-theme-outline-variant w-full items-start rounded-lg border py-2 pl-3"
                                    @tap="addCommentPollOption"
                                    a11y-label="Add another reply poll option"
                                >
                                    <native:text class="text-theme-on-surface-variant text-sm">Add another option</native:text>
                                </native:pressable>
                            @endif

                            <native:row class="w-full flex-wrap items-center gap-2">
                                <native:text class="text-theme-on-surface-variant text-xs">Duration</native:text>
                                @foreach (\App\NativeComponents\Detail::REPLY_POLL_DURATIONS as $days => $label)
                                    <native:pressable
                                        class="rounded-md px-2.5 py-1.5 {{ $commentPollDuration === $days ? 'bg-theme-primary' : 'border border-theme-outline-variant' }}"
                                        @tap="setCommentPollDuration({{ $days }})"
                                        a11y-label="Reply poll duration {{ $label }}"
                                    >
                                        <native:text class="text-xs {{ $commentPollDuration === $days ? 'text-white' : 'text-theme-on-surface' }}">{{ $label }}</native:text>
                                    </native:pressable>
                                @endforeach
                            </native:row>
                        </native:column>
                    @endif

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
                    :disabled="$commentBusy || $captchaLoading || $pickingImages || $uploadingImages"
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
