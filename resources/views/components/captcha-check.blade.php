@props(['required', 'loading', 'completed', 'url', 'error'])

@if ($required || $loading || $error !== '')
    <native:column class="w-full gap-2">
        @if ($loading)
            <native:row class="items-center gap-2">
                <native:activity-indicator />
                <native:text class="text-xs text-theme-on-surface-variant">Loading security check…</native:text>
            </native:row>
        @elseif ($completed)
            <native:text class="text-xs text-theme-on-surface-variant">Security check complete.</native:text>
        @elseif ($url !== '')
            <native:webview :src="$url" javascript dom-storage class="w-full h-40" @navigated="captchaNavigated" a11y-label="Security check" />
        @endif

        @if ($error !== '')
            <native:text class="text-xs text-theme-destructive">{{ $error }}</native:text>
        @endif
        @if (! $loading && ! $completed)
            <native:pressable class="py-2" @tap="retryCaptcha" a11y-label="Retry security check">
                <native:text class="text-xs font-medium text-theme-primary">Retry security check</native:text>
            </native:pressable>
        @endif
    </native:column>
@endif
