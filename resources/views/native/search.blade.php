<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        <x-search-bar :query="$query" />

        {{-- Search.php's navTitle never renders — the screen's header is
            in content, so the title needs an in-content band. Hidden once
            a query is running, where the result bands speak for
            themselves. Styled like the People/Hashtags bands below. No
            back arrow: the web has none. --}}
        @if ($query === '')
            <native:column class="w-full px-4 py-3 bg-theme-surface border-b border-theme-outline-variant">
                <native:text class="text-lg font-semibold text-theme-on-surface">Search</native:text>
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
                <native:column class="mx-4 my-5 p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                    <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                </native:column>
            @endif

            @if ($query === '' && count($recentSearches) > 0)
                <native:column class="w-full px-4 py-3 bg-theme-surface border-b border-theme-outline-variant">
                    <native:text class="text-lg font-semibold text-theme-on-surface">Recent searches</native:text>
                </native:column>
                @foreach ($recentSearches as $index => $recent)
                    <native:pressable @tap="selectRecent({{ $index }})" a11y-label="Search {{ $recent }} again">
                        <native:row class="w-full items-center gap-3 px-4 py-4 bg-theme-background border-b border-theme-outline-variant">
                            <native:column class="w-10 h-10 items-center justify-center rounded-full bg-theme-surface-variant border border-theme-outline-variant">
                                <native:icon :ios="\App\Icons\Ios::Clock" :android="\App\Icons\AndroidOutlined::History" :size="16" color="#64748B" dark-color="#94A3B8" />
                            </native:column>
                            <native:text class="flex-1 text-sm text-theme-on-surface" max-lines="1">{{ $recent }}</native:text>
                        </native:row>
                    </native:pressable>
                @endforeach
            @endif
            @if (count($users) > 0)
                <native:column class="w-full px-4 py-3 bg-theme-surface border-b border-theme-outline-variant">
                    <native:text class="text-lg font-semibold text-theme-on-surface">People</native:text>
                </native:column>
                @foreach ($users as $user)
                    <x-user-row :user="$user" />
                @endforeach
            @endif

            @if (count($hashtags) > 0)
                <native:column class="w-full px-4 py-3 bg-theme-surface border-b border-theme-outline-variant">
                    <native:text class="text-lg font-semibold text-theme-on-surface">Hashtags</native:text>
                </native:column>
                @foreach ($hashtags as $hashtag)
                    <native:pressable @tap="openHashtag('{{ data_get($hashtag, 'name', '') }}')" a11y-label="Search hashtag {{ '#'.data_get($hashtag, 'name', '') }}">
                        <native:row class="w-full items-center gap-3 px-4 py-4 bg-theme-background border-b border-theme-outline-variant">
                            <native:column class="w-10 h-10 items-center justify-center rounded-full bg-theme-surface-variant border border-theme-outline-variant">
                                <native:text class="text-base font-semibold text-theme-primary">#</native:text>
                            </native:column>
                            <native:column class="flex-1 gap-0.5 min-w-0">
                                <native:text class="text-sm font-medium text-theme-on-surface" max-lines="1">{{ '#'.data_get($hashtag, 'name') }}</native:text>
                                <native:text class="text-sm text-theme-on-surface-variant">{{ data_get($hashtag, 'questions_count', 0) }} posts</native:text>
                            </native:column>
                        </native:row>
                    </native:pressable>
                @endforeach
            @endif

            {{-- Web copy (../pinkary/resources/views/livewire/home/users.blade.php:29-32 and :8-10). --}}
            @if ($searched && count($users) === 0 && count($hashtags) === 0)
                <x-empty-state title="No users found." subtitle="Try another search or clear the query to return to the discovery list." />
            @endif

            @if (! $searched)
                <native:column class="w-full items-center gap-2 px-5 py-10">
                    <native:text class="text-sm text-center text-theme-on-surface-variant">Discover new profiles to follow.</native:text>
                </native:column>
            @endif
        @endif

        <native:column class="w-full h-20" />
    </native:column>
</native:scroll-view>

@include('native.app-menu-sheet')
