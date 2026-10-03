@props(['post', 'rail' => false, 'railTop' => false, 'moreLink' => null, 'flushTop' => false, 'bare' => false, 'expanded' => false, 'full' => false, 'comment' => false, 'viewerUsername' => null])

@php
    $anonymous = (bool) data_get($post, 'anonymously', false);
    $sharedUpdate = (bool) data_get($post, 'is_update', false);

    $questionText = \App\Support\DisplayText::plain(data_get($post, 'content'));
    $answerText = \App\Support\DisplayText::plain(data_get($post, 'answer'));
    $hasAnswer = $answerText !== '';
    $pinned = (bool) data_get($post, 'pinned', false);
    $timestamp = \App\Support\DisplayText::relativeTime(data_get($post, 'updated_at'));
    $edited = (bool) data_get($post, 'edited', false);
    $channel = data_get($post, 'channel');
    $preview = data_get($post, 'preview');
    $images = array_values(array_filter((array) data_get($post, 'images', []), fn (mixed $src): bool => is_string($src) && $src !== ''));
    $poll = data_get($post, 'poll');
    $postId = (string) data_get($post, 'id', '');
    $liked = (bool) data_get($post, 'metrics.liked', false);
    $bookmarked = (bool) data_get($post, 'metrics.bookmarked', false);
    $commentsCount = (int) data_get($post, 'metrics.comments', 0);
    $likesCount = (int) data_get($post, 'metrics.likes', 0);
    $bookmarksCount = (int) data_get($post, 'metrics.bookmarks', 0);
    $viewsCount = (int) data_get($post, 'views', 0);

    $viewerUsername = mb_strtolower(trim((string) ($viewerUsername ?? data_get(\App\Support\AccountStore::active(), 'username', ''))));
    $postOwnerUsername = mb_strtolower(trim((string) data_get($post, 'to.username', '')));
    $viewerOwnsPost = $postOwnerUsername !== '' && $viewerUsername !== '' && $postOwnerUsername === $viewerUsername;

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
    $showLane = $comment ? $laneText !== '' : $hasAnswer;

    $topPad = $sharedUpdate && ! $flushTop ? 'pt-3' : 'pt-2';
@endphp

@if (! \Native\Mobile\System::runningInJump())
<native:impression native:key="impression-{{ $postId }}" identity="{{ $postId }}" :enabled="$hasAnswer" on-impression="recordPostView('{{ $postId }}')" class="w-full">
@endif
<native:column native:key="post-{{ $postId }}" class="w-full {{ $bare ? '' : 'bg-theme-surface' }}">
    @unless ($sharedUpdate || $comment)
        <native:column class="w-full px-2 {{ $bare ? 'pt-2' : 'pt-3' }}">
            <native:column class="w-full gap-2 px-3 py-2.5 rounded-md bg-theme-bubble border border-theme-outline-variant/50">
                <native:row class="w-full items-start justify-between gap-3">
                    <native:column class="flex-1 min-w-0">
                        @if ($anonymous)
                            <native:row class="w-full items-center gap-3">
                                <x-avatar :anonymous="true" :size="8" />
                                <native:text class="text-sm font-medium text-theme-on-surface-variant">Anonymously</native:text>
                            </native:row>
                        @else
                            <x-post-author :author="data_get($post, 'from', [])" />
                        @endif
                    </native:column>
                    @if ($questionText !== '' || $pinned)
                        <native:row class="items-center gap-2 shrink-0">
                            @if ($questionText !== '')
                                <native:row class="items-center gap-1.5 px-2.5 py-1.5 rounded-full bg-theme-surface-variant">
                                    <x-post-action type="translate" :post-id="$postId" field="content" />
                                </native:row>
                            @endif
                            @if ($pinned)
                                <native:row class="items-center gap-1.5 px-2.5 py-1.5 rounded-full bg-theme-surface-variant">
                                    <native:icon :ios="\App\Icons\Ios::Pin" :android="\App\Icons\Android::PushPin" :size="16" color="#64748B" dark-color="#94A3B8" />
                                    {{-- Arbitrary font sizes use points; rem units are not supported. --}}
                                    <native:text class="text-[11.52] font-medium text-theme-on-surface-variant" max-lines="1">Pinned</native:text>
                                </native:row>
                            @endif
                        </native:row>
                    @endif
                </native:row>

                @if ($questionText !== '')
                    {{-- Numeric line-height classes are unsupported; leading-loose gives 28px at text-sm. --}}
                    <x-post-text :html="data_get($post, 'content')" :post-id="$postId" :question="true" />
                @endif
            </native:column>
        </native:column>
    @endunless

    @if ($showLane)
        <native:column class="w-full relative">
            <native:row class="w-full px-2">
                <native:column class="w-5 shrink-0" />
                {{-- Use measured content height; stretched siblings can affect post layout. --}}
                <native:column native:key="thread-rail" class="flex-1 min-w-0 pl-0.5 {{ $rail ? 'bg-theme-outline' : 'bg-theme-surface' }}">
                <native:column class="w-full gap-1.5 min-w-0 pl-[30] bg-theme-surface {{ $topPad }} {{ $contentBottomPad }}">
                    <native:row class="w-full items-center justify-between gap-2">
                        <native:pressable class="flex-1 min-w-0" @tap="openProfile('{{ data_get($laneAuthor, 'username', '') }}')" a11y-label="Open {{ '@'.data_get($laneAuthor, 'username', '') }}’s profile">
                            <native:row class="items-center gap-1.5">
                                <native:text class="text-sm font-medium text-theme-on-surface shrink-0" max-lines="1">{{ data_get($laneAuthor, 'name') }}</native:text>
                                @if (data_get($laneAuthor, 'verified'))
                                    <native:icon :ios="\App\Icons\Ios::CheckmarkSealFill" :android="\App\Icons\Android::Verified" :size="14" color="#EC4899" />
                                @endif
                                <native:text class="text-sm text-theme-on-surface-variant flex-1" max-lines="1">{{ '@'.data_get($laneAuthor, 'username') }}</native:text>
                            </native:row>
                        </native:pressable>
                        <native:row class="items-center gap-1.5 shrink-0">
                            @if ($comment)
                                <x-post-action type="translate" :post-id="$postId" field="content" />
                            @endif
                            @if (is_array($channel) && ($channel['name'] ?? '') !== '')
                                <native:row class="items-center gap-1 px-1.5 py-0.5 rounded-md bg-theme-surface-variant">
                                    <native:icon :ios="\App\Icons\Ios::Tag" :android="\App\Icons\AndroidOutlined::Sell" :size="12" color="#EC4899CC" />
                                    <native:text class="text-[11.52] font-medium text-theme-on-surface-variant" max-lines="1">{{ $channel['name'] }}</native:text>
                                </native:row>
                                <native:rect class="w-1 h-1 rounded-full bg-theme-outline" />
                            @endif
                        @if ($timestamp !== '')
                            <native:text class="text-xs text-theme-on-surface-variant shrink-0" max-lines="1">{{ $edited ? 'Edited: ' : '' }}{{ $timestamp }}</native:text>
                        @endif
                    </native:row>
                </native:row>
                <x-post-text :html="$comment && $answerText === '' ? data_get($post, 'content') : data_get($post, 'answer')" :post-id="$postId" :limit="! $comment && ! $full && ! $expanded && $answerLong ? $answerLimit : null" />

                @unless ($comment)
                @if ($answerLong && ! $full)
                    <x-show-more :post-id="$postId" :expanded="$expanded" />
                @endif

                <x-post-images :images="$images" />

                @if ($images === [] && is_array($preview) && ($preview['url'] ?? '') !== '')
                    <native:pressable @tap="openLink('{{ $preview['url'] }}')" a11y-label="Open link: {{ $preview['host'] ?? $preview['url'] }}">
                        @if (($preview['image'] ?? null) !== null && $preview['image'] !== '')
                            <native:column class="w-full gap-0 rounded-lg relative">
                                <x-lazy-image src="{{ $preview['image'] }}" alt="{{ $preview['title'] ?? $preview['url'] }}" class="w-full h-48 object-contain rounded-lg" />
                                <native:column class="w-full gap-0 px-2 py-1 rounded-b-lg bg-pink-800/55">
                                    <native:text class="text-xs font-semibold text-white" max-lines="1">{{ $preview['title'] ?? $preview['url'] }}</native:text>
                                </native:column>
                            </native:column>
                        @else
                            <native:column class="w-full items-center gap-2 px-3 py-4 rounded-lg bg-theme-bubble border border-theme-outline-variant/50">
                                <native:row class="w-full items-center gap-2">
                                    <native:icon :ios="\App\Icons\Ios::PlayCircle" :android="\App\Icons\AndroidOutlined::PlayCircle" :size="18" color="#EC4899" />
                                    <native:text class="flex-1 text-xs font-semibold text-theme-on-surface" max-lines="1">{{ $preview['host'] ?? $preview['url'] }}</native:text>
                                </native:row>
                                <native:text class="w-full text-xs text-theme-on-surface-variant" max-lines="1">{{ $preview['url'] }}</native:text>
                            </native:column>
                        @endif
                    </native:pressable>
                @endif

                @if (is_array($poll) && $poll !== [])
                    <x-post-poll :poll="$poll" :post-id="$postId" />
                @endif

                <native:row class="w-full items-center gap-3 pt-3">
                    <x-post-action type="comments" :post-id="$postId" :count="$commentsCount" />
                    <native:rect class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
                    <x-post-action type="like" :post-id="$postId" :count="$likesCount" :active="$liked" />
                    <native:rect class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
                    <x-post-action type="views" :count="$viewsCount" />
                    <native:rect class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
                    <x-post-action type="translate" :post-id="$postId" />
                    <native:rect class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
                    <x-post-action type="bookmark" :post-id="$postId" :count="$bookmarksCount" :active="$bookmarked" />
                    {{-- Use a native spacer because ml-auto is unreliable in this renderer. --}}
                    <native:spacer class="flex-1" />
                    <x-post-action type="share" :post-id="$postId" />
                    @if ($viewerOwnsPost)
                        <native:rect class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
                        <x-post-action type="menu" :post-id="$postId" />
                    @endif
                </native:row>
                @endunless
                </native:column>
                </native:column>
            </native:row>

            @if ($rail && ! $railTop)
                <native:rect class="absolute left-[28] top-0 w-0.5 {{ $topPad === 'pt-3' ? 'h-[52]' : 'h-[48]' }} bg-theme-surface" />
            @elseif ($railTop && ! $rail)
                <native:rect class="absolute left-[28] top-0 w-0.5 {{ $topPad === 'pt-3' ? 'h-3' : 'h-2' }} bg-theme-outline" />
            @endif

            <native:column native:key="post-avatar" class="absolute left-2 {{ $topPad === 'pt-3' ? 'top-3' : 'top-2' }} w-10 h-10">
                <x-avatar :user="$laneAuthor" />
            </native:column>
        </native:column>
    @endif

    @if ($moreLink !== null && $moreLink !== '')
        <x-post-divider :link="$moreLink" />
    @endif
    @unless ($bare)
        <native:rect native:key="post-separator" class="w-full h-px bg-theme-outline-variant" />
    @endunless
</native:column>
@if (! \Native\Mobile\System::runningInJump())
</native:impression>
@endif
