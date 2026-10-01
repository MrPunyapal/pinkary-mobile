@props(['label', 'model', 'keyboard' => 'default', 'password' => false, 'showPassword' => false, 'error' => ''])

<native:column class="w-full gap-2" native:key="auth-{{ $model }}">
    <native:text class="text-sm font-medium text-theme-on-surface-variant">{{ $label }}</native:text>
    <native:row class="w-full items-center gap-2">
        <native:outlined-text-input
            class="flex-1"
            native:key="input-{{ $model }}"
            native:model="{{ $model }}"
            size="sm"
            a11y-label="{{ $label }}"
            keyboard="{{ $password ? 'password' : $keyboard }}"
            :error="$error !== ''"
            :secure="$password && ! $showPassword" />
        @if ($password)
            <native:pressable class="w-9 h-11 items-center justify-center" @tap="togglePasswordVisibility" a11y-label="{{ $showPassword ? 'Hide password' : 'Show password' }}">
                <native:icon :ios="$showPassword ? \App\Icons\Ios::EyeSlash : \App\Icons\Ios::Eye" :android="$showPassword ? \App\Icons\AndroidOutlined::VisibilityOff : \App\Icons\AndroidOutlined::Visibility" :size="18" class="text-theme-on-surface-variant" />
            </native:pressable>
        @endif
    </native:row>
    @if ($error !== '')
        <native:text class="text-xs text-theme-destructive">{{ $error }}</native:text>
    @endif
</native:column>
