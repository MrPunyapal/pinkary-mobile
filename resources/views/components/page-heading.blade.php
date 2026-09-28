@props(['title', 'back' => false, 'brand' => false, 'size' => '2xl'])

{{-- Top bar for every screen with a heading. The bar box is the shared
    `px-4 py-4 min-h-14` convention (home.blade.php:9 and search-bar.blade.php:7
    use the same values — that is the web's 57px bar) so the bars cannot
    drift apart.

    `size` defaults to 2xl. The web's own section headings are
    `text-[2rem]` (32px, resources/views/bookmarks/index.blade.php:4 and
    livewire/notifications/index.blade.php:2-3), which is text-3xl here, but
    only the short static titles with no back arrow and no trailing action
    take it: a back arrow plus a 30px title crowds the bar, and the
    notifications screen has to keep room for "Ignore all". Everything with
    a back arrow stays centered at 2xl. --}}
@php
    $hasSlot = trim((string) $slot) !== '';
    $titleSize = 'text-'.$size;
@endphp
<native:column class="w-full bg-theme-surface border-b border-theme-outline-variant justify-center">
    {{-- A trailing action (Ignore all) must sit inside the row. A
        flex-1 title plus a centering spacer pushes that action past
        the screen edge on Android, where text does not shrink. --}}
    <native:row class="w-full items-center px-4 py-4 min-h-14 gap-3 {{ $hasSlot ? 'justify-between' : '' }}">
        @if ($back)
            <native:pressable class="w-10 h-10 items-center justify-center shrink-0 -ml-2" @tap="back" a11y-label="Go back">
                <native:icon :ios="\App\Icons\Ios::ChevronLeft" :android="\App\Icons\AndroidOutlined::ArrowBack" :size="20" color="#64748B" dark-color="#94A3B8" />
            </native:pressable>
        @endif

        @if ($brand)
            {{-- Centered wordmark. The logo image is served over http
                (see components/pinkary-logo); text is the fallback for
                the drawer-free brand bar. --}}
            <native:text class="flex-1 {{ $titleSize }} font-bold text-center text-theme-primary" max-lines="1">{{ $title }}</native:text>
        @elseif ($hasSlot)
            {{-- flex-1 min-w-0 is what keeps a trailing action on screen:
                without a shrinkable title the row measures both children at
                their natural width and "Notifications" + "Ignore all" runs
                past the right edge. --}}
            <native:text class="flex-1 min-w-0 {{ $titleSize }} font-semibold text-theme-on-surface" max-lines="1">{{ $title }}</native:text>
        @else
            <native:text class="flex-1 {{ $titleSize }} font-semibold text-center text-theme-on-surface" max-lines="1">{{ $title }}</native:text>
        @endif

        @if ($hasSlot)
            <native:row class="shrink-0 items-center justify-end gap-2">
                {{ $slot }}
            </native:row>
        @elseif ($back)
            <native:column class="w-10 shrink-0" />
        @endif
    </native:row>
</native:column>
