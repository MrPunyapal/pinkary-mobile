@props(['post'])

@php
    $anonymous = (bool) data_get($post, 'anonymously', false);
    $sharedUpdate = data_get($post, 'content') === '__UPDATE__'
        && data_get($post, 'from.id') !== null
        && data_get($post, 'from.id') === data_get($post, 'to.id');
@endphp

<native:column class="w-full gap-4 px-5 py-5 bg-theme-surface border-b border-theme-outline-variant">
    @unless ($sharedUpdate)
        <native:column class="w-full gap-3 p-3 rounded-lg bg-theme-surface-variant border border-theme-outline-variant">
            @if ($anonymous)
                <native:row class="w-full items-center gap-3">
                    <native:column class="w-8 h-8 items-center justify-center rounded-full border border-theme-outline">
                        <native:text class="text-sm text-theme-on-surface-variant">?</native:text>
                    </native:column>
                    <native:text class="text-sm text-theme-on-surface-variant">Anonymously</native:text>
                </native:row>
            @else
                <x-post-author :author="data_get($post, 'from', [])" />
            @endif

            @if (data_get($post, 'content') && ! $anonymous)
                <native:text class="text-sm leading-relaxed text-theme-on-surface">{{ data_get($post, 'content') }}</native:text>
            @endif
        </native:column>
    @endunless

    @if (data_get($post, 'answer') !== null && data_get($post, 'answer') !== '')
        <native:column class="w-full gap-3">
            <x-post-author :author="data_get($post, 'to', [])" />

            @if (data_get($post, 'updated_at'))
                <native:text class="text-xs text-theme-on-surface-variant">{{ \Illuminate\Support\Carbon::parse(data_get($post, 'updated_at'))->diffForHumans() }}</native:text>
            @endif

            <native:text class="text-base leading-relaxed text-theme-on-surface">{{ data_get($post, 'answer') }}</native:text>

            <native:column class="w-full gap-2 pt-2">
                <native:text class="text-xs text-theme-on-surface-variant">{{ data_get($post, 'metrics.comments', 0) }} comments · {{ data_get($post, 'metrics.likes', 0) }} likes · {{ data_get($post, 'views', 0) }} views</native:text>
                @if (data_get($post, 'metrics.liked', false))
                    <native:text class="text-xs text-theme-accent">Liked by you</native:text>
                @endif
            </native:column>
        </native:column>
    @endif
</native:column>
