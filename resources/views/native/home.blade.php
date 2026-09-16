<native:top-bar title="Feed">
    <native:top-bar-action id="profile" label="Profile" @tap="profile" />
</native:top-bar>

<native:scroll-view class="w-full h-full bg-[#f1f5f9] dark:bg-[#060c18]">
    <native:column class="w-full gap-0 safe-area-bottom">
        <native:column class="w-full px-5 py-5 gap-2 bg-white dark:bg-[#07101f] border-b border-slate-200 dark:border-slate-800">
            <native:text class="text-2xl font-semibold text-slate-950 dark:text-white">Feed</native:text>
            <native:text class="text-sm text-slate-500 dark:text-slate-400">Thoughtful questions and answers from Pinkary.</native:text>
        </native:column>

        @if ($loading)
            <native:column class="w-full items-center gap-3 px-5 py-10">
                <native:activity-indicator />
                <native:text class="text-sm text-slate-500 dark:text-slate-400">Loading the latest conversations…</native:text>
            </native:column>
        @elseif ($errorMessage !== '')
            <native:column class="w-full mx-4 my-5 p-4 rounded-2xl bg-white dark:bg-[#0b1324] border border-slate-200 dark:border-slate-800">
                <native:text class="text-sm text-pink-500">{{ $errorMessage }}</native:text>
            </native:column>
        @elseif (count($posts) === 0)
            <native:column class="w-full items-center gap-2 px-5 py-10">
                <native:text class="text-lg font-semibold text-slate-950 dark:text-white">There are no questions to show.</native:text>
                <native:text class="text-sm text-center text-slate-500 dark:text-slate-400">Come back once new conversations are published.</native:text>
            </native:column>
        @else
            @foreach ($posts as $post)
                <native:column class="w-full gap-3 px-5 py-5 bg-white dark:bg-[#07101f] border-b border-slate-200 dark:border-slate-800">
                    <native:column class="w-full gap-1">
                        <native:text class="text-sm font-semibold text-slate-950 dark:text-white">{{ data_get($post, 'from.name') }} <native:text class="text-sm font-normal text-slate-500">{{ '@'.data_get($post, 'from.username') }}</native:text></native:text>
                        @if (data_get($post, 'content'))
                            <native:text class="text-sm leading-relaxed text-slate-700 dark:text-slate-200">{{ data_get($post, 'content') }}</native:text>
                        @endif
                    </native:column>

                    <native:column class="w-full gap-2 p-4 rounded-2xl bg-slate-50 dark:bg-[#0b1324] border border-slate-200 dark:border-slate-800">
                        <native:text class="text-xs font-semibold uppercase tracking-widest text-pink-500">{{ data_get($post, 'to.name') }}</native:text>
                        <native:text class="text-sm leading-relaxed text-slate-700 dark:text-slate-200">{{ data_get($post, 'answer') }}</native:text>
                        <native:text class="text-xs text-slate-500 dark:text-slate-400">{{ data_get($post, 'metrics.likes', 0) }} likes · {{ data_get($post, 'metrics.comments', 0) }} comments</native:text>
                    </native:column>
                </native:column>
            @endforeach
        @endif
    </native:column>
</native:scroll-view>
