@props(['images' => [], 'keyPrefix' => ''])

@if (count($images) === 1)
    <native:pressable
        native:key="post-image-{{ $keyPrefix }}-0"
        class="w-full"
        @tap="openImageFullscreen({{ json_encode($images[0], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }})"
        a11y-label="Open image fullscreen"
    >
        <x-lazy-image :src="$images[0]" alt="Post image" class="h-64 w-full rounded-xl object-contain" />
    </native:pressable>
@elseif (count($images) > 1)
    <native:column class="w-full gap-1">
        <native:list horizontal class="h-64 w-full" a11y-label="Post images">
            @foreach ($images as $index => $src)
                <native:pressable
                    native:key="post-image-{{ $keyPrefix }}-{{ $index }}"
                    class="mr-2 h-64 w-72"
                    @tap="openImageFullscreen({{ json_encode($src, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }})"
                    a11y-label="Open image {{ $index + 1 }} of {{ count($images) }} fullscreen"
                >
                    <x-lazy-image
                        :src="$src"
                        alt="Post image {{ $index + 1 }}"
                        class="h-64 w-full rounded-xl object-contain"
                    />
                </native:pressable>
            @endforeach
        </native:list>
        <native:text class="text-theme-on-surface-variant text-xs">Swipe to see all {{ count($images) }} images</native:text>
    </native:column>
@endif
