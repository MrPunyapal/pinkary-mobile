@php
    $isMe = (bool) data_get($profile, 'is_me', false);
    $followed = (bool) data_get($profile, 'followed_by_me', false);
    $company = (bool) data_get($profile, 'verification.company', false);
    $verified = (bool) data_get($profile, 'verification.profile', false);

    // Same abbreviation the web's stat pills use (toFixed(1): "1.0K").
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

    $followers = (int) data_get($profile, 'stats.followers', 0);
    $following = (int) data_get($profile, 'stats.following', 0);
    $posts = (int) data_get($profile, 'stats.posts', 0);
    $views = (int) data_get($profile, 'stats.views', 0);
    $links = array_values(array_filter(
        (array) data_get($profile, 'links', []),
        fn (mixed $link): bool => is_array($link) && ($link['url'] ?? '') !== ''
    ));
@endphp

<native:list on-end-reached="loadMoreQuestions" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        @if ($username !== '')
            <x-page-heading :title="'@'.(data_get($profile, 'username', $username) ?: $username)" :back="true" />
        @else
            <x-page-heading title="Profile" />
        @endif

        <native:column class="w-full px-4 py-4 gap-4">
        @if ($loading)
            <native:column class="w-full items-center gap-3 py-10">
                <native:activity-indicator />
                <native:text class="text-sm text-theme-on-surface-variant">Loading this profile…</native:text>
            </native:column>
        @elseif ($errorMessage !== '')
            <native:column class="w-full p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry">
                    <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                </native:pressable>
            </native:column>
        @else
            <native:column class="w-full items-center gap-0 p-4 rounded-md bg-theme-surface border border-theme-outline-variant">
                <x-avatar :user="$profile" :size="20" company-radius="3xl" :link="false" />

                <native:row class="w-full items-center justify-center gap-1.5 mt-4">
                    <native:text class="text-2xl font-semibold text-theme-on-surface">{{ data_get($profile, 'name') }}</native:text>
                    @if ($verified || $company)
                        <native:icon :ios="\App\Icons\Ios::CheckmarkSealFill" :android="\App\Icons\Android::Verified" :size="24" color="#EC4899" />
                    @endif
                </native:row>

                <native:row class="items-center px-3 py-1 mt-2 rounded-full bg-theme-surface-variant border border-theme-outline-variant">
                    <native:text class="text-sm text-theme-on-surface-variant">{{ '@'.data_get($profile, 'username') }}</native:text>
                </native:row>

                @if (data_get($profile, 'bio'))
                    <native:text class="text-sm text-center leading-relaxed text-theme-on-surface mt-3">{{ data_get($profile, 'bio') }}</native:text>
                @elseif ($isMe)
                    <native:text class="text-sm text-center text-theme-on-surface-variant mt-3">Tell people about yourself</native:text>
                @endif

                <native:row class="items-center justify-center gap-2 mt-4 flex-wrap">
                    <native:pressable @tap="openFollowers" a11y-label="Open followers">
                        <native:row class="items-center px-3 py-1 rounded-full bg-theme-surface-variant border border-theme-outline-variant">
                            <native:text class="text-sm text-theme-on-surface-variant">{{ $abbreviate($followers) }} {{ $followers === 1 ? 'Follower' : 'Followers' }}</native:text>
                        </native:row>
                    </native:pressable>
                    <native:pressable @tap="openFollowing" a11y-label="Open following">
                        <native:row class="items-center px-3 py-1 rounded-full bg-theme-surface-variant border border-theme-outline-variant">
                            <native:text class="text-sm text-theme-on-surface-variant">{{ $abbreviate($following) }} Following</native:text>
                        </native:row>
                    </native:pressable>
                    @if ($posts > 0)
                        <native:row class="items-center px-3 py-1 rounded-full bg-theme-surface-variant border border-theme-outline-variant">
                            <native:text class="text-sm text-theme-on-surface-variant">{{ $abbreviate($posts) }} {{ $posts === 1 ? 'Post' : 'Posts' }}</native:text>
                        </native:row>
                    @endif
                    <native:row class="items-center px-3 py-1 rounded-full bg-theme-surface-variant border border-theme-outline-variant">
                        <native:text class="text-sm text-theme-on-surface-variant">{{ $abbreviate($views) }} {{ $views === 1 ? 'View' : 'Views' }}</native:text>
                    </native:row>
                </native:row>

                @if (count($links) > 0)
                    <native:column class="w-full gap-2 mt-4 pt-4 border-t border-theme-outline-variant">
                        @foreach ($links as $link)
                            {{-- Solid theme color: profile gradients do not
                                render on native clients, so the card's
                                gradient/link_shape stay web-only. --}}
                            <native:pressable class="w-full items-center py-3 rounded-full bg-theme-primary" @tap="openUserLink({{ (int) data_get($link, 'id', 0) }})" a11y-label="Open {{ data_get($link, 'description', 'link') }}">
                                <native:text class="text-sm font-bold text-white">{{ data_get($link, 'description', '') }}</native:text>
                                @php $clicks = data_get($link, 'click_count'); @endphp
                                @if (is_int($clicks) || (is_string($clicks) && $clicks !== ''))
                                    <native:text class="text-xs text-white/80">{{ $clicks }} {{ (int) $clicks === 1 ? 'click' : 'clicks' }}</native:text>
                                @endif
                            </native:pressable>
                        @endforeach
                    </native:column>
                @elseif ($isMe)
                    <native:column class="w-full items-center gap-2 p-5 mt-4 rounded-md border border-dashed border-theme-outline">
                        <native:text class="text-lg font-medium text-theme-on-surface">No links yet.</native:text>
                        <native:text class="text-sm text-center text-theme-on-surface-variant">Add your first link to complete the profile card.</native:text>
                    </native:column>
                @endif
                {{-- Web parity (links/index): owners manage links inline from
                    their own card — email/logout live in Settings instead. --}}
                @if ($isMe)
                    <native:pressable class="self-center py-1" @tap="editProfile" a11y-label="Edit links">
                        <native:text class="text-sm font-medium text-theme-primary">Edit links</native:text>
                    </native:pressable>
                @endif
            </native:column>

            {{-- In-flow action row (no absolute positioning — older Jump
                apps render absolute nodes in-flow, overlapping content). --}}
            <native:row class="w-full items-center gap-2">
                <native:pressable class="w-11 h-11 items-center justify-center rounded-md bg-theme-surface border border-theme-outline-variant" @tap="shareProfile" a11y-label="Share profile">
                    <native:icon :ios="\App\Icons\Ios::SquareAndArrowUp" :android="\App\Icons\AndroidOutlined::Share" :size="20" color="#94A3B8" />
                </native:pressable>
                @if ($isMe)
                    <native:pressable class="w-11 h-11 items-center justify-center rounded-md bg-theme-surface border border-theme-outline-variant" @tap="toggleQr" a11y-label="Show profile QR code">
                        <native:icon :ios="\App\Icons\Ios::Qrcode" :android="\App\Icons\AndroidOutlined::QrCode" :size="20" color="#94A3B8" />
                    </native:pressable>
                @endif
                @unless ($isMe)
                    <native:pressable class="px-4 py-2 rounded-full border border-theme-primary" @tap="askUser" a11y-label="Ask {{ '@'.data_get($profile, 'username') }}">
                        <native:text class="text-sm font-medium text-theme-primary">Ask {{ '@'.data_get($profile, 'username') }}</native:text>
                    </native:pressable>
                    <native:spacer class="flex-1" />
                    <native:pressable class="px-4 py-2 rounded-full bg-theme-primary {{ $followBusy ? 'opacity-40' : '' }}" @tap="toggleFollow" a11y-label="{{ $followed ? 'Unfollow' : 'Follow' }}">
                        <native:text class="text-sm font-medium text-white">{{ $followBusy ? '…' : ($followed ? 'Following' : 'Follow') }}</native:text>
                    </native:pressable>
                @endunless
                @if ($isMe)
                    <native:pressable class="px-4 py-2 rounded-full border border-theme-outline-variant bg-theme-surface" @tap="editProfile" a11y-label="Edit profile">
                        <native:text class="text-sm font-medium text-theme-on-surface">Edit profile</native:text>
                    </native:pressable>
                @endif
            </native:row>

            @if (($showQr ?? false) && $isMe)
                @php
                    $qrProfileUrl = rtrim((string) config('services.pinkary.url'), '/').'/@'.data_get($profile, 'username', '');
                @endphp
                <native:column class="w-full items-center gap-2 p-4 rounded-md bg-theme-surface border border-theme-outline-variant">
                    <native:image src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($qrProfileUrl) }}" alt="Profile QR code" class="w-56 h-56 rounded-md border border-theme-outline-variant" />
                    <native:text class="text-sm text-center text-theme-on-surface-variant">{{ $qrProfileUrl }}</native:text>
                </native:column>
            @endif

            {{-- Email, member-since, and logout live in Settings (web parity:
                the public profile card never shows them). --}}

            @if ($isMe)
                <native:pressable class="w-full gap-3 p-4 rounded-md bg-theme-surface border border-theme-outline-variant" @tap="compose" a11y-label="Share an update">
                    <native:row class="items-center gap-3">
                        <x-avatar :user="$profile" :size="10" :link="false" />
                        <native:text class="text-sm text-theme-on-surface-variant">Share an update...</native:text>
                    </native:row>
                    <native:button variant="secondary" @tap="compose">Post</native:button>
                </native:pressable>
            @endif

            <native:column class="w-full gap-0 rounded-md bg-theme-surface border border-theme-outline-variant overflow-hidden">
                <native:column class="w-full px-4 py-3 border-b border-theme-outline-variant">
                    <native:text class="text-sm font-semibold uppercase tracking-widest text-theme-on-surface-variant">Posts</native:text>
                </native:column>

                @if ($questionsLoading)
                    <native:column class="w-full items-center gap-3 py-10">
                        <native:activity-indicator />
                        <native:text class="text-sm text-theme-on-surface-variant">Loading posts…</native:text>
                    </native:column>
                @elseif ($questionsError !== '')
                    <native:column class="w-full p-4 gap-3">
                        <native:text class="text-sm text-theme-destructive">{{ $questionsError }}</native:text>
                        <native:pressable class="self-start py-1" @tap="retryQuestions">
                            <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                        </native:pressable>
                    </native:column>
                @elseif (count($questions) === 0)
                    <native:column class="w-full items-center gap-1 px-6 py-10">
                        <native:text class="text-lg font-medium text-theme-on-surface">No posts yet.</native:text>
                        @if ($isMe)
                            <native:text class="text-sm text-center text-theme-on-surface-variant">Share your first update to get started.</native:text>
                        @endif
                    </native:column>
                @else
                    @foreach ($questions as $post)
                        @if ((bool) data_get($post, 'pinned', false))
                            <native:row class="w-full items-center gap-1.5 px-4 pt-3">
                                <native:icon :ios="\App\Icons\Ios::PinFill" :android="\App\Icons\Android::PushPin" :size="12" color="#EC4899" />
                                <native:text class="text-xs font-semibold uppercase tracking-widest text-theme-primary">Pinned</native:text>
                            </native:row>
                        @endif
                        <x-thread-post :post="$post" :expanded-ids="$expanded" />
                    @endforeach

                    <x-list-footer :loading="$loadingMoreQuestions" :error="$loadMoreQuestionsError" tap="loadMoreQuestions" :has-more="$hasMoreQuestions" />
                @endif
            </native:column>
        @endif

        <native:column class="w-full h-20" />
        </native:column>
    </native:column>
</native:list>

@include('native.app-menu-sheet')
