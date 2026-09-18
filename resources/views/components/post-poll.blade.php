@props(['poll', 'postId'])

@php
    $options = (array) data_get($poll, 'options', []);
    $totalVotes = (int) data_get($poll, 'total_votes', 0);
    $total = max(1, $totalVotes);
    $userVote = data_get($poll, 'user_vote_option_id');
    $expired = (bool) data_get($poll, 'expired', false);
@endphp

<native:column class="w-full gap-2">
    @foreach ($options as $option)
        @php
            $optionId = (int) data_get($option, 'id', 0);
            $votes = (int) data_get($option, 'votes', 0);
            $percent = (int) round($votes / $total * 100);
            $mine = $userVote !== null && (int) $userVote === $optionId;
        @endphp
        <native:pressable @tap="votePoll('{{ $postId }}', {{ $optionId }})" a11y-label="Vote for {{ data_get($option, 'text', '') }}">
            <native:stack class="w-full rounded-lg bg-theme-poll-track">
                <native:row class="w-full min-h-10">
                    <native:column class="min-h-10 rounded-lg {{ $mine ? 'bg-theme-primary' : 'bg-theme-poll-fill' }}" style="width: {{ $percent }}%" />
                    <native:spacer class="flex-1" />
                </native:row>
                <native:row class="w-full min-h-10 items-center justify-between gap-3 px-3.5 py-2.5">
                    <native:row class="flex-1 items-center gap-2 min-w-0">
                        @if ($mine)
                            <native:icon :ios="\App\Icons\Ios::CheckmarkCircleFill" :android="\App\Icons\Android::CheckCircle" :size="16" color="#FFFFFF" />
                        @endif
                        <native:text class="text-sm font-semibold flex-1 {{ $mine ? 'text-white' : 'text-theme-on-surface' }}">{{ data_get($option, 'text', '') }}</native:text>
                    </native:row>
                    <native:text class="text-xs font-semibold shrink-0 {{ $mine ? 'text-white' : 'text-theme-on-surface-variant' }}">{{ $percent }}%</native:text>
                </native:row>
            </native:stack>
        </native:pressable>
    @endforeach
    <native:row class="w-full items-center gap-1.5 pt-2">
        <native:text class="text-sm text-theme-on-surface-variant">{{ $totalVotes }} {{ $totalVotes === 1 ? 'vote' : 'votes' }}</native:text>
        @if ($expired)
            <native:text class="text-sm text-theme-on-surface-variant">·</native:text>
            <native:text class="text-sm text-theme-destructive">Poll expired</native:text>
        @elseif (is_string(data_get($poll, 'time_remaining')) && data_get($poll, 'time_remaining') !== '')
            <native:text class="text-sm text-theme-on-surface-variant">·</native:text>
            <native:text class="text-sm text-theme-on-surface-variant">Ends {{ data_get($poll, 'time_remaining') }}</native:text>
        @endif
    </native:row>
</native:column>
