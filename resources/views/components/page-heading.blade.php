@props(['title', 'back' => false, 'brand' => false])

{{-- Centered heading (bookmarks pattern): title always centered and
    vertically centered (py-4 + min-h-14 + items-center). Single source
    for every top bar — copied verbatim to search-bar so headers can
    never drift (no nested shell — old Jump clients drop nested slots). --}}
@php $hasSlot = trim((string) $slot) !== ''; @endphp
<native:column class="w-full bg-theme-surface border-b border-theme-outline-variant justify-center">
    <native:row class="w-full items-center px-4 py-4 min-h-14">
        @if ($back)
            <native:pressable class="w-10 h-10 items-center justify-center shrink-0 -ml-2" @tap="back" a11y-label="Go back">
                <native:icon :ios="\App\Icons\Ios::ChevronLeft" :android="\App\Icons\AndroidOutlined::ArrowBack" :size="20" color="#64748B" dark-color="#94A3B8" />
            </native:pressable>
        @elseif ($hasSlot)
            {{-- Spacer keeps title centered when trailing action exists --}}
            <native:column class="w-10 shrink-0" />
        @endif

        @if ($brand)
            {{-- Centered wordmark. A logo image was tried here but
                public_path() is a server disk path the phone cannot load
                (image slots need an http(s) URL); text always renders. --}}
            <native:text class="flex-1 text-xl font-bold text-center text-theme-primary" max-lines="1">{{ $title }}</native:text>
        @else
            <native:text class="flex-1 text-2xl font-semibold text-center text-theme-on-surface">{{ $title }}</native:text>
        @endif

        @if ($hasSlot)
            <native:row class="shrink-0 items-center justify-end">
                {{ $slot }}
            </native:row>
        @elseif ($back)
            <native:column class="w-10 shrink-0" />
        @endif
    </native:row>
</native:column>
