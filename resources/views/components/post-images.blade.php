@props(['images' => []])

@if (count($images) === 1)
    <native:pressable class="w-full" @tap="openLink({{ json_encode($images[0], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }})" a11y-label="Open image fullscreen">
        <x-lazy-image :src="$images[0]" alt="Post image" class="w-full h-64 object-contain rounded-xl" />
    </native:pressable>
@elseif (count($images) > 1)
    <native:column class="w-full gap-1">
        <native:list horizontal class="w-full h-64" a11y-label="Post images">
            @foreach ($images as $index => $src)
                <native:pressable class="w-72 h-64 mr-2" @tap="openLink({{ json_encode($src, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }})" a11y-label="Open image {{ $index + 1 }} of {{ count($images) }} fullscreen">
                    <x-lazy-image :src="$src" alt="Post image {{ $index + 1 }}" class="w-full h-64 object-contain rounded-xl" />
                </native:pressable>
            @endforeach
        </native:list>
        <native:text class="text-xs text-theme-on-surface-variant">Swipe to see all {{ count($images) }} images</native:text>
    </native:column>
@endif
