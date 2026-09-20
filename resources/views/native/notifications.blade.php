@php
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
@endphp

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        <x-page-heading title="Notifications">
            @if (! $loading && $errorMessage === '' && count($items) > 0)
                <native:pressable class="shrink-0 px-3 py-1.5 rounded-full border border-theme-outline-variant {{ $markingRead ? 'opacity-40' : '' }}" @tap="markAllRead" a11y-label="Ignore all">
                    <native:text class="text-sm font-medium text-theme-on-surface-variant">Ignore all</native:text>
                </native:pressable>
            @endif
        </x-page-heading>

        @if ($loading)
            <native:column class="w-full items-center gap-3 px-5 py-10">
                <native:activity-indicator />
                <native:text class="text-sm text-theme-on-surface-variant">Loading notifications…</native:text>
            </native:column>
        @elseif ($errorMessage !== '')
            <native:column class="w-full mx-4 my-5 p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry">
                    <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                </native:pressable>
            </native:column>
        @elseif (count($items) === 0)
            <x-empty-state title="No pending notifications." subtitle="New replies, mentions, followers, and questions will show up here.">
                <native:pressable class="px-4 py-2 rounded-full bg-theme-primary" @tap="goFeed" a11y-label="Back to Feed">
                    <native:text class="text-sm font-semibold text-white">Back to Feed</native:text>
                </native:pressable>
            </x-empty-state>
        @else
            @foreach ($items as $item)
                <native:pressable @tap="open('{{ data_get($item, 'id') }}')" a11y-label="Open notification">
                    <native:row class="w-full items-start gap-3 px-4 py-3.5 bg-theme-background border-b border-theme-outline-variant">
                        @php $actor = data_get($item, 'actor'); @endphp
                        @if (is_array($actor))
                            <x-avatar :user="$actor" :link="false" />
                        @else
                            <x-avatar :anonymous="true" :link="false" />
                        @endif

                        <native:column class="flex-1 gap-0 min-w-0 py-0.5">
                            <native:row class="w-full items-center gap-x-1.5">
                                <native:row class="flex-1 items-center gap-x-1.5 min-w-0">
                                    <native:text class="text-sm font-medium text-theme-on-surface truncate">{{ is_array($actor) ? data_get($actor, 'name') : 'Anonymous' }}</native:text>
                                    @if (is_array($actor) && (data_get($actor, 'verified') || data_get($actor, 'company_verified')))
                                        <native:icon :ios="\App\Icons\Ios::CheckmarkSealFill" :android="\App\Icons\Android::Verified" :size="14" color="#EC4899" />
                                    @endif
                                    @if (is_array($actor) && data_get($actor, 'username'))
                                        <native:text class="text-xs text-theme-on-surface-variant truncate">{{ '@'.data_get($actor, 'username') }}</native:text>
                                    @endif
                                </native:row>
                                @php $timestamp = $shortTime(data_get($item, 'created_at')); @endphp
                                @if ($timestamp !== '')
                                    <native:text class="text-xs text-theme-on-surface-variant shrink-0">{{ $timestamp }}</native:text>
                                @endif
                            </native:row>

                            <native:text class="text-xs text-theme-on-surface-variant mt-0.5">{{ data_get($item, 'action') }}</native:text>

                            @if (is_string(data_get($item, 'snippet')) && data_get($item, 'snippet') !== '')
                                <native:text class="text-sm leading-relaxed text-theme-on-surface mt-1.5">{{ data_get($item, 'snippet') }}</native:text>
                            @endif
                        </native:column>
                    </native:row>
                </native:pressable>
            @endforeach

            @if ($hasMore)
                <native:column class="w-full items-center gap-2 px-5 py-5">
                    @if ($loadMoreError !== '')
                        <native:text class="text-sm text-center text-theme-destructive">{{ $loadMoreError }}</native:text>
                    @endif
                    <native:button variant="secondary" :disabled="$loadingMore" @tap="loadMore">{{ $loadingMore ? 'Loading…' : 'Load more' }}</native:button>
                </native:column>
            @endif
        @endif

        <native:column class="w-full h-20" />
    </native:column>
</native:scroll-view>
