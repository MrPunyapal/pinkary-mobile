@props(['post', 'replyTo' => null, 'bare' => false, 'expanded' => false])

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

    // Continuation marker for chained thread posts ("Replying to @user",
    // the super-native tweet-detail pattern). Usernames may arrive with
    // or without the @ prefix; normalize so the affordance always reads
    // the same.
    $replyToName = is_string($replyTo) ? trim($replyTo) : '';
    $replyToHandle = $replyToName !== '' ? '@'.ltrim($replyToName, '@') : '';

    // Bare posts render inside a thread-post card (like the web's x-thread):
    // no padding or border of their own — the card owns the frame and
    // separates chained posts with its own borders, so there is no drawn
    // rail that must stitch across posts on old Jump apps.
    $frameClass = $bare
        ? 'w-full gap-3'
        : 'w-full gap-3 px-4 py-3 bg-theme-background border-b border-theme-outline-variant';
@endphp

<native:column class="{{ $frameClass }}">
    @unless ($sharedUpdate)
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
    @endunless

    @if ($hasAnswer)
        {{-- Plain avatar + body row (the super-native feed pattern). The
            avatar column carries no rail: thread continuity comes from the
            card's own borders and the "Replying to" affordance below, so
            there is no stretch lane or overlay that old Jump apps can
            drop, leaving gaps. --}}
        <native:row class="w-full items-start gap-3">
            <native:column class="w-10 items-center shrink-0">
                <x-avatar :user="data_get($post, 'to', [])" />
            </native:column>
            <native:column class="flex-1 gap-1.5 min-w-0">
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
                @if ($replyToHandle !== '')
                    <native:row class="w-full items-center gap-1">
                        <native:text class="text-xs text-theme-on-surface-variant">Replying to</native:text>
                        <native:text class="text-xs font-medium text-theme-primary">{{ $replyToHandle }}</native:text>
                    </native:row>
                @endif
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
            </native:column>
        </native:row>
    @endif
</native:column>
