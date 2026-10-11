@props([
    'profile' => [],
    'isMe' => false,
    'followed' => false,
    'followBusy' => false,
    'showAvatar' => false,
    'linkError' => '',
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

    $company = (bool) data_get($profile, 'verification.company', false);
    $verified = (bool) data_get($profile, 'verification.profile', false);
    $sealColor = $company ? '#EAB308' : '#EC4899';
    $followers = (int) data_get($profile, 'stats.followers', 0);
    $following = (int) data_get($profile, 'stats.following', 0);
    $posts = (int) data_get($profile, 'stats.posts', 0);
    $views = (int) data_get($profile, 'stats.views', 0);
    $username = (string) data_get($profile, 'username', '');
    $links = array_values(array_filter(
        (array) data_get($profile, 'links', []),
        fn (mixed $link): bool => is_array($link) && ($link['url'] ?? '') !== ''
    ));

    $statPills = [];

    if ($followers > 0) {
        $statPills[] = ['label' => $abbreviate($followers).' '.($followers === 1 ? 'Follower' : 'Followers'), 'tap' => 'openFollowers', 'a11y' => 'Open followers'];
    }

    if ($following > 0) {
        $statPills[] = ['label' => $abbreviate($following).' Following', 'tap' => 'openFollowing', 'a11y' => 'Open following'];
    }

    if ($posts > 0) {
        $statPills[] = ['label' => $abbreviate($posts).' '.($posts === 1 ? 'Post' : 'Posts'), 'tap' => null, 'a11y' => null];
    }

    $statPills[] = ['label' => $abbreviate($views).' '.($views === 1 ? 'View' : 'Views'), 'tap' => null, 'a11y' => null];
@endphp

<native:column class="bg-theme-surface border-theme-outline-variant w-full gap-0 rounded-md border p-4">
    <native:row class="w-full items-center justify-between">
        <native:row class="items-center gap-2">
            <native:pressable
                class="bg-theme-background border-theme-outline-variant h-11 w-11 items-center justify-center rounded-md border"
                @tap="shareProfile"
                a11y-label="Share profile"
            >
                <native:icon
                    :ios="\App\Icons\Ios::SquareAndArrowUp"
                    :android="\App\Icons\AndroidOutlined::Share"
                    :size="20"
                    color="#64748B"
                    dark-color="#94A3B8"
                />
            </native:pressable>
            @if ($isMe)
                <native:pressable
                    class="bg-theme-background border-theme-outline-variant h-11 w-11 items-center justify-center rounded-md border"
                    @tap="toggleQr"
                    a11y-label="Show profile QR code"
                >
                    <native:icon
                        :ios="\App\Icons\Ios::Qrcode"
                        :android="\App\Icons\AndroidOutlined::QrCode"
                        :size="20"
                        color="#64748B"
                        dark-color="#94A3B8"
                    />
                </native:pressable>
            @endif
        </native:row>

        <native:row class="items-center gap-2">
            @unless ($isMe)
                @if (data_get($profile, 'can_ask_question') !== false)
                    <native:pressable
                        class="border-theme-primary rounded-full border px-3 py-2"
                        @tap="askUser"
                        a11y-label="Ask {{ '@'.$username }}"
                    >
                        <native:text class="text-theme-primary text-sm font-medium">Ask {{ '@'.$username }}</native:text>
                    </native:pressable>
                @endif
                <native:pressable
                    class="px-4 py-2 rounded-full bg-theme-primary {{ $followBusy ? 'opacity-40' : '' }}"
                    @tap="toggleFollow"
                    a11y-label="{{ $followed ? 'Unfollow' : 'Follow' }}"
                >
                    <native:text class="text-sm font-medium text-white">{{ $followBusy ? '…' : ($followed ? 'Unfollow' : 'Follow') }}</native:text>
                </native:pressable>
            @endunless
            @if ($isMe)
                <native:pressable
                    class="border-theme-outline-variant bg-theme-background rounded-full border px-4 py-2"
                    @tap="editProfile"
                    a11y-label="Edit profile"
                >
                    <native:text class="text-theme-on-surface text-sm font-medium">Edit profile</native:text>
                </native:pressable>
            @endif
        </native:row>
    </native:row>

    <native:column class="w-full items-center gap-0">
        <native:pressable @tap="showAvatar" a11y-label="Show {{ '@'.$username }}’s avatar">
            <x-avatar :user="$profile" :size="20" company-radius="3xl" :link="false" />
        </native:pressable>

        <native:row class="mt-4 w-full items-center justify-center gap-1.5">
            <native:text class="text-theme-on-surface text-2xl font-semibold">{{ data_get($profile, 'name') }}</native:text>
            @if ($verified)
                <native:icon
                    :ios="\App\Icons\Ios::CheckmarkSealFill"
                    :android="\App\Icons\Android::Verified"
                    :size="24"
                    :color="$sealColor"
                />
            @endif
        </native:row>

        <native:row class="bg-theme-surface-variant border-theme-outline-variant mt-2 items-center rounded-full border px-3 py-1">
            <native:text class="text-theme-on-surface-variant text-sm">{{ '@'.$username }}</native:text>
        </native:row>

        @if (data_get($profile, 'bio'))
            <native:text class="text-theme-on-surface mt-3 text-center text-sm leading-relaxed">{{ data_get($profile, 'bio') }}</native:text>
        @elseif ($isMe)
            <native:pressable class="mt-3 self-center py-1" @tap="editProfile" a11y-label="Tell people about yourself">
                <native:text class="text-theme-primary text-center text-sm">Tell people about yourself</native:text>
            </native:pressable>
        @endif

        <native:row class="mt-4 flex-wrap items-center justify-center gap-2">
            @foreach ($statPills as $pill)
                @if ($pill['tap'] !== null)
                    <native:pressable @tap="{{ $pill['tap'] }}" a11y-label="{{ $pill['a11y'] }}">
                        <native:row class="bg-theme-surface-variant border-theme-outline-variant items-center rounded-full border px-3 py-1">
                            <native:text class="text-theme-on-surface-variant text-sm">{{ $pill['label'] }}</native:text>
                        </native:row>
                    </native:pressable>
                @else
                    <native:row class="bg-theme-surface-variant border-theme-outline-variant items-center rounded-full border px-3 py-1">
                        <native:text class="text-theme-on-surface-variant text-sm">{{ $pill['label'] }}</native:text>
                    </native:row>
                @endif
            @endforeach
        </native:row>
    </native:column>

    @if (count($links) > 0)
        <native:column class="border-theme-outline-variant mt-4 w-full gap-2 border-t pt-4">
            @if ($isMe && $linkError !== '')
                <native:text class="text-theme-destructive text-sm">{{ $linkError }}</native:text>
            @endif
            @foreach ($links as $link)
                @php
                    $linkId = (int) data_get($link, 'id', 0);
                    $shape = (string) data_get($link, 'link_shape', data_get($profile, 'link_shape', 'rounded-lg'));
                    $linkRadius = in_array($shape, ['rounded-none', 'rounded-lg', 'rounded-full'], true) ? $shape : 'rounded-lg';
                    $clicks = $isMe ? data_get($link, 'click_count') : null;
                    $linkVisible = (bool) data_get($link, 'is_visible', true);
                    $linkBusy = (bool) data_get($link, '_busy', false);
                @endphp
                <native:row class="w-full items-center gap-2">
                    <native:pressable
                        class="flex-1 min-w-0 items-center py-3 {{ $linkRadius }} bg-theme-primary"
                        @tap="openUserLink({{ $linkId }})"
                        a11y-label="Open {{ data_get($link, 'description', 'link') }}"
                    >
                        <native:text class="text-sm font-bold text-white">{{ data_get($link, 'description', '') }}</native:text>
                        @if (is_int($clicks) || (is_string($clicks) && $clicks !== ''))
                            <native:text class="text-xs text-white/80">{{ $clicks }} {{ (int) $clicks === 1 ? 'click' : 'clicks' }}</native:text>
                        @endif
                    </native:pressable>
                    @if ($isMe)
                        <native:pressable
                            class="w-9 h-9 items-center justify-center shrink-0 {{ $linkBusy ? 'opacity-40' : '' }}"
                            @tap="toggleLinkVisibility({{ $linkId }})"
                            a11y-label="{{ $linkVisible ? 'Hide link' : 'Show link' }}"
                        >
                            @if ($linkVisible)
                                <native:icon
                                    :ios="\App\Icons\Ios::Eye"
                                    :android="\App\Icons\AndroidOutlined::Visibility"
                                    :size="18"
                                    color="#64748B"
                                    dark-color="#94A3B8"
                                />
                            @else
                                <native:icon
                                    :ios="\App\Icons\Ios::EyeSlash"
                                    :android="\App\Icons\AndroidOutlined::VisibilityOff"
                                    :size="18"
                                    color="#64748B"
                                    dark-color="#94A3B8"
                                />
                            @endif
                        </native:pressable>
                        <native:pressable
                            class="h-9 w-9 shrink-0 items-center justify-center"
                            @tap="editProfile"
                            a11y-label="Edit links"
                        >
                            <native:icon
                                :ios="\App\Icons\Ios::Pencil"
                                :android="\App\Icons\AndroidOutlined::Edit"
                                :size="18"
                                color="#64748B"
                                dark-color="#94A3B8"
                            />
                        </native:pressable>
                    @endif
                </native:row>
            @endforeach
        </native:column>
    @elseif ($isMe)
        <native:column class="border-theme-outline mt-4 w-full items-center gap-2 rounded-md border border-dashed p-5">
            <native:text class="text-theme-on-surface text-lg font-medium">No links yet.</native:text>
            <native:text class="text-theme-on-surface-variant text-center text-sm">Add your first link to complete the profile card.</native:text>
        </native:column>
    @endif

    @if ($isMe)
        <native:pressable
            class="bg-theme-primary mt-4 w-full items-center justify-center gap-2 rounded-md py-3"
            @tap="editProfile"
            a11y-label="Add New Link"
        >
            <native:icon
                :ios="\App\Icons\Ios::Plus"
                :android="\App\Icons\AndroidOutlined::Add"
                :size="18"
                color="#FFFFFF"
            />
            <native:text class="text-sm font-bold text-white">Add New Link</native:text>
        </native:pressable>
    @endif

    @if ($showAvatar)
        <native:bottom-sheet visible @dismiss="toggleAvatar">
            <native:column class="bg-theme-surface w-full gap-3 px-5 py-4">
                <native:column class="border-theme-outline-variant w-full items-center gap-2 rounded-md border p-3">
                    <x-avatar :user="$profile" :size="24" company-radius="3xl" :link="false" />
                    <native:text class="text-theme-on-surface-variant text-center text-sm">{{ '@'.$username }}</native:text>
                </native:column>
                <native:button variant="secondary" @tap="toggleAvatar">Close</native:button>
            </native:column>
        </native:bottom-sheet>
    @endif
</native:column>
