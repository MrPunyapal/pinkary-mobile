@props(['query' => ''])

<native:row class="w-full items-center gap-3 px-4 py-3 bg-theme-surface border-b border-theme-outline-variant">
    <native:row class="w-full items-center gap-3 px-3 py-2.5 rounded-md bg-theme-surface-variant border border-theme-outline-variant">
        <native:icon :ios="\App\Icons\Ios::Magnifyingglass" :android="\App\Icons\AndroidOutlined::Search" :size="18" color="#64748B" dark-color="#94A3B8" />
        <native:bare-text-input class="flex-1 text-sm text-theme-on-surface" placeholder="Search users or hashtags..." native:model.debounce.500ms="query" />
    </native:row>
</native:row>
