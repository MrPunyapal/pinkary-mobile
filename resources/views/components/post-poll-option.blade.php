@props(['text' => '', 'percent' => 0, 'mine' => false, 'disabled' => false])

<native:stack class="w-full rounded-lg bg-theme-poll-track {{ $disabled ? 'opacity-60' : '' }}">
    <native:row class="min-h-10 w-full">
        <native:column
            class="min-h-10 rounded-lg {{ $mine ? 'bg-theme-primary/90' : 'bg-theme-poll-fill' }}"
            style="width: {{ (int) $percent }}%"
        />
        <native:spacer class="flex-1" />
    </native:row>
    <native:row class="min-h-10 w-full items-center justify-between gap-3 px-3.5 py-2.5">
        <native:text class="text-sm font-semibold flex-1 {{ $mine ? 'text-white' : 'text-theme-on-body' }}">{{ $text }}</native:text>
        <native:row class="shrink-0 items-center gap-2">
            @if ($mine)
                <native:icon
                    :ios="\App\Icons\Ios::CheckmarkCircleFill"
                    :android="\App\Icons\Android::CheckCircle"
                    :size="16"
                    color="#FFFFFF"
                />
            @endif
            <native:text class="text-xs font-semibold shrink-0 {{ $mine ? 'text-white' : 'text-theme-on-surface-variant' }}">{{ (int) $percent }}%</native:text>
        </native:row>
    </native:row>
</native:stack>
