@php
    $isFollowing = ($mode ?? 'followers') === 'following';

    // Web parity (pinkary/resources/views/livewire/followers/index.blade.php:4-12
    // and following/index.blade.php:4-12): the heading itself carries the
    // empty sentence, and the non-empty heading is "@user followers".
    $title = $username !== '' ? '@'.$username.($isFollowing ? ' following' : ' followers') : ($isFollowing ? 'Following' : 'Followers');
    $emptyTitle = $username !== ''
        ? '@'.$username.($isFollowing ? ' is not following anyone yet.' : ' does not have any followers')
        : ($isFollowing ? 'This user is not following anyone yet.' : 'This user does not have any followers');
    $emptySubtitle = $isFollowing
        ? 'People this user follows will show up here.'
        : 'People who follow this user will show up here.';
@endphp

<native:list on-end-reached="loadMore" class="w-full h-full bg-theme-surface">
    <native:column class="w-full gap-0">
        <x-page-heading :title="$title" :back="true" />

        @if ($refreshing ?? false)
            <x-refresh-bar />
        @endif

        @if ($loading)
            <native:column class="w-full gap-0">
                <x-skeleton-user-row />
                <x-skeleton-user-row />
                <x-skeleton-user-row />
                <x-skeleton-user-row />
                <x-skeleton-user-row />
            </native:column>
        @elseif ($errorMessage !== '')
            <native:column class="mx-4 my-5 p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry" a11y-label="Try again">
                    <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                </native:pressable>
            </native:column>
        @elseif (count($users) === 0)
            <x-empty-state :title="$emptyTitle" :subtitle="$emptySubtitle" />
        @else
            {{-- Web parity: the followers/following/likes lists are inset
                CARDS, not full-bleed rows
                (pinkary/resources/views/livewire/followers/index.blade.php:25,
                following/index.blade.php:25, likes/index.blade.php:26 —
                `rounded-xl border bg-slate-50/80 p-3` in a `gap-2` column). --}}
            <native:column class="w-full gap-2 px-4 py-3">
            @foreach ($users as $user)
                @php
                    $rowUsername = (string) data_get($user, 'username', '');
                    $rowFollowed = (bool) data_get($user, 'followed_by_me', false);
                    $rowIsMe = (bool) data_get($user, 'is_me', false);
                    $rowBusy = in_array($rowUsername, $busyUsernames ?? [], true);
                    // The web shows this only on someone else's list: on your
                    // own follower sheet every row follows you by definition,
                    // so the badge would be true of all of them and mean
                    // nothing (followers/index.blade.php:55).
                    $rowFollowsMe = (bool) data_get($user, 'follows_me', false) && ! $isOwnProfile;
                @endphp
                <native:row class="w-full items-center gap-3 p-3 rounded-xl bg-theme-surface border border-theme-outline-variant">
                    <native:pressable class="flex-1 min-w-0" @tap="openProfile('{{ $rowUsername }}')" a11y-label="Open {{ '@'.$rowUsername }}’s profile">
                        <native:row class="flex-1 items-center gap-3 min-w-0">
                            <x-avatar :user="$user" company-radius="md" :link="false" />
                            <native:column class="flex-1 gap-0.5 min-w-0">
                                <native:row class="items-center gap-1.5">
                                    <native:text class="text-sm font-medium text-theme-on-surface" max-lines="1">{{ data_get($user, 'name') }}</native:text>
                                    {{-- Web parity: the seal requires profile
                                        verification; a company-only account gets
                                        no seal (components/avatar-with-name.blade.php:19-23,
                                        resources/views/livewire/questions/show.blade.php:124-133). --}}
                                    @if (data_get($user, 'verification.profile') || data_get($user, 'verified'))
                                        <native:icon :ios="\App\Icons\Ios::CheckmarkSealFill" :android="\App\Icons\Android::Verified" :size="14" color="#EC4899" />
                                    @endif
                                </native:row>
                                {{-- Web parity: the username line carries a
                                    "Follows you" badge when the person on the
                                    row also follows the profile's owner
                                    (followers/index.blade.php:55-57, and the
                                    same line in following/index.blade.php and
                                    likes/index.blade.php). It sits on the
                                    username's own line, not in a row of its
                                    own. The web hardcodes the pill as
                                    bg-slate-900 text-slate-50
                                    (components/badge.blade.php:2) and never
                                    flips it; this themes it with the
                                    secondary pair instead, which is the same
                                    dark-on-light inversion in both modes. --}}
                                @if ($rowFollowsMe)
                                    <native:row class="items-center gap-1.5 min-w-0">
                                        <native:text class="text-sm text-theme-on-surface-variant shrink-0" max-lines="1">{{ '@'.$rowUsername }}</native:text>
                                        <native:text class="px-2 py-1 leading-none text-xs rounded-full bg-theme-secondary text-theme-on-secondary" max-lines="1">Follows you</native:text>
                                    </native:row>
                                @else
                                    <native:text class="text-sm text-theme-on-surface-variant" max-lines="1">{{ '@'.$rowUsername }}</native:text>
                                @endif
                            </native:column>
                        </native:row>
                    </native:pressable>
                    @unless ($rowIsMe || $rowUsername === '')
                        {{-- Web parity (components/follow-button.blade.php:9-16):
                            ONE outlined pill for both states, and the label
                            never changes width — busy dims the row instead
                            of swapping in an ellipsis. The follow direction
                            the button cannot express is carried by the
                            "Follows you" badge beside the username, exactly
                            as the web does; the web has no "Follow Back"
                            state either. --}}
                        <native:pressable class="shrink-0 px-3 py-1.5 rounded-full border border-theme-outline-variant text-nowrap {{ $rowBusy ? 'opacity-40' : '' }}" @tap="toggleFollow('{{ $rowUsername }}')" a11y-label="{{ $rowFollowed ? 'Unfollow' : 'Follow' }}">
                            <native:text class="text-xs font-medium text-theme-on-surface-variant">{{ $rowFollowed ? 'Unfollow' : 'Follow' }}</native:text>
                        </native:pressable>
                    @endunless
                </native:row>
            @endforeach
            </native:column>

            <x-list-footer :loading="$loadingMore" :error="$loadMoreError" tap="loadMore" :has-more="$hasMore" />
        @endif

        <native:column class="w-full h-20" />
    </native:column>
</native:list>

@include('native.app-menu-sheet')
