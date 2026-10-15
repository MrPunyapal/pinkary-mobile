@props(['post', 'rail' => false, 'railTop' => false, 'moreLink' => null, 'flushTop' => false, 'bare' => false, 'expanded' => false, 'full' => false, 'comment' => false, 'viewerUsername' => null, 'activeYoutubePostId' => '', 'youtubeEmbedUrl' => '', 'feedItemId' => ''])

@php
    $anonymous = (bool) data_get($post, 'anonymously', false);
    $sharedUpdate = (bool) data_get($post, 'is_update', false);

    $questionText = \App\Support\DisplayText::plain(data_get($post, 'content'));
    $answerText = \App\Support\DisplayText::plain(data_get($post, 'answer'));
    $hasAnswer = $answerText !== '';
    $pinned = (bool) data_get($post, 'pinned', false);
    $timestampSource = data_get($post, 'updated_at') ?: data_get($post, 'created_at');
    $timestamp = \App\Support\DisplayText::relativeTime(is_string($timestampSource) ? $timestampSource : null);
    $absoluteTimestamp = \App\Support\DisplayText::absoluteTime(is_string($timestampSource) ? $timestampSource : null);
    $edited = (bool) data_get($post, 'edited', false);
    $channel = data_get($post, 'channel');
    $preview = data_get($post, 'preview');
    $previewUrl = is_array($preview) ? (string) ($preview['url'] ?? '') : '';
    $images = array_values(array_filter((array) data_get($post, 'images', []), fn (mixed $src): bool => is_string($src) && $src !== ''));
    $poll = data_get($post, 'poll');
    $repostedBy = data_get($post, 'reposted_by');
    $repostedByName = is_array($repostedBy) ? mb_trim((string) data_get($repostedBy, 'name', '')) : '';
    $repostedByUsername = is_array($repostedBy) ? (string) data_get($repostedBy, 'username', '') : '';
    $postId = (string) data_get($post, 'id', '');
    $feedItemId = $feedItemId !== '' ? $feedItemId : (string) data_get($post, 'repost_id', $postId);
    $postKey = ($feedItemId !== '' ? $feedItemId.'-' : '').$postId;
    $liked = (bool) data_get($post, 'metrics.liked', false);
    $bookmarked = (bool) data_get($post, 'metrics.bookmarked', false);
    $reposted = (bool) data_get($post, 'metrics.reposted', false);
    $reactionError = (string) data_get($post, 'reaction_error', '');
    $commentsCount = (int) data_get($post, 'metrics.comments', 0);
    $likesCount = (int) data_get($post, 'metrics.likes', 0);
    $repostsCount = (int) data_get($post, 'metrics.reposts', 0);
    $bookmarksCount = (int) data_get($post, 'metrics.bookmarks', 0);
    $viewsCount = (int) data_get($post, 'views', 0);

    $viewerUsername = mb_strtolower(mb_trim((string) ($viewerUsername ?? data_get(\App\Support\AccountStore::active(), 'username', ''))));
    $canAnswer = ! $comment
        && ! $sharedUpdate
        && ! $hasAnswer
        && $viewerUsername !== ''
        && $viewerUsername === mb_strtolower(mb_trim((string) data_get($post, 'to.username', '')));

    // Native views cannot measure text height; approximate 640px with 27 lines of 36 characters.
    $answerLimit = 972;
    $answerLong = mb_strlen($answerText) > $answerLimit;
    $answerShown = $full || $expanded || ! $answerLong
        ? $answerText
        : mb_substr($answerText, 0, $answerLimit).'…';
    $hasMoreLink = $moreLink !== null && $moreLink !== '';
    $contentBottomPad = $hasMoreLink ? 'pb-0.5' : ($bare ? 'pb-1' : 'pb-3');

    $laneAuthor = $comment ? data_get($post, 'from', []) : data_get($post, 'to', []);
    $laneText = $comment ? ($answerText !== '' ? $answerText : $questionText) : $answerShown;
    $hasPreview = is_array($preview) && $previewUrl !== '';
    $hasPoll = is_array($poll) && $poll !== [];
    $hasLaneMedia = $images !== [] || $hasPreview || $hasPoll;
    $showLane = $comment ? ($laneText !== '' || $hasLaneMedia) : ($hasAnswer || $canAnswer || $hasLaneMedia);

    $topPad = $sharedUpdate && ! $flushTop ? 'pt-3' : 'pt-2';
@endphp

@if (! \Native\Mobile\System::runningInJump())
    <native:impression
        native:key="impression-{{ $postKey }}"
        identity="{{ $postId }}"
        :enabled="$hasAnswer"
        on-impression="recordPostView('{{ $postId }}')"
        class="w-full"
    >


@endif
<native:column native:key="post-{{ $postKey }}" class="w-full {{ $bare ? '' : 'bg-theme-surface' }}">
    @if (! $comment && $repostedByName !== '')
        <native:pressable
            class="w-full px-2 pt-2"
            @tap="openProfile('{{ $repostedByUsername }}')"
            a11y-label="Open {{ $repostedByName }}’s profile"
        >
            <native:row class="w-full items-center gap-2">
                <x-avatar :user="$repostedBy" :size="7" :link="false" :border="false" />
                <native:row class="min-w-0 flex-1 items-center gap-1">
                    <native:text
                        class="text-theme-on-surface min-w-0 flex-1 text-sm font-medium"
                        max-lines="1"
                    >{{ $repostedByName }}</native:text>
                    <native:text class="text-theme-on-surface-variant shrink-0 text-sm" max-lines="1"
                        >reposted</native:text>
                </native:row>
            </native:row>
        </native:pressable>
    @endif

    @unless ($sharedUpdate || $comment)
        <native:column class="w-full px-2 {{ $bare ? 'pt-2' : 'pt-3' }}">
            <native:column class="bg-theme-bubble border-theme-outline-variant/50 w-full gap-2 rounded-md border px-3 py-2.5">
                <native:row class="w-full items-start justify-between gap-3">
                    <native:column class="min-w-0 flex-1">
                        @if ($anonymous)
                            <native:row class="w-full items-center gap-3">
                                <x-avatar :anonymous="true" :size="8" />
                                <native:text class="text-theme-on-surface-variant text-sm font-medium">Anonymously</native:text>
                            </native:row>
                        @else
                            <x-post-author :author="data_get($post, 'from', [])" />
                        @endif
                    </native:column>
                    @if ($pinned)
                        <native:row class="shrink-0 items-center gap-2">
                            <native:row class="bg-theme-surface-variant items-center gap-1.5 rounded-full px-2.5 py-1.5">
                                <native:icon
                                    :ios="\App\Icons\Ios::Pin"
                                    :android="\App\Icons\Android::PushPin"
                                    :size="16"
                                    color="#64748B"
                                    dark-color="#94A3B8"
                                />
                                {{-- Arbitrary font sizes use points; rem units are not supported. --}}
                                <native:text
                                    class="text-theme-on-surface-variant font-medium text-[11.52]"
                                    max-lines="1"
                                >Pinned</native:text>
                            </native:row>
                        </native:row>
                    @endif
                </native:row>

                @if ($questionText !== '')
                    {{-- Numeric line-height classes are unsupported; leading-loose gives 28px at text-sm. --}}
                    <x-post-text :html="data_get($post, 'content')" :post-id="$postId" :question="true" :key-prefix="$postKey" />
                    @if ($viewerUsername === '')
                        <native:row class="w-full items-center pt-1">
                            <x-post-action type="translate" :post-id="$postId" field="content" />
                        </native:row>
                    @endif
                @endif
            </native:column>
        </native:column>
    @endunless

    @if ($showLane)
        <native:column class="relative w-full">
            <native:row class="w-full px-2">
                <native:column class="w-5 shrink-0" />
                {{-- Use measured content height; stretched siblings can affect post layout. --}}
                <native:column
                    native:key="thread-rail-{{ $postKey }}"
                    class="flex-1 min-w-0 pl-0.5 {{ $rail ? 'bg-theme-outline' : 'bg-theme-surface' }}"
                >
                    <native:column class="w-full gap-1.5 min-w-0 pl-[30] bg-theme-surface {{ $topPad }} {{ $contentBottomPad }}">
                        <native:row class="w-full items-center justify-between gap-2">
                            <native:pressable
                                class="min-w-0 flex-1"
                                @tap="openProfile('{{ data_get($laneAuthor, 'username', '') }}')"
                                a11y-label="Open {{ '@'.data_get($laneAuthor, 'username', '') }}’s profile"
                            >
                                <native:row class="items-center gap-1.5">
                                    <native:text
                                        class="text-theme-on-surface shrink-0 text-sm font-medium"
                                        max-lines="1"
                                    >{{ data_get($laneAuthor, 'name') }}</native:text>
                                    @if (data_get($laneAuthor, 'verified'))
                                        <native:icon
                                            :ios="\App\Icons\Ios::CheckmarkSealFill"
                                            :android="\App\Icons\Android::Verified"
                                            :size="14"
                                            color="#EC4899"
                                        />
                                    @endif
                                    <native:text
                                        class="text-theme-on-surface-variant flex-1 text-sm"
                                        max-lines="1"
                                    >{{ '@'.data_get($laneAuthor, 'username') }}</native:text>
                                </native:row>
                            </native:pressable>
                            <native:row class="shrink-0 items-center gap-1.5">
                                @if (is_array($channel) && ($channel['name'] ?? '') !== '')
                                    <native:pressable
                                        class="shrink-0"
                                        @tap="openChannel({{ json_encode($channel['slug'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}, {{ json_encode($channel['name'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }})"
                                        a11y-label="Open {{ '#'.$channel['name'] }} channel feed"
                                    >
                                        <native:row class="bg-theme-surface-variant items-center gap-1 rounded-md px-1.5 py-0.5">
                                            <native:icon
                                                :ios="\App\Icons\Ios::Tag"
                                                :android="\App\Icons\AndroidOutlined::Sell"
                                                :size="12"
                                                color="#EC4899CC"
                                            />
                                            <native:text
                                                class="text-theme-on-surface-variant font-medium text-[11.52]"
                                                max-lines="1"
                                            >{{ $channel['name'] }}</native:text>
                                        </native:row>
                                    </native:pressable>
                                    <native:rect class="bg-theme-outline h-1 w-1 rounded-full" />
                                @endif
                                @if ($timestamp !== '')
                                    <native:text class="text-theme-on-surface-variant shrink-0 text-xs" max-lines="1"
                                        a11y-label="{{ ($edited ? 'Edited on ' : 'Posted on ').$absoluteTimestamp }}"
                                        >{{ $edited ? 'Edited: ' : '' }}{{ $timestamp }}</native:text>
                                @endif
                            </native:row>
                        </native:row>
                        @if ($laneText !== '')
                            <x-post-text
                                :html="$comment && $answerText === '' ? data_get($post, 'content') : data_get($post, 'answer')"
                                :post-id="$postId"
                                :key-prefix="$postKey"
                                :limit="! $comment && ! $full && ! $expanded && $answerLong ? $answerLimit : null"
                            />
                        @endif
                        @if ($canAnswer)
                            <native:text class="text-theme-on-surface-variant w-full text-sm">This question is waiting for your answer.</native:text>
                        @endif

                        <x-post-images :images="$images" :key-prefix="$postKey" />

                        @unless ($comment)
                            @if ($answerLong && ! $full && ! $expanded)
                                <x-view-more :post-id="$postId" />
                            @endif
                        @endunless

                        @if ($hasPreview)
                            @if ($activeYoutubePostId === $feedItemId && $youtubeEmbedUrl !== '')
                                <native:column class="w-full gap-1">
                                    <native:webview
                                        native:key="youtube-player-{{ $postKey }}"
                                        :src="$youtubeEmbedUrl"
                                        javascript
                                        dom-storage
                                        class="h-56 w-full rounded-lg"
                                        a11y-label="{{ str_contains(mb_strtolower((string) ($preview['host'] ?? '')), 'vimeo') ? 'Vimeo' : 'YouTube' }} video player"
                                    />
                                    <native:pressable
                                        class="self-end py-1"
                                        @tap="closeYoutubeVideo"
                                        a11y-label="Close video player"
                                    >
                                        <native:text class="text-theme-primary text-xs font-medium">Close video</native:text>
                                    </native:pressable>
                                </native:column>
                            @else
                                <native:pressable
                                    native:key="post-preview-{{ $postKey }}"
                                    @tap="openPostPreview({{ json_encode($postId) }}, {{ json_encode($feedItemId) }})"
                                    a11y-label="{{ str_contains(mb_strtolower((string) ($preview['host'] ?? '')), 'youtu') ? 'Play YouTube video' : (str_contains(mb_strtolower((string) ($preview['host'] ?? '')), 'vimeo') ? 'Play Vimeo video' : 'Open link: '.($preview['host'] ?? $previewUrl)) }}"
                                >
                                    @if (($preview['image'] ?? null) !== null && $preview['image'] !== '')
                                        <native:column class="relative w-full gap-0 rounded-lg">
                                            <x-lazy-image
                                                src="{{ $preview['image'] }}"
                                                alt="{{ $preview['title'] ?? $preview['url'] }}"
                                                class="h-48 w-full rounded-lg object-contain"
                                            />
                                            <native:column class="w-full gap-0 rounded-b-lg bg-pink-800/55 px-2 py-1">
                                                <native:text
                                                    class="text-xs font-semibold text-white"
                                                    max-lines="1"
                                                >{{ $preview['title'] ?? $preview['url'] }}</native:text>
                                            </native:column>
                                        </native:column>
                                    @else
                                        <native:column class="bg-theme-bubble border-theme-outline-variant/50 w-full items-center gap-2 rounded-lg border px-3 py-4">
                                            <native:row class="w-full items-center gap-2">
                                                <native:icon
                                                    :ios="\App\Icons\Ios::PlayCircle"
                                                    :android="\App\Icons\AndroidOutlined::PlayCircle"
                                                    :size="18"
                                                    color="#EC4899"
                                                />
                                                <native:text
                                                    class="text-theme-on-surface flex-1 text-xs font-semibold"
                                                    max-lines="1"
                                                >{{ $preview['host'] ?? $preview['url'] }}</native:text>
                                            </native:row>
                                            <native:text
                                                class="text-theme-on-surface-variant w-full text-xs"
                                                max-lines="1"
                                            >{{ $previewUrl }}</native:text>
                                        </native:column>
                                    @endif
                                </native:pressable>
                            @endif
                        @endif

                        @if ($hasPoll)
                            <x-post-poll :poll="$poll" :post-id="$postId" :key-prefix="$postKey" :error="(string) data_get($post, 'poll_error', '')" />
                        @endif

                        <native:row class="w-full items-center gap-3 pt-3">
                            <x-post-action type="comments" :post-id="$postId" :count="$commentsCount" />
                            <native:rect class="bg-theme-outline h-1 w-1 shrink-0 rounded-full" />
                            <x-post-action type="like" :post-id="$postId" :count="$likesCount" :active="$liked" />
                            <native:rect class="bg-theme-outline h-1 w-1 shrink-0 rounded-full" />
                            <x-post-action
                                type="repost"
                                :post-id="$postId"
                                :count="$repostsCount"
                                :active="$reposted"
                            />
                            <native:rect class="bg-theme-outline h-1 w-1 shrink-0 rounded-full" />
                            <x-post-action type="views" :count="$viewsCount" />
                            <native:rect class="bg-theme-outline h-1 w-1 shrink-0 rounded-full" />
                            <x-post-action
                                type="bookmark"
                                :post-id="$postId"
                                :count="$bookmarksCount"
                                :active="$bookmarked"
                            />
                            @if ($viewerUsername === '')
                                <native:rect class="bg-theme-outline h-1 w-1 shrink-0 rounded-full" />
                                <x-post-action
                                    type="translate"
                                    :post-id="$postId"
                                    :field="$comment && $answerText === '' ? 'content' : 'answer'"
                                />
                            @endif
                            {{-- Use a native spacer because ml-auto is unreliable in this renderer. --}}
                            <native:spacer class="flex-1" />
                            <x-post-action type="share" :post-id="$postId" />
                            @if ($viewerUsername !== '')
                                <native:rect class="bg-theme-outline h-1 w-1 shrink-0 rounded-full" />
                                <x-post-action type="menu" :post-id="$postId" />
                            @endif
                        </native:row>
                        @if ($reactionError !== '')
                            <native:text class="text-theme-destructive w-full text-xs">{{ $reactionError }}</native:text>
                        @endif
                    </native:column>
                </native:column>
            </native:row>

            @if ($rail && ! $railTop)
                <native:rect class="absolute left-[28] top-0 w-0.5 {{ $topPad === 'pt-3' ? 'h-[52]' : 'h-[48]' }} bg-theme-surface" />
            @elseif ($railTop && ! $rail)
                <native:rect class="absolute left-[28] top-0 w-0.5 {{ $topPad === 'pt-3' ? 'h-3' : 'h-2' }} bg-theme-outline" />
            @endif

            <native:column
                native:key="post-avatar-{{ $postKey }}"
                class="absolute left-2 {{ $topPad === 'pt-3' ? 'top-3' : 'top-2' }} w-10 h-10"
            >
                <x-avatar :user="$laneAuthor" />
            </native:column>
        </native:column>
    @endif

    @if ($moreLink !== null && $moreLink !== '')
        <x-post-divider :link="$moreLink" />
    @endif
    @unless ($bare)
        <native:rect native:key="post-separator" class="bg-theme-outline-variant h-px w-full" />
    @endunless
</native:column>
@if (! \Native\Mobile\System::runningInJump())
    </native:impression>
@endif
