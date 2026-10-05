@props(['poll', 'postId', 'guest' => null])

@php
    $options = (array) data_get($poll, 'options', []);
    $totalVotes = (int) data_get($poll, 'total_votes', 0);
    $total = max(1, $totalVotes);
    $userVote = data_get($poll, 'user_vote_option_id');
    $expired = (bool) data_get($poll, 'expired', false);
    $timeRemaining = data_get($poll, 'time_remaining');

    $isGuest = $guest === null
        ? \App\Support\AccountStore::count() === 0
        : (bool) $guest;
    $disabled = $expired || $isGuest;
@endphp

<native:column class="w-full gap-2 pt-2.5">
    @foreach ($options as $option)
        @php
            $optionId = (int) data_get($option, 'id', 0);
            $votes = (int) data_get($option, 'votes', 0);
            $percent = (int) round($votes / $total * 100);
            $mine = $userVote !== null && (int) $userVote === $optionId;
        @endphp
        @if ($disabled)
            <x-post-poll-option :text="data_get($option, 'text', '')" :percent="$percent" :mine="$mine" disabled />
        @else
            <native:pressable @tap="votePoll('{{ $postId }}', {{ $optionId }})"
                a11y-label="Vote for {{ data_get($option, 'text', '') }}"
            >
                <x-post-poll-option :text="data_get($option, 'text', '')" :percent="$percent" :mine="$mine" />
            </native:pressable>
        @endif
    @endforeach
    <native:row class="w-full items-center gap-1.5 pt-2">
        <native:text class="text-theme-on-surface-variant text-sm"
            >{{ $totalVotes }} {{ $totalVotes === 1 ? 'vote' : 'votes' }}</native:text>
        @if ($expired)
            <native:text class="text-theme-on-surface-variant text-sm">·</native:text>
            <native:text class="text-sm text-red-500" dark-color="#F87171">Poll expired</native:text>
        @elseif (is_string($timeRemaining) && $timeRemaining !== '')
            <native:text class="text-theme-on-surface-variant text-sm">·</native:text>
            <native:text class="text-theme-on-surface-variant text-sm">Ends {{ $timeRemaining }}</native:text>
        @endif
        @if (! $expired && $isGuest)
            <native:pressable @tap="openLogin()" a11y-label="Sign in to vote">
                <native:text class="text-theme-primary text-sm">· Sign in to vote</native:text>
            </native:pressable>
        @endif
    </native:row>
</native:column>
