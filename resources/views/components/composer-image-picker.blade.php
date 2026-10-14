@props(['postIndex' => -1, 'label' => 'Add an image', 'enabled' => false, 'canAdd' => false])

@if ($enabled)
    <native:pressable
        class="text-theme-on-surface-variant h-7 w-7 items-center justify-center {{ $canAdd ? '' : 'opacity-40' }}"
        @tap="pickImages({{ $postIndex }})"
        a11y-label="{{ $label }}"
    >
        <native:icon :ios="\App\Icons\Ios::Photo" :android="\App\Icons\AndroidOutlined::Image" :size="16" />
    </native:pressable>
@endif
