@props(['post', 'rail' => false, 'railTop' => false, 'moreLink' => null, 'flushTop' => false, 'bare' => false, 'expanded' => false, 'comment' => false, 'viewerUsername' => null])

@php
    $anonymous = (bool) data_get($post, 'anonymously', false);
    // A shared update (you answered your own question) carries
    // `is_update` and no asked question. The API layer normalizes the
    // legacy `__UPDATE__` marker into this flag, so no screen ever
    // renders the sentinel as text.
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

    // The ••• menu is owner-only on the web (auth()->check() &&
    // auth()->user()->can('update', $question), questions/show:359) — its
    // every entry is a question action, so guests and non-owners get a
    // dead ellipsis. The recipient (`to`) owns an asked question, and this
    // component receives only `$post`, so the same lowercase-trimmed
    // username comparison as viewerOwnsPost() in
    // App\NativeComponents\Concerns\TogglesPostReactions decides it here.
    $viewerUsername = mb_strtolower(trim((string) ($viewerUsername ?? data_get(\App\Support\AccountStore::active(), 'username', ''))));
    $postOwnerUsername = mb_strtolower(trim((string) data_get($post, 'to.username', '')));
    $viewerOwnsPost = $postOwnerUsername !== '' && $viewerUsername !== '' && $postOwnerUsername === $viewerUsername;

    // Long answers clamp like the web's showMore with a pink Show more /
    // Show less toggle; the question box always shows fully. The web
    // measures a real pixel height (maxHeight 600, toggle only past 640px
    // — resources/js/show-more.js) and native has nothing to measure, so
    // that budget is converted to characters — 24px per line, 36 characters
    // per line. 640px /
    // 24px = 26.7 lines, so the toggle appears at 27 lines x 36
    // characters = 972. (The old 600-character limit was roughly 16 lines,
    // which almost no real answer ever reached.)
    $answerLimit = 972;
    $answerLong = mb_strlen($answerText) > $answerLimit;
    $answerShown = $expanded || ! $answerLong
        ? $answerText
        : mb_substr($answerText, 0, $answerLimit).'…';
    $hasMoreLink = $moreLink !== null && $moreLink !== '';
    $contentBottomPad = $hasMoreLink ? 'pb-0.5' : ($bare ? 'pb-1' : 'pb-3');

    // Comments never carry an asked question: the asker box is skipped
    // and the comment renders as a plain avatar-lane row authored by
    // `from` — no action row, like the web.
    //
    // A comment's text is its *answer*, not its content: the web's reply
    // composer rewrites the payload to the __UPDATE__ sentinel and moves the
    // words into the answer (pinkary/app/Livewire/Questions/Create.php:471-475),
    // and the API now stores comments the same way. The content fallback is
    // only for rows written before that.
    $laneAuthor = $comment ? data_get($post, 'from', []) : data_get($post, 'to', []);
    $laneText = $comment ? ($answerText !== '' ? $answerText : $questionText) : $answerShown;
    $showLane = $comment ? $laneText !== '' : $hasAnswer;

    $topPad = $sharedUpdate && ! $flushTop ? 'pt-3' : 'pt-2';
@endphp

<native:column native:key="post-{{ $postId }}" class="{{ $bare ? 'w-full' : 'w-full bg-theme-surface border-b border-theme-outline-variant' }}">
    @unless ($sharedUpdate || $comment)
        {{-- Chained thread segments stack tight (bare): smaller top pad
            so the gap between two questions stays compact. px-2 is the
            web's per-thread inset (livewire/feed.blade.php:6 — border-b
            px-2 py-2), which puts the answer avatar 8px from the column
            edge; the old px-4 floated it 16px in. --}}
        <native:column class="w-full px-2 {{ $bare ? 'pt-2' : 'pt-3' }}">
            {{-- The asked-question card is the web's `bg-slate-50/70` over
                white (pinkary/resources/views/livewire/questions/show.blade.php:18),
                which is exactly the `bubble` token (config/native-ui.php, commented
                "asked-question card (slate-50 on web)"). It must stay LIGHTER than
                the Translate/Pinned chips below, which are `bg-theme-surface-variant`
                (slate-100) — the web has the same relationship, chip darker than card.
                Using surface-variant for both made the chips disappear. --}}
            <native:column class="w-full gap-2 px-3 py-2.5 rounded-md bg-theme-bubble border border-theme-outline-variant/50">
                <native:row class="w-full items-start justify-between gap-3">
                    <native:column class="flex-1 min-w-0">
                        @if ($anonymous)
                            <native:row class="w-full items-center gap-3">
                                {{-- The web's anonymous ring is genuinely dashed
                                    (livewire/questions/show.blade.php:25) and
                                    border-dashed does NOT parse in this renderer
                                    (TailwindParser drops it), so it silently
                                    renders SOLID here. The fix belongs in
                                    components/avatar.blade.php:37 (owned by
                                    another stream) — this call site only
                                    records the deviation. --}}
                                <x-avatar :anonymous="true" :size="8" />
                                <native:text class="text-sm font-medium text-theme-on-surface-variant">Anonymously</native:text>
                            </native:row>
                        @else
                            <x-post-author :author="data_get($post, 'from', [])" />
                        @endif
                    </native:column>
                    {{-- Web parity (questions/show:36-52): Translate and
                        Pinned are grey pill chips sharing one trailing
                        header group, not a bare icon and a pink label under
                        the text. The web hides the Translate word on phones
                        (hidden sm:inline) so only the icon shows here, and
                        its pin inherits currentColor (slate-500) instead of
                        pink. The Pinned chip is kept on every surface: the
                        web only shows it on the question page header
                        (questions/index.blade.php:8 passes inIndex, which
                        suppresses it) but this one component serves the
                        feed and the detail screen alike. --}}
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
                                    {{-- 0.72rem x 16px = 11.52pt. Do NOT write
                                        `text-[0.72rem]`: this renderer drops
                                        the rem unit and would set a 0.72pt
                                        font. --}}
                                    <native:text class="text-[11.52] font-medium text-theme-on-surface-variant" max-lines="1">Pinned</native:text>
                                </native:row>
                            @endif
                        </native:row>
                    @endif
                </native:row>

                @if ($questionText !== '')
                    {{-- Web parity (questions/show:65): text-sm leading-7 on
                        slate-700 / slate-200. leading-7 does not parse in
                        this renderer; leading-loose (2.0 x 14px) is exactly
                        the 28px the web asks for. --}}
                    <x-post-text :html="data_get($post, 'content')" :post-id="$postId" :question="true" />
                @endif
            </native:column>
        </native:column>
    @endunless

    @if ($showLane)
        {{-- px-2 matches the web's per-thread inset (livewire/feed.blade.php:6)
            and the asker box above, so the avatar lane hugs the column edge. --}}
        <native:column class="w-full relative">
            <native:row class="w-full px-2">
                <native:column class="w-5 shrink-0" />
                {{-- The 2px background gutter inherits the content's measured height.
                    Unlike a stretched sibling, it cannot enlarge or collapse a post. --}}
                <native:column native:key="thread-rail" class="flex-1 min-w-0 pl-0.5 {{ $rail ? 'bg-theme-outline' : 'bg-theme-surface' }}">
                <native:column class="w-full gap-1.5 min-w-0 pl-[30] bg-theme-surface {{ $topPad }} {{ $contentBottomPad }}">
                    <native:row class="w-full items-center justify-between gap-2">
                        <native:pressable class="flex-1 min-w-0" @tap="openProfile('{{ data_get($laneAuthor, 'username', '') }}')" a11y-label="Open {{ '@'.data_get($laneAuthor, 'username', '') }}’s profile">
                            {{-- Web parity (questions/show): the name never wraps
                                and the handle truncates with … — the channel pill
                                and timestamp on the right always stay visible. --}}
                            <native:row class="items-center gap-1.5">
                                <native:text class="text-sm font-medium text-theme-on-surface shrink-0" max-lines="1">{{ data_get($laneAuthor, 'name') }}</native:text>
                                {{-- Web parity (questions/show:124-133): the seal
                                    is gated on is_verified alone — a company
                                    account only swaps the badge art, it never
                                    earns one. --}}
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
                                {{-- Web parity (components/channel-badge.blade.php:5,9):
                                    no border, medium weight at 0.72rem, and a
                                    dimmed tag icon — the pink is a 80%-alpha
                                    accent, not a solid mark. --}}
                                <native:row class="items-center gap-1 px-1.5 py-0.5 rounded-md bg-theme-surface-variant">
                                    <native:icon :ios="\App\Icons\Ios::Tag" :android="\App\Icons\AndroidOutlined::Sell" :size="12" color="#EC4899CC" />
                                    {{-- 0.72rem x 16px = 11.52pt — see the note
                                        on the Pinned chip about rem units. --}}
                                    <native:text class="text-[11.52] font-medium text-theme-on-surface-variant" max-lines="1">{{ $channel['name'] }}</native:text>
                                </native:row>
                                <native:rect class="w-1 h-1 rounded-full bg-theme-outline" />
                            @endif
                        @if ($timestamp !== '')
                            <native:text class="text-xs text-theme-on-surface-variant shrink-0" max-lines="1">{{ $edited ? 'Edited: ' : '' }}{{ $timestamp }}</native:text>
                        @endif
                    </native:row>
                </native:row>
                <x-post-text :html="$comment && $answerText === '' ? data_get($post, 'content') : data_get($post, 'answer')" :post-id="$postId" :limit="! $comment && ! $expanded && $answerLong ? $answerLimit : null" />

                @unless ($comment)
                @if ($answerLong)
                    <x-show-more :post-id="$postId" :expanded="$expanded" />
                @endif

                @foreach ($images as $src)
                    {{-- Fullscreen viewing via the existing in-app browser:
                        openLink only reaches http(s) URLs (guarded in the
                        shared trait), so a tap opens the image full-size
                        instead of leaving it a fixed h-48 stack.
                        Web parity (resources/css/app.css:46-52): w-full
                        rounded-xl, no border, natural height. KNOWN
                        DEVIATION: h-48 crops to a 16:9-ish box because a
                        native image needs an explicit height before it
                        loads (there is no intrinsic ratio to lay out
                        against); the web lets the image's own height win. --}}
                    <native:pressable @tap="openLink('{{ $src }}')" a11y-label="Open image fullscreen">
                        <x-lazy-image src="{{ $src }}" alt="Post image" class="w-full h-48 object-cover rounded-xl" />
                    </native:pressable>
                @endforeach

                @if (is_array($preview) && ($preview['url'] ?? '') !== '')
                    {{-- Web parity (components/link-preview-card.blade.php:14-21):
                        rounded-lg, NO border, and the title as a bottom
                        overlay on pink-800/55 — the host lives in the a11y
                        label only, exactly as the web hides it in the card's
                        title attribute. Two renderer adaptations: the
                        overlay tint is a `bg-pink-800/55` class rather than
                        an inline style (this renderer drops the `style`
                        attribute — the same is true of the poll bar's
                        style="width: N%"), and it parses to the identical
                        #8C9D174D; the image carries its own radius and the
                        overlay rounds only its bottom corners because
                        overflow-hidden does not parse, so the parent's
                        radius cannot clip its children. --}}
                    <native:pressable @tap="openLink('{{ $preview['url'] }}')" a11y-label="Open link: {{ $preview['host'] ?? $preview['url'] }}">
                        @if (($preview['image'] ?? null) !== null && $preview['image'] !== '')
                            <native:column class="w-full gap-0 rounded-lg relative">
                                <x-lazy-image src="{{ $preview['image'] }}" alt="{{ $preview['title'] ?? $preview['url'] }}" class="w-full h-36 object-cover rounded-lg" />
                                <native:column class="absolute right-0 bottom-0 left-0 w-full gap-0 px-2 py-1 rounded-b-lg bg-pink-800/55">
                                    <native:text class="text-xs font-semibold text-white" max-lines="1">{{ $preview['title'] ?? $preview['url'] }}</native:text>
                                </native:column>
                            </native:column>
                        @else
                            {{-- An EMBED preview (YouTube, Vimeo — the web's
                                `html` branch paints the provider's own
                                iframe, livewire/questions/show.blade.php:7-10).
                                Native cannot render an iframe, and the API
                                only reports a card's <img>/<h3>, so an embed
                                arrives with image: null and its title
                                falling back to the raw URL.

                                So this is a "watch it over there" card: the
                                host, a play glyph that says what it is, and
                                the URL — all tappable to open the browser.
                                A bare title-only strip read as a broken
                                preview. --}}
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

                {{-- pt-3 is the web's mt-3 below the answer (questions/show:188). --}}
                <native:row class="w-full items-center gap-3 pt-3">
                    <x-post-action type="comments" :post-id="$postId" :count="$commentsCount" />
                    <native:rect class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
                    <x-post-action type="like" :post-id="$postId" :count="$likesCount" :active="$liked" />
                    {{-- Web parity (questions/show:237-245): the chart icon is
                        always drawn and only the COUNT is conditional —
                        post-action already drops a zero count, so the action
                        stays visible at 0 views instead of the whole row
                        vanishing. --}}
                    <native:rect class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
                    <x-post-action type="views" :count="$viewsCount" />
                    <native:rect class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
                    <x-post-action type="translate" :post-id="$postId" />
                    <native:rect class="w-1 h-1 rounded-full bg-theme-outline shrink-0" />
                    <x-post-action type="bookmark" :post-id="$postId" :count="$bookmarksCount" :active="$bookmarked" />
                    {{-- Spacer pushes share to the trailing edge: ml-auto is
                        unreliable on the renderers, so this uses the same
                        native:spacer flex-1 pattern as post-poll-option. --}}
                    <native:spacer class="flex-1" />
                    <x-post-action type="share" :post-id="$postId" />
                    {{-- Web parity (questions/show:359): the ••• menu is
                        owner-only — every entry is a question action, so a
                        guest or non-owner gets a dead ellipsis. --}}
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

    {{-- Thread "View more comments" divider: a full lane-aligned row of
        its own (like the web), not indented inside the content column. --}}
    @if ($moreLink !== null && $moreLink !== '')
        <x-post-divider :link="$moreLink" />
    @endif
</native:column>
