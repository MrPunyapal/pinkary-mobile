@props(['query' => ''])

<native:column class="bg-theme-surface border-theme-outline-variant w-full justify-center border-b">
    <native:row class="min-h-14 w-full items-center px-4 py-4">
        <native:row class="w-full items-center gap-3">
            <native:icon
                :ios="\App\Icons\Ios::Magnifyingglass"
                :android="\App\Icons\AndroidOutlined::Search"
                :size="20"
                color="#64748B"
                dark-color="#94A3B8"
            />
            <native:bare-text-input
                class="text-theme-on-surface flex-1 text-sm"
                placeholder="Search users or hashtags..."
                native:model.debounce.500ms="query"
                @submit="search"
            />
            @if ($query !== '')
                <native:pressable class="shrink-0 px-2 py-1" @tap="clearQuery" a11y-label="Clear search">
                    <native:text class="text-theme-on-surface-variant text-sm font-medium">Clear</native:text>
                </native:pressable>
            @endif
        </native:row>
    </native:row>
</native:column>
