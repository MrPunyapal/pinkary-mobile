@props(['label', 'model', 'keyboard' => 'default', 'password' => false, 'showPassword' => false, 'error' => ''])

<native:column class="w-full gap-2" native:key="auth-{{ $model }}">
    <native:text class="text-theme-on-surface-variant text-sm font-medium">{{ $label }}</native:text>
    <native:row class="w-full items-center gap-2">
        <native:outlined-text-input
            class="flex-1"
            native:key="input-{{ $model }}"
            native:model="{{ $model }}"
            size="sm"
            a11y-label="{{ $label }}"
            keyboard="{{ $password ? 'password' : $keyboard }}"
            :error="$error !== ''"
            :secure="$password && ! $showPassword"
        />
        @if ($password)
            <native:pressable
                class="h-11 w-9 items-center justify-center"
                @tap="togglePasswordVisibility"
                a11y-label="{{ $showPassword ? 'Hide password' : 'Show password' }}"
            >
                <native:icon
                    :ios="$showPassword ? \App\Icons\Ios::EyeSlash : \App\Icons\Ios::Eye"
                    :android="$showPassword ? \App\Icons\AndroidOutlined::VisibilityOff : \App\Icons\AndroidOutlined::Visibility"
                    :size="18"
                    class="text-theme-on-surface-variant"
                />
            </native:pressable>
        @endif
    </native:row>
    @if ($error !== '')
        <native:text class="text-theme-destructive text-xs">{{ $error }}</native:text>
    @endif
</native:column>
