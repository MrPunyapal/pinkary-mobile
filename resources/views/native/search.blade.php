<native:top-bar title="Search" />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 px-5 py-5">
        <native:filled-text-input label="Search users or hashtags" placeholder="Try ‘laravel’…" native:model.debounce.500ms="query" />

        @if ($searching)
            <native:column class="w-full items-center gap-3 py-6">
                <native:activity-indicator />
            </native:column>
        @else
            @if (count($users) > 0)
                <native:column class="w-full gap-2">
                <native:text class="text-lg font-semibold text-theme-on-surface">People</native:text>
                    <native:list>
                        @foreach ($users as $user)
                            <native:list-item
                                headline="{{ data_get($user, 'name') }}"
                                supporting="{{ '@'.data_get($user, 'username') }}{{ data_get($user, 'followed') ? ' · Following' : '' }}"
                                leading-avatar="{{ data_get($user, 'avatar', '') }}"
                                @tap="openProfile('{{ data_get($user, 'username', '') }}')"
                            />
                        @endforeach
                    </native:list>
                </native:column>
            @endif

            @if (count($hashtags) > 0)
                <native:column class="w-full gap-2">
                <native:text class="text-lg font-semibold text-theme-on-surface">Hashtags</native:text>
                    <native:list>
                        @foreach ($hashtags as $hashtag)
                            <native:list-item
                                headline="{{ '#'.data_get($hashtag, 'name') }}"
                                supporting="{{ data_get($hashtag, 'questions_count', 0) }} posts"
                            />
                        @endforeach
                    </native:list>
                </native:column>
            @endif

            @if ($searched && count($users) === 0 && count($hashtags) === 0)
                <native:column class="w-full items-center gap-2 py-10">
                    <native:text class="text-lg font-semibold text-theme-on-surface">No results.</native:text>
                    <native:text class="text-sm text-center text-theme-on-surface-variant">Try a different name or hashtag.</native:text>
                </native:column>
            @endif

            @if (! $searched)
                <native:column class="w-full items-center gap-2 py-10">
                    <native:text class="text-sm text-center text-theme-on-surface-variant">Search people by name or username, and hashtags by prefix.</native:text>
                </native:column>
            @endif
        @endif

        <native:column class="w-full h-20" />
    </native:column>
</native:scroll-view>
