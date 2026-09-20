@props(['post', 'rail' => false, 'moreLink' => null, 'flushTop' => false, 'bare' => false, 'expanded' => false])

@php
    $anonymous = (bool) data_get($post, 'anonymously', false);
    $sharedUpdate = data_get($post, 'content') === '__UPDATE__'
        && data_get($post, 'from.id') !== null
        && data_get($post, 'from.id') === data_get($post, 'to.id');

    // The API returns parsed HTML (answers carry links, mentions, embeds).
    // Native text cannot render HTML, so display its plain text — the same
    // words the web shows, without the markup.
    $plainText = fn (mixed $html): string => trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));

    // Same clock as the web feed: short diffForHumans with an Edited prefix.
    $shortTime = function (?string $iso): string {
        if ($iso === null || $iso === '') {
            return '';
        }

        try {
            return \Illuminate\Support\Carbon::parse($iso)->diffForHumans(short: true);
        } catch (\Throwable) {
            return '';
        }
    };

    $questionText = $plainText(data_get($post, 'content'));
    $answerText = $plainText(data_get($post, 'answer'));
    $hasAnswer = $answerText !== '';
    $timestamp = $shortTime(data_get($post, 'updated_at'));
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

    // Long answers clamp like the web's showMore (600px) with a pink
    // Show more / Show less toggle; the question box always shows fully.
    $answerLimit = 600;
    $answerLong = mb_strlen($answerText) > $answerLimit;
    $answerShown = $expanded || ! $answerLong
        ? $answerText
        : mb_substr($answerText, 0, $answerLimit).'…';
    // Row-level vertical padding is zero by design: the avatar lane's
    // rail runs from below the avatar to the row's bottom edge, and the
    // next segment starts flush — so chained rails physically touch
    // instead of leaving seam gaps. Spacing lives inside the content
    // column (top pad mirrors on the lane to keep the avatar aligned
    // with the header row).
    $topPad = $flushTop ? 'pt-2' : ($sharedUpdate ? 'pt-3' : 'pt-2');
@endphp

{{-- Flat web-style post: full-width question box (asks only), then an
    avatar-lane row. Chained posts draw a rail under the avatar that runs
    to the row's bottom edge (items-stretch + flex-1, plain flow — no
    absolute overlays); the thread container owns the single full-bleed
    divider, so there are no boxes or borders per post. --}}
<native:column class="{{ $bare ? 'w-full' : 'w-full bg-theme-background border-b border-theme-outline-variant' }}">
    @unless ($sharedUpdate)
        <native:column class="w-full px-4 pt-3">
            <native:column class="w-full gap-2 px-3 py-2.5 rounded-md bg-theme-surface-variant border border-theme-outline-variant">
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
                    @if ($questionText !== '' && $questionText !== '__UPDATE__')
                        <x-post-action type="translate" :post-id="$postId" field="content" />
                    @endif
                </native:row>

                @if ($questionText !== '' && $questionText !== '__UPDATE__')
                    <native:text class="text-sm leading-relaxed text-theme-on-surface">{{ $questionText }}</native:text>
                @endif
            </native:column>
        </native:column>
    @endunless

    @if ($hasAnswer)
        <native:row class="w-full items-stretch gap-3 px-4">
            <native:column class="w-10 items-center shrink-0 {{ $topPad }}">
                <x-avatar :user="data_get($post, 'to', [])" />
                @if ($rail)
                    <native:column class="flex-1 w-0.5 bg-theme-outline" />
                @endif
            </native:column>
            <native:column class="flex-1 gap-1.5 min-w-0 {{ $topPad }} pb-3">
                <native:row class="w-full items-center justify-between gap-2">
                    <native:pressable class="flex-1 min-w-0" @tap="openProfile('{{ data_get($post, 'to.username', '') }}')" a11y-label="Open {{ '@'.data_get($post, 'to.username', '') }}’s profile">
                        <native:row class="items-center gap-1.5">
                            <native:text class="text-sm font-medium text-theme-on-surface shrink-0">{{ data_get($post, 'to.name') }}</native:text>
                            @if (data_get($post, 'to.verified') || data_get($post, 'to.company_verified'))
                                <native:icon :ios="\App\Icons\Ios::CheckmarkSealFill" :android="\App\Icons\Android::Verified" :size="14" color="#EC4899" />
                            @endif
                            <native:text class="text-sm text-theme-on-surface-variant truncate">{{ '@'.data_get($post, 'to.username') }}</native:text>
                        </native:row>
                    </native:pressable>
                    <native:row class="items-center gap-1.5 shrink-0">
                        @if (is_array($channel) && ($channel['name'] ?? '') !== '')
                            <native:row class="items-center gap-1 px-1.5 py-0.5 rounded-md bg-theme-surface-variant border border-theme-outline-variant">
                                <native:icon :ios="\App\Icons\Ios::Tag" :android="\App\Icons\AndroidOutlined::Sell" :size="12" color="#EC4899" />
                                <native:text class="text-xs text-theme-on-surface-variant">{{ $channel['name'] }}</native:text>
                            </native:row>
                            <native:column class="w-1 h-1 rounded-full bg-theme-outline" />
                        @endif
                        @if ($timestamp !== '')
                            <native:text class="text-xs text-theme-on-surface-variant">{{ $edited ? 'Edited: ' : '' }}{{ $timestamp }}</native:text>
                        @endif
                    </native:row>
                </native:row>
                <native:pressable @tap="openDetail('{{ $postId }}')" a11y-label="Open post">
                    <native:text class="text-base leading-relaxed text-theme-on-surface">{{ $answerShown }}</native:text>
                </native:pressable>

                @if ($answerLong)
                    <x-show-more :post-id="$postId" :expanded="$expanded" />
                @endif

                @foreach ($images as $src)
                    {{-- Fullscreen viewing via the existing in-app browser:
                        openLink only reaches http(s) URLs (guarded in the
                        shared trait), so a tap opens the image full-size
                        instead of leaving it a fixed h-48 stack. --}}
                    <native:pressable @tap="openLink('{{ $src }}')" a11y-label="Open image fullscreen">
                        <native:image src="{{ $src }}" alt="Post image" class="w-full h-48 object-cover rounded-md border border-theme-outline-variant" />
                    </native:pressable>
                @endforeach

                @if (is_array($preview) && ($preview['url'] ?? '') !== '')
                    <native:pressable @tap="openLink('{{ $preview['url'] }}')" a11y-label="Open link: {{ $preview['host'] ?? $preview['url'] }}">
                        <native:column class="w-full gap-0 rounded-md border border-theme-outline-variant overflow-hidden">
                            @if (($preview['image'] ?? null) !== null && $preview['image'] !== '')
                                <native:image src="{{ $preview['image'] }}" alt="{{ $preview['title'] ?? $preview['url'] }}" class="w-full h-36 object-cover" />
                            @endif
                            <native:column class="w-full gap-0.5 px-3 py-2 bg-theme-bubble">
                                <native:text class="text-xs font-semibold text-theme-on-surface">{{ $preview['title'] ?? $preview['url'] }}</native:text>
                                <native:text class="text-xs text-theme-on-surface-variant">{{ $preview['host'] ?? '' }}</native:text>
                            </native:column>
                        </native:column>
                    </native:pressable>
                @endif

                @if (is_array($poll) && $poll !== [])
                    <x-post-poll :poll="$poll" :post-id="$postId" />
                @endif

                <native:row class="w-full items-center gap-3 pt-1">
                    <x-post-action type="comments" :post-id="$postId" :count="$commentsCount" />
                    <native:column class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
                    <x-post-action type="like" :post-id="$postId" :count="$likesCount" :active="$liked" />
                    @if ($viewsCount > 0)
                        <native:column class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
                        <x-post-action type="views" :count="$viewsCount" />
                    @endif
                    <native:column class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
                    <x-post-action type="translate" :post-id="$postId" />
                    <native:column class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
                    <x-post-action type="bookmark" :post-id="$postId" :count="$bookmarksCount" :active="$bookmarked" />
                    {{-- Spacer pushes share to the trailing edge: ml-auto is
                        unreliable on the renderers, so this uses the same
                        native:spacer flex-1 pattern as post-poll-option. --}}
                    <native:spacer class="flex-1" />
                    <x-post-action type="share" :post-id="$postId" />
                </native:row>

                @if ($moreLink !== null && $moreLink !== '')
                    <x-post-divider :link="$moreLink" />
                @endif
            </native:column>
        </native:row>
    @endif
</native:column>
