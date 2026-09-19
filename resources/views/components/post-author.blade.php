@props(['author'])

<native:row class="w-full items-center gap-3">
    @if (data_get($author, 'avatar'))
        <native:pressable @tap="openProfile('{{ data_get($author, 'username', '') }}')" a11y-label="Open {{ '@'.data_get($author, 'username', '') }}’s profile">
            <native:image src="{{ data_get($author, 'avatar') }}" alt="{{ data_get($author, 'username', '') }}" class="w-10 h-10 object-cover {{ data_get($author, 'company_verified') ? 'rounded-md' : 'rounded-full' }} border border-theme-outline" />
        </native:pressable>
    @else
        <native:pressable @tap="openProfile('{{ data_get($author, 'username', '') }}')" a11y-label="Open {{ '@'.data_get($author, 'username', '') }}’s profile">
            <native:column class="w-10 h-10 items-center justify-center {{ data_get($author, 'company_verified') ? 'rounded-md' : 'rounded-full' }} bg-theme-avatar border border-theme-outline">
                <native:text class="text-sm text-theme-on-avatar">{{ strtoupper(substr((string) data_get($author, 'name', '?'), 0, 1)) }}</native:text>
            </native:column>
        </native:pressable>
    @endif

    <native:column class="flex-1 gap-0.5">
        <native:pressable @tap="openProfile('{{ data_get($author, 'username', '') }}')" a11y-label="Open {{ '@'.data_get($author, 'username', '') }}’s profile">
            <native:row class="items-center gap-1.5">
                <native:text class="text-sm font-medium text-theme-on-surface">{{ data_get($author, 'name') }}</native:text>
                @if (data_get($author, 'verified') || data_get($author, 'company_verified'))
                    <native:icon :ios="\App\Icons\Ios::CheckmarkSealFill" :android="\App\Icons\Android::Verified" :size="14" color="#EC4899" />
                @endif
            </native:row>
        </native:pressable>
        <native:text class="text-xs text-theme-on-surface-variant">{{ '@'.data_get($author, 'username') }}</native:text>
    </native:column>
</native:row>
