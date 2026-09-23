@props([
    'type',
    'postId' => '',
    'count' => 0,
    'active' => false,
    'field' => 'answer',
])

@php
    $abbreviate = function (mixed $value): string {
        $number = (int) $value;

        if ($number < 1000) {
            return (string) $number;
        }

        $symbols = ['', 'K', 'M', 'B', 'T'];
        $tier = (int) (log10(abs($number)) / 3);

        if ($tier <= 0) {
            return (string) $number;
        }

        return number_format($number / (10 ** ($tier * 3)), 1).$symbols[$tier];
    };

    $muted = '#64748B';
    $mutedDark = '#94A3B8';
    $pink = '#EC4899';
    $showCount = (int) $count > 0;
    $countLabel = $showCount ? $abbreviate($count) : '';
    // Web parity (questions/show): only the like keeps a pink count —
    // the bookmark toggles solid vs outline at the muted tone.
    $countClass = ($active && $type !== 'bookmark') ? 'text-theme-primary' : 'text-theme-on-surface-variant';

    $tap = match ($type) {
        'comments' => "openDetail('{$postId}')",
        'like' => "toggleLike('{$postId}')",
        'bookmark' => "toggleBookmark('{$postId}')",
        'translate' => "translatePost('{$postId}', '{$field}')",
        'share' => "sharePost('{$postId}')",
        'menu' => "openMenu('{$postId}')",
        default => null,
    };

    $label = match ($type) {
        'comments' => 'Open comments',
        'like' => $active ? 'Unlike this post' : 'Like this post',
        'bookmark' => $active ? 'Remove bookmark' : 'Bookmark this post',
        'translate' => $field === 'content' ? 'Translate question' : 'Translate answer',
        'share' => 'Share this post',
        'menu' => 'Open post menu',
        'views' => 'Views',
        default => (string) $type,
    };
@endphp

@if ($tap === null)
    <native:row class="items-center gap-1.5">
        @if ($type === 'views')
            <native:icon :ios="\App\Icons\Ios::ChartBar" :android="\App\Icons\Android::BarChart" :size="16" color="{{ $muted }}" dark-color="{{ $mutedDark }}" />
        @endif
        @if ($showCount)
            <native:text class="text-xs text-theme-on-surface-variant">{{ $countLabel }}</native:text>
        @endif
    </native:row>
@else
    <native:pressable @tap="{!! $tap !!}" a11y-label="{{ $label }}">
        <native:row class="items-center gap-1.5">
            @switch ($type)
                @case ('comments')
                    <native:icon :ios="\App\Icons\Ios::BubbleLeftAndBubbleRight" :android="\App\Icons\AndroidOutlined::ChatBubbleOutline" :size="16" color="{{ $muted }}" dark-color="{{ $mutedDark }}" />
                    @break
                @case ('like')
                    @if ($active)
                        <native:icon :ios="\App\Icons\Ios::HeartFill" :android="\App\Icons\Android::Favorite" :size="16" color="{{ $pink }}" />
                    @else
                        <native:icon :ios="\App\Icons\Ios::Heart" :android="\App\Icons\AndroidOutlined::FavoriteBorder" :size="16" color="{{ $muted }}" dark-color="{{ $mutedDark }}" />
                    @endif
                    @break
                @case ('bookmark')
                    {{-- Web parity: solid vs outline at the muted tone, never
                        pink — only the like carries the pink active state. --}}
                    @if ($active)
                        <native:icon :ios="\App\Icons\Ios::BookmarkFill" :android="\App\Icons\Android::Bookmark" :size="16" color="{{ $muted }}" dark-color="{{ $mutedDark }}" />
                    @else
                        <native:icon :ios="\App\Icons\Ios::Bookmark" :android="\App\Icons\AndroidOutlined::Bookmark" :size="16" color="{{ $muted }}" dark-color="{{ $mutedDark }}" />
                    @endif
                    @break
                @case ('translate')
                    <native:icon :ios="\App\Icons\Ios::CharacterBubble" :android="\App\Icons\AndroidOutlined::Translate" :size="16" color="{{ $muted }}" dark-color="{{ $mutedDark }}" />
                    @break
                @case ('share')
                    <native:icon :ios="\App\Icons\Ios::Paperplane" :android="\App\Icons\AndroidOutlined::Send" :size="16" color="{{ $muted }}" dark-color="{{ $mutedDark }}" />
                    @break
                @case ('menu')
                    <native:icon :ios="\App\Icons\Ios::Ellipsis" :android="\App\Icons\AndroidOutlined::MoreHoriz" :size="16" color="{{ $muted }}" dark-color="{{ $mutedDark }}" />
                    @break
            @endswitch
            @if ($showCount)
                <native:text class="text-xs {{ $countClass }}">{{ $countLabel }}</native:text>
            @endif
        </native:row>
    </native:pressable>
@endif
