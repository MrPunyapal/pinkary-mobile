<native:scroll-view class="bg-theme-background h-full w-full">
    <native:column class="w-full gap-0">
        <x-search-bar :query="$query" />

        @if ($query === '')
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

            @if ($query === '' && count($recentSearches) > 0)
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
            @if (count($users) > 0)
                <native:column class="bg-theme-surface border-theme-outline-variant w-full border-b px-4 py-3">
                    <native:text class="text-theme-on-surface text-lg font-semibold">People</native:text>
                </native:column>
                @foreach ($users as $user)
                    <x-user-row :user="$user" />
                @endforeach
            @endif

            @if (count($hashtags) > 0)
                <native:column class="bg-theme-surface border-theme-outline-variant w-full border-b px-4 py-3">
                    <native:text class="text-theme-on-surface text-lg font-semibold">Hashtags</native:text>
                </native:column>
                @foreach ($hashtags as $hashtag)
                    <native:pressable @tap="openHashtag('{{ data_get($hashtag, 'name', '') }}')"
                        a11y-label="Search hashtag {{ '#'.data_get($hashtag, 'name', '') }}"
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

            @if ($searched && count($users) === 0 && count($hashtags) === 0)
                <x-empty-state
                    title="No users found."
                    subtitle="Try another search or clear the query to return to the discovery list."
                />
            @endif

            @if (! $searched)
                <native:column class="w-full items-center gap-2 px-5 py-10">
                    <native:text class="text-theme-on-surface-variant text-center text-sm">Discover new profiles to follow.</native:text>
                </native:column>
            @endif
        @endif

        <native:column class="h-20 w-full" />
    </native:column>
</native:scroll-view>

@include('native.app-menu-sheet')
