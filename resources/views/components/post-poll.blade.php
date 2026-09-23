@props(['poll', 'postId'])

@php
    $options = (array) data_get($poll, 'options', []);
    $totalVotes = (int) data_get($poll, 'total_votes', 0);
    $total = max(1, $totalVotes);
    $userVote = data_get($poll, 'user_vote_option_id');
    $expired = (bool) data_get($poll, 'expired', false);
    $timeRemaining = data_get($poll, 'time_remaining');
@endphp

<native:column class="w-full gap-2">
    @foreach ($options as $option)
        @php
            $optionId = (int) data_get($option, 'id', 0);
            $votes = (int) data_get($option, 'votes', 0);
            $percent = (int) round($votes / $total * 100);
            $mine = $userVote !== null && (int) $userVote === $optionId;
        @endphp
        {{-- Expired polls render static (web disables voting); live polls stay tappable. --}}
        @if ($expired)
            <x-post-poll-option :text="data_get($option, 'text', '')" :percent="$percent" :mine="$mine" />
        @else
            <native:pressable @tap="votePoll('{{ $postId }}', {{ $optionId }})" a11y-label="Vote for {{ data_get($option, 'text', '') }}">
                <x-post-poll-option :text="data_get($option, 'text', '')" :percent="$percent" :mine="$mine" />
            </native:pressable>
        @endif
    @endforeach
    <native:row class="w-full items-center gap-1.5 pt-2">
        <native:text class="text-sm text-theme-on-surface-variant">{{ $totalVotes }} {{ $totalVotes === 1 ? 'vote' : 'votes' }}</native:text>
        @if ($expired)
            <native:text class="text-sm text-theme-on-surface-variant">·</native:text>
            <native:text class="text-sm text-theme-destructive">Poll expired</native:text>
        @elseif (is_string($timeRemaining) && $timeRemaining !== '')
            <native:text class="text-sm text-theme-on-surface-variant">·</native:text>
            <native:text class="text-sm text-theme-on-surface-variant">Ends {{ $timeRemaining }}</native:text>
        @endif
    </native:row>
</native:column>
