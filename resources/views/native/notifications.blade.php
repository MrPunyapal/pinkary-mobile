@php
    $shortTime = fn (?string $iso): string => \App\Support\DisplayText::relativeTime($iso);
@endphp

<native:column class="bg-theme-background h-full w-full">
    <x-page-heading title="Notifications">
        @if (! $loading && $errorMessage === '' && count($items) > 0)
            <native:pressable
                class="h-9 w-20 shrink-0 items-center justify-center rounded-full border border-theme-outline-variant {{ $ignoringAll ? 'opacity-40' : '' }}"
                @tap="ignoreAll"
                a11y-label="Ignore all"
            >
                <native:text class="text-theme-on-surface-variant text-sm font-medium" max-lines="1"
                    >Ignore all</native:text>
            </native:pressable>
        @endif
    </x-page-heading>

    @if ($refreshing ?? false)
        <x-refresh-bar />
    @endif

    <native:list on-end-reached="loadMore" :end-reached-buffer="1" class="bg-theme-background w-full flex-1">
        @if ($loading)
            <x-skeleton-notifications />
        @elseif ($errorMessage !== '')
            <native:column class="bg-theme-destructive/15 border-theme-destructive mx-4 my-5 gap-3 rounded-md border p-4">
                <native:text class="text-theme-destructive text-sm">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry" a11y-label="Try again">
                    <native:text class="text-theme-destructive text-sm font-semibold">Try again</native:text>
                </native:pressable>
            </native:column>
        @elseif (count($items) === 0)
            <x-empty-state
                title="No pending notifications."
                subtitle="New replies, mentions, followers, and questions will show up here."
            />
        @else
            @foreach ($items as $item)
                <native:pressable @tap="open('{{ data_get($item, 'id') }}')" a11y-label="Open notification">
                    <native:row class="bg-theme-background border-theme-outline-variant w-full items-start gap-3 border-b px-4 py-3.5">
                        @php $actor = data_get($item, 'actor'); @endphp
                        @if (is_array($actor))
                            <x-avatar :user="$actor" :link="false" />
                        @else
                            <x-avatar :anonymous="true" :link="false" />
                        @endif

                        <native:column class="min-w-0 flex-1 gap-0 py-0.5">
                            <native:row class="w-full items-center gap-x-1.5">
                                <native:row class="min-w-0 flex-1 items-center gap-x-1.5">
                                    <native:text
                                        class="text-theme-on-surface text-sm font-medium"
                                        max-lines="1"
                                    >{{ is_array($actor) ? data_get($actor, 'name') : 'Anonymous' }}</native:text>
                                    @if (is_array($actor) && (data_get($actor, 'verified') || data_get($actor, 'company_verified')))
                                        <native:icon
                                            :ios="\App\Icons\Ios::CheckmarkSealFill"
                                            :android="\App\Icons\Android::Verified"
                                            :size="14"
                                            color="#EC4899"
                                        />
                                    @endif
                                    @if (is_array($actor) && data_get($actor, 'username'))
                                        <native:text
                                            class="text-theme-on-surface-variant text-xs"
                                            max-lines="1"
                                        >{{ '@'.data_get($actor, 'username') }}</native:text>
                                    @endif
                                </native:row>
                                @php $timestamp = $shortTime(data_get($item, 'created_at')); @endphp
                                @if ($timestamp !== '')
                                    <native:text class="text-theme-on-surface-variant shrink-0 text-xs">{{ $timestamp }}</native:text>
                                @endif
                            </native:row>

                            <native:text class="text-theme-on-surface-variant mt-0.5 text-xs">{{ data_get($item, 'action') }}</native:text>

                            @if (is_string(data_get($item, 'snippet')) && data_get($item, 'snippet') !== '')
                                <native:text class="text-theme-on-surface mt-1.5 text-sm leading-relaxed">{{ data_get($item, 'snippet') }}</native:text>
                            @endif
                        </native:column>
                    </native:row>
                </native:pressable>
            @endforeach

        @endif

        <x-list-footer :loading="$loadingMore" :error="$loadMoreError" tap="loadMore" :has-more="$hasMore" />
        <native:column class="h-20 w-full" />
    </native:list>
</native:column>

@include('native.app-menu-sheet')
