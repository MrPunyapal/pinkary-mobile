@props(['post', 'inThread' => false, 'bare' => false])

@php
    $anonymous = (bool) data_get($post, 'anonymously', false);
    $sharedUpdate = data_get($post, 'content') === '__UPDATE__'
        && data_get($post, 'from.id') !== null
        && data_get($post, 'from.id') === data_get($post, 'to.id');

    // The API returns parsed HTML (answers carry links, mentions, embeds).
    // Native text cannot render HTML, so display its plain text — the same
    // words the web shows, without the markup.
    $plainText = fn (mixed $html): string => trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8'));

    // Mirrors web resources/js/abbreviate.js exactly (toFixed(1) keeps "1.0K").
    $abbreviate = function (mixed $value): string {
        $number = (int) $value;

        if ($number < 1000) {
            return (string) $number;
        }

        $symbols = ['', 'K', 'M', 'B', 'T'];
        $tier = (int) (log10(abs($number)) / 3);

        if ($tier <= 0) {
            return (string) $number;
        }

        return number_format($number / (10 ** ($tier * 3)), 1).$symbols[$tier];
    };

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

    // Bare posts render inside a thread-post card (like the web's x-thread):
    // no padding or border of their own — the card owns the frame and the
    // rail bridges connect the avatars into one continuous line.
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
                            <native:column class="w-8 h-8 items-center justify-center rounded-full border border-theme-outline">
                                <native:text class="text-sm text-theme-on-surface-variant">?</native:text>
                            </native:column>
                            <native:text class="text-sm font-medium text-theme-on-surface-variant">Anonymously</native:text>
                        </native:row>
                    @else
                        <x-post-author :author="data_get($post, 'from', [])" />
                    @endif
                </native:column>
                @if ($questionText !== '' && $questionText !== '__UPDATE__')
                    <native:pressable class="py-1 shrink-0" @tap="translatePost('{{ $postId }}', 'content')" a11y-label="Translate question">
                        <native:icon :ios="\App\Icons\Ios::Globe" :android="\App\Icons\AndroidOutlined::Language" :size="16" color="#64748B" dark-color="#94A3B8" />
                    </native:pressable>
                @endif
            </native:row>

            @if ($questionText !== '' && $questionText !== '__UPDATE__' && ! $anonymous)
                <native:text class="text-sm leading-relaxed text-theme-on-surface">{{ $questionText }}</native:text>
            @endif
        </native:column>
    @endunless

    @if ($hasAnswer)
        <native:row class="w-full gap-3">
            <native:column class="items-center gap-0 shrink-0">
                <native:pressable @tap="openProfile('{{ data_get($post, 'to.username', '') }}')" a11y-label="Open {{ '@'.data_get($post, 'to.username', '') }}’s profile">
                    @if (data_get($post, 'to.avatar'))
                        <native:image src="{{ data_get($post, 'to.avatar') }}" alt="{{ data_get($post, 'to.username', '') }}" class="w-10 h-10 object-cover {{ data_get($post, 'to.company_verified') ? 'rounded-2xl' : 'rounded-full' }} border border-theme-outline" />
                    @else
                        <native:column class="w-10 h-10 items-center justify-center {{ data_get($post, 'to.company_verified') ? 'rounded-2xl' : 'rounded-full' }} bg-theme-avatar border border-theme-outline">
                            <native:text class="text-sm text-theme-on-avatar">{{ strtoupper(substr((string) data_get($post, 'to.name', '?'), 0, 1)) }}</native:text>
                        </native:column>
                    @endif
                </native:pressable>
                @if ($inThread)
                    <native:column class="w-0.5 flex-1 min-h-4 bg-theme-outline" />
                @endif
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

                <native:pressable @tap="openDetail('{{ $postId }}')" a11y-label="Open post">
                    <native:text class="text-base leading-relaxed text-theme-on-surface">{{ $answerText }}</native:text>
                </native:pressable>

                @foreach ($images as $src)
                    <native:image src="{{ $src }}" alt="Post image" class="w-full h-48 object-cover rounded-md border border-theme-outline-variant" />
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
            </native:column>
        </native:row>

        <native:row class="w-full items-center gap-3">
            <native:pressable @tap="openDetail('{{ $postId }}')" a11y-label="Open comments">
                <native:row class="items-center gap-1.5">
                    <native:icon :ios="\App\Icons\Ios::BubbleLeftAndBubbleRight" :android="\App\Icons\AndroidOutlined::ChatBubbleOutline" :size="16" color="#64748B" dark-color="#94A3B8" />
                    @if ($commentsCount > 0)
                        <native:text class="text-xs text-theme-on-surface-variant">{{ $abbreviate($commentsCount) }}</native:text>
                    @endif
                </native:row>
            </native:pressable>
            <native:column class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
            <native:pressable @tap="toggleLike('{{ $postId }}')" a11y-label="{{ $liked ? 'Unlike this post' : 'Like this post' }}">
                <native:row class="items-center gap-1.5">
                    @if ($liked)
                        <native:icon :ios="\App\Icons\Ios::HeartFill" :android="\App\Icons\Android::Favorite" :size="16" color="#EC4899" />
                    @else
                        <native:icon :ios="\App\Icons\Ios::Heart" :android="\App\Icons\AndroidOutlined::FavoriteBorder" :size="16" color="#64748B" dark-color="#94A3B8" />
                    @endif
                    @if ($likesCount > 0)
                        <native:text class="text-xs {{ $liked ? 'text-theme-primary' : 'text-theme-on-surface-variant' }}">{{ $abbreviate($likesCount) }}</native:text>
                    @endif
                </native:row>
            </native:pressable>
            @if ($viewsCount > 0)
                <native:column class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
                <native:row class="items-center gap-1.5">
                    <native:icon :ios="\App\Icons\Ios::ChartBar" :android="\App\Icons\Android::BarChart" :size="16" color="#64748B" dark-color="#94A3B8" />
                    <native:text class="text-xs text-theme-on-surface-variant">{{ $abbreviate($viewsCount) }}</native:text>
                </native:row>
            @endif
            <native:column class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
            <native:pressable @tap="translatePost('{{ $postId }}', 'answer')" a11y-label="Translate answer">
                <native:icon :ios="\App\Icons\Ios::Globe" :android="\App\Icons\AndroidOutlined::Language" :size="16" color="#64748B" dark-color="#94A3B8" />
            </native:pressable>
            <native:column class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
            <native:pressable @tap="toggleBookmark('{{ $postId }}')" a11y-label="{{ $bookmarked ? 'Remove bookmark' : 'Bookmark this post' }}">
                <native:row class="items-center gap-1.5">
                    @if ($bookmarked)
                        <native:icon :ios="\App\Icons\Ios::BookmarkFill" :android="\App\Icons\Android::Bookmark" :size="16" color="#EC4899" />
                    @else
                        <native:icon :ios="\App\Icons\Ios::Bookmark" :android="\App\Icons\AndroidOutlined::Bookmark" :size="16" color="#64748B" dark-color="#94A3B8" />
                    @endif
                    @if ($bookmarksCount > 0)
                        <native:text class="text-xs text-theme-on-surface-variant">{{ $abbreviate($bookmarksCount) }}</native:text>
                    @endif
                </native:row>
            </native:pressable>
            <native:pressable class="ml-auto" @tap="sharePost('{{ $postId }}')" a11y-label="Share this post">
                <native:icon :ios="\App\Icons\Ios::Paperplane" :android="\App\Icons\AndroidOutlined::Send" :size="16" color="#64748B" dark-color="#94A3B8" />
            </native:pressable>
        </native:row>
    @endif
</native:column>
