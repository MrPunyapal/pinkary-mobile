@props(['users'])

@if (count($users) > 0)
    <native:column class="w-full gap-0 rounded-md bg-theme-surface-variant border border-theme-outline-variant" native:key="mention-suggestions">
        @foreach ($users as $index => $user)
            <native:pressable class="w-full px-3 py-2" @tap="selectMention({{ $index }})" a11y-label="Mention {{ '@'.$user['username'] }}">
                <native:row class="w-full items-center gap-2">
                    <x-avatar :user="$user" :size="7" :link="false" />
                    <native:column class="flex-1 gap-1 min-w-0">
                        <native:text class="text-sm font-medium text-theme-on-surface" max-lines="1">{{ $user['name'] ?? $user['username'] }}</native:text>
                        <native:text class="text-xs text-theme-on-surface-variant" max-lines="1">{{ '@'.$user['username'] }}</native:text>
                    </native:column>
                </native:row>
            </native:pressable>
        @endforeach
    </native:column>
@endif
