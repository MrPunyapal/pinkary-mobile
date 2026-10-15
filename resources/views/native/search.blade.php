@php $hasQuery = mb_trim($query, "@# \t\n\r\0\x0B") !== ''; @endphp
<native:scroll-view class="bg-theme-background h-full w-full">
    <native:column class="w-full gap-0">
        <x-search-bar :query="$query" />

        @if (! $hasQuery)
            <native:column class="bg-theme-surface border-theme-outline-variant w-full border-b px-4 py-3">
                <native:text class="text-theme-on-surface text-lg font-semibold">Search</native:text>
            </native:column>
        @endif

        @if ($searching)
            <native:column class="w-full gap-0">
                <x-skeleton-user-row />
                <x-skeleton-user-row />
                <x-skeleton-user-row />
                <x-skeleton-user-row />
            </native:column>
        @else
            @if (($errorMessage ?? '') !== '')
                <native:column class="bg-theme-destructive/15 border-theme-destructive mx-4 my-5 gap-3 rounded-md border p-4">
                    <native:text class="text-theme-destructive text-sm">{{ $errorMessage }}</native:text>
                </native:column>
            @endif

            @if (! $hasQuery && count($recentSearches) > 0)
                <native:column class="bg-theme-surface border-theme-outline-variant w-full border-b px-4 py-3">
                    <native:text class="text-theme-on-surface text-lg font-semibold">Recent searches</native:text>
                </native:column>
                @foreach ($recentSearches as $index => $recent)
                    <native:pressable @tap="selectRecent({{ $index }})" a11y-label="Search {{ $recent }} again">
                        <native:row class="bg-theme-background border-theme-outline-variant w-full items-center gap-3 border-b px-4 py-4">
                            <native:column class="bg-theme-surface-variant border-theme-outline-variant h-10 w-10 items-center justify-center rounded-full border">
                                <native:icon
                                    :ios="\App\Icons\Ios::Clock"
                                    :android="\App\Icons\AndroidOutlined::History"
                                    :size="16"
                                    color="#64748B"
                                    dark-color="#94A3B8"
                                />
                            </native:column>
                            <native:text
                                class="text-theme-on-surface flex-1 text-sm"
                                max-lines="1"
                            >{{ $recent }}</native:text>
                        </native:row>
                    </native:pressable>
                @endforeach
            @endif
            @if (! $hasQuery)
                <native:column class="bg-theme-surface border-theme-outline-variant w-full border-b px-4 py-3">
                    <native:text class="text-theme-on-surface text-lg font-semibold">People to follow</native:text>
                </native:column>
                @if ($recommendationsLoading && $recommendationPage === 0)
                    <x-skeleton-user-row />
                    <x-skeleton-user-row />
                    <x-skeleton-user-row />
                @elseif ($recommendationsError !== '' && count($recommendations) === 0)
                    <x-empty-state :title="$recommendationsError" />
                    <x-load-more tap="loadMoreRecommendations" label="Try again" />
                @elseif (count($recommendations) === 0)
                    <x-empty-state title="No recommendations right now." />
                @else
                    @foreach ($recommendations as $user)
                        <x-user-row :user="$user" />
                    @endforeach
                    @if ($recommendationsHasMore || $recommendationsLoading || $recommendationsError !== '')
                        <x-load-more
                            tap="loadMoreRecommendations"
                            label="More people to follow"
                            :loading="$recommendationsLoading"
                            :error="$recommendationsError"
                        />
                    @else
                        <x-list-footer :has-more="false" end-text="You're all caught up." />
                    @endif
                @endif

                <native:column class="bg-theme-surface border-theme-outline-variant w-full border-b px-4 py-3">
                    <native:text class="text-theme-on-surface text-lg font-semibold">Discover people</native:text>
                </native:column>
                @if ($directoryLoading && $directoryPage === 0)
                    <x-skeleton-user-row />
                    <x-skeleton-user-row />
                    <x-skeleton-user-row />
                @elseif ($directoryError !== '' && count($directoryUsers) === 0)
                    <x-empty-state :title="$directoryError" />
                    <x-load-more tap="loadMoreDirectory" label="Try again" />
                @elseif (count($directoryUsers) === 0)
                    <x-empty-state title="No people to discover right now." />
                @else
                    @foreach ($directoryUsers as $user)
                        <x-user-row :user="$user" />
                    @endforeach
                    @if ($directoryHasMore || $directoryLoading || $directoryError !== '')
                        <x-load-more
                            tap="loadMoreDirectory"
                            label="Load more people"
                            :loading="$directoryLoading"
                            :error="$directoryError"
                        />
                    @else
                        <x-list-footer :has-more="false" end-text="You're all caught up." />
                    @endif
                @endif
            @endif

            @if ($hasQuery && count($users) > 0)
                <native:column class="bg-theme-surface border-theme-outline-variant w-full border-b px-4 py-3">
                    <native:text class="text-theme-on-surface text-lg font-semibold">People</native:text>
                </native:column>
                @foreach ($users as $user)
                    <x-user-row :user="$user" />
                @endforeach
            @endif

            @if ($hasQuery && count($hashtags) > 0)
                <native:column class="bg-theme-surface border-theme-outline-variant w-full border-b px-4 py-3">
                    <native:text class="text-theme-on-surface text-lg font-semibold">Hashtags</native:text>
                </native:column>
                @foreach ($hashtags as $hashtag)
                    <native:pressable @tap="openHashtag({{ (int) data_get($hashtag, 'id', 0) }})"
                        a11y-label="Open posts for hashtag {{ '#'.data_get($hashtag, 'name', '') }}"
                    >
                        <native:row
                            class="bg-theme-background border-theme-outline-variant w-full items-center gap-3 border-b px-4 py-4"
                        >
                            <native:column
                                class="bg-theme-surface-variant border-theme-outline-variant h-10 w-10 items-center justify-center rounded-full border"
                            >
                                <native:text class="text-theme-primary text-base font-semibold">#</native:text>
                            </native:column>
                            <native:column class="min-w-0 flex-1 gap-0.5">
                                <native:text
                                    class="text-theme-on-surface text-sm font-medium"
                                    max-lines="1"
                                >{{ '#'.data_get($hashtag, 'name') }}</native:text>
                                <native:text class="text-theme-on-surface-variant text-sm">{{ data_get($hashtag, 'questions_count', 0) }} posts</native:text>
                            </native:column>
                        </native:row>
                    </native:pressable>
                @endforeach
            @endif

            @if ($hasQuery && $searched && count($users) === 0 && count($hashtags) === 0)
                <x-empty-state
                    title="No users found."
                    subtitle="Try another search or clear the query to return to the discovery list."
                />
            @endif

        @endif

        <native:column class="h-20 w-full" />
    </native:column>
</native:scroll-view>

@include('native.app-menu-sheet')
