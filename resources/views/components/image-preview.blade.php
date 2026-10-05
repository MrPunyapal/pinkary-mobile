@props([
    'images' => [],
    'class' => '',
])

{{-- Native stacks ignore absolute children; keep remove controls in normal flow. --}}
@if (count($images) > 0)
    <native:row class="w-full flex-wrap items-start gap-2 {{ $class }}">
        @foreach ($images as $index => $image)
            <native:column class="w-14 items-center gap-0.5">
                <native:pressable
                    @tap="reinsertImage({{ $index }})"
                    a11y-label="Add {{ $image['name'] }} to the post again"
                >
                    <native:image
                        src="{{ $image['url'] }}"
                        alt="{{ $image['name'] }}"
                        class="bg-theme-surface-variant border-theme-outline-variant h-14 w-14 rounded-lg border"
                    />
                </native:pressable>
                <native:pressable
                    class="h-5 w-5 items-center justify-center"
                    @tap="removeImage({{ $index }})"
                    a11y-label="Remove {{ $image['name'] }}"
                >
                    <native:icon
                        :ios="\App\Icons\Ios::Xmark"
                        :android="\App\Icons\AndroidOutlined::Close"
                        :size="12"
                        color="#64748B"
                        dark-color="#94A3B8"
                    />
                </native:pressable>
            </native:column>
        @endforeach
    </native:row>
@endif
