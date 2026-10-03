@props([
    'images' => [],
    'class' => '',
])

{{-- Native stacks ignore absolute children; keep remove controls in normal flow. --}}
@if (count($images) > 0)
    <native:row class="w-full flex-wrap items-start gap-2 {{ $class }}">
        @foreach ($images as $index => $image)
            <native:column class="w-14 items-center gap-0.5">
                <native:pressable @tap="reinsertImage({{ $index }})" a11y-label="Add {{ $image['name'] }} to the post again">
                    <native:image
                        src="{{ $image['url'] }}"
                        alt="{{ $image['name'] }}"
                        class="w-14 h-14 rounded-lg bg-theme-surface-variant border border-theme-outline-variant"
                    />
                </native:pressable>
                <native:pressable class="w-5 h-5 items-center justify-center" @tap="removeImage({{ $index }})" a11y-label="Remove {{ $image['name'] }}">
                    <native:icon :ios="\App\Icons\Ios::Xmark" :android="\App\Icons\AndroidOutlined::Close" :size="12" color="#64748B" dark-color="#94A3B8" />
                </native:pressable>
            </native:column>
        @endforeach
    </native:row>
@endif
