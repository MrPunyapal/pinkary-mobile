@php
    // Same clock as the web rows: short diffForHumans.
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

<native:top-bar title="Notifications" />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
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
            <native:column class="w-full p-4">
                <native:column class="w-full items-center gap-2 px-6 py-10 rounded-md bg-theme-surface border border-dashed border-theme-outline">
                    <native:text class="text-lg font-medium text-theme-on-surface">No pending notifications.</native:text>
                    <native:text class="text-sm text-center text-theme-on-surface-variant">New replies, mentions, followers, and questions will show up here.</native:text>
                </native:column>
            </native:column>
        @else
            @if (! $loading && $errorMessage === '' && count($items) > 0)
                <native:row class="w-full justify-end px-4 py-3 bg-theme-surface border-b border-theme-outline-variant">
                    <native:pressable class="px-3 py-2 rounded-full border border-theme-outline-variant {{ $markingRead ? 'opacity-40' : '' }}" @tap="markAllRead" a11y-label="Ignore all">
                        <native:text class="text-sm font-medium text-theme-on-surface-variant">Ignore all</native:text>
                    </native:pressable>
                </native:row>
            @endif
            @foreach ($items as $item)
                <native:pressable @tap="open('{{ data_get($item, 'id') }}')" a11y-label="Open notification">
                    <native:column class="w-full bg-theme-surface border-b border-theme-outline-variant">
                        <native:row class="w-full items-start gap-3 px-4 py-3.5">
                            @php $actor = data_get($item, 'actor'); @endphp
                            @if (is_array($actor))
                                @if (data_get($actor, 'avatar'))
                                    <native:image src="{{ data_get($actor, 'avatar') }}" alt="{{ data_get($actor, 'username', '') }}" class="w-10 h-10 shrink-0 {{ data_get($actor, 'company_verified') ? 'rounded-2xl' : 'rounded-full' }} border border-theme-outline" />
                                @else
                                    <native:column class="w-10 h-10 shrink-0 items-center justify-center {{ data_get($actor, 'company_verified') ? 'rounded-2xl' : 'rounded-full' }} bg-theme-avatar border border-theme-outline">
                                        <native:text class="text-sm text-theme-on-avatar">{{ strtoupper(substr((string) data_get($actor, 'name', '?'), 0, 1)) }}</native:text>
                                    </native:column>
                                @endif
                            @else
                                <native:column class="w-10 h-10 shrink-0 items-center justify-center rounded-full border border-dashed border-theme-outline bg-theme-avatar">
                                    <native:text class="text-sm font-medium text-theme-on-surface-variant">?</native:text>
                                </native:column>
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
                    </native:column>
                </native:pressable>
            @endforeach

            @if ($hasMore)
                <native:column class="w-full items-center gap-2 px-5 py-5">
                    <native:button variant="secondary" :disabled="$loadingMore" @tap="loadMore">{{ $loadingMore ? 'Loading…' : 'Load more' }}</native:button>
                </native:column>
            @endif
        @endif

        <native:column class="w-full h-20" />
    </native:column>
</native:scroll-view>
