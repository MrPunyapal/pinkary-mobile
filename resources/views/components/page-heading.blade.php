@props(['title', 'back' => false, 'brand' => false, 'size' => '2xl'])

@php
    $hasSlot = trim((string) $slot) !== '';
    $titleSize = 'text-'.$size;
@endphp
<native:column class="w-full bg-theme-surface border-b border-theme-outline-variant justify-center">
    <native:row class="w-full items-center px-4 py-4 min-h-14 gap-3 {{ $hasSlot ? 'justify-between' : '' }}">
        @if ($back)
            <native:pressable class="w-10 h-10 items-center justify-center shrink-0 -ml-2" @tap="back" a11y-label="Go back">
                <native:icon :ios="\App\Icons\Ios::ChevronLeft" :android="\App\Icons\AndroidOutlined::ArrowBack" :size="20" color="#64748B" dark-color="#94A3B8" />
            </native:pressable>
        @endif

        @if ($brand)
            <native:text class="flex-1 {{ $titleSize }} font-bold text-center text-theme-primary" max-lines="1">{{ $title }}</native:text>
        @elseif ($hasSlot)
            {{-- Native text must be shrinkable to leave room for trailing actions. --}}
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
