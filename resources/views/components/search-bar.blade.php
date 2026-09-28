@props(['query' => ''])

{{-- Mirrors page-heading shell verbatim (no nested component) so old Jump
    does not drop the slot — same py-4 min-h-14 outer row. The input
    itself is flat and transparent: the web's search field carries no
    pill chrome, only the bar's own bottom border
    (../pinkary/resources/views/layouts/app.blade.php:83-95). --}}
<native:column class="w-full bg-theme-surface border-b border-theme-outline-variant justify-center">
    <native:row class="w-full items-center px-4 py-4 min-h-14">
        <native:row class="w-full items-center gap-3">
            <native:icon :ios="\App\Icons\Ios::Magnifyingglass" :android="\App\Icons\AndroidOutlined::Search" :size="20" color="#64748B" dark-color="#94A3B8" />
            <native:bare-text-input class="flex-1 text-sm text-theme-on-surface" placeholder="Search users or hashtags..." native:model.debounce.500ms="query" @submit="search" />
            {{-- Deliberate mobile-only affordance: a bare field has no
                browser clear affordance, so the tap target is explicit. --}}
            @if ($query !== '')
                <native:pressable class="shrink-0 px-2 py-1" @tap="clearQuery" a11y-label="Clear search">
                    <native:text class="text-sm font-medium text-theme-on-surface-variant">Clear</native:text>
                </native:pressable>
            @endif
        </native:row>
    </native:row>
</native:column>
