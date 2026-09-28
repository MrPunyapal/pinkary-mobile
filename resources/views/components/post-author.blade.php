@props(['author'])

<native:row class="w-full items-center gap-3">
    <x-avatar :user="$author" company-radius="md" />

    <native:column class="flex-1 gap-0.5">
        <native:pressable @tap="openProfile('{{ data_get($author, 'username', '') }}')" a11y-label="Open {{ '@'.data_get($author, 'username', '') }}’s profile">
            <native:row class="items-center gap-1.5">
                <native:text class="text-sm font-medium text-theme-on-surface" max-lines="1">{{ data_get($author, 'name') }}</native:text>
                {{-- Web parity (components/avatar-with-name.blade.php:19-23):
                    the badge is gated on is_verified alone — company_verified
                    only selects different badge art, never grants the badge. --}}
                @if (data_get($author, 'verified'))
                    <native:icon :ios="\App\Icons\Ios::CheckmarkSealFill" :android="\App\Icons\Android::Verified" :size="14" color="#EC4899" />
                @endif
            </native:row>
        </native:pressable>
        <native:text class="text-xs text-theme-on-surface-variant" max-lines="1">{{ '@'.data_get($author, 'username') }}</native:text>
    </native:column>
</native:row>
