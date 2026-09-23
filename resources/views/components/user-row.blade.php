@props(['user'])

<native:pressable @tap="openProfile('{{ data_get($user, 'username', '') }}')" a11y-label="Open {{ '@'.data_get($user, 'username', '') }}’s profile">
    <native:row class="w-full items-center gap-3 px-4 py-4 bg-theme-background border-b border-theme-outline-variant">
        <x-avatar :user="$user" company-radius="md" :link="false" />
        <native:column class="flex-1 gap-0.5 min-w-0">
            <native:row class="items-center gap-1.5">
                <native:text class="text-sm font-medium text-theme-on-surface" max-lines="1">{{ data_get($user, 'name') }}</native:text>
                @if (data_get($user, 'verified') || data_get($user, 'company_verified'))
                    <native:icon :ios="\App\Icons\Ios::CheckmarkSealFill" :android="\App\Icons\Android::Verified" :size="14" color="#EC4899" />
                @endif
            </native:row>
            <native:text class="text-sm text-theme-on-surface-variant" max-lines="1">{{ '@'.data_get($user, 'username') }}</native:text>
        </native:column>
        @if (data_get($user, 'followed'))
            <native:text class="text-xs text-theme-on-surface-variant shrink-0">Following</native:text>
        @endif
    </native:row>
</native:pressable>
