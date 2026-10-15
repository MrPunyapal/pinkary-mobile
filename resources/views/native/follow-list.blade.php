@php
    $isFollowing = ($mode ?? 'followers') === 'following';

    $title = $username !== '' ? '@'.$username.($isFollowing ? ' following' : ' followers') : ($isFollowing ? 'Following' : 'Followers');
    $emptyTitle = $username !== ''
        ? '@'.$username.($isFollowing ? ' is not following anyone yet.' : ' does not have any followers')
        : ($isFollowing ? 'This user is not following anyone yet.' : 'This user does not have any followers');
    $emptySubtitle = $isFollowing
        ? 'People this user follows will show up here.'
        : 'People who follow this user will show up here.';
@endphp

<native:list on-end-reached="loadMore" :end-reached-buffer="1" class="bg-theme-surface h-full w-full">
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
            <native:column class="bg-theme-destructive/15 border-theme-destructive mx-4 my-5 gap-3 rounded-md border p-4">
                <native:text class="text-theme-destructive text-sm">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry" a11y-label="Try again">
                    <native:text class="text-theme-destructive text-sm font-semibold">Try again</native:text>
                </native:pressable>
            </native:column>
        @elseif (count($users) === 0)
            <x-empty-state :title="$emptyTitle" :subtitle="$emptySubtitle" />
        @else
            <native:column class="w-full gap-2 px-4 py-3">
                @foreach ($users as $user)
                    @php
                        $rowUsername = (string) data_get($user, 'username', '');
                        $rowFollowed = (bool) data_get($user, 'followed_by_me', false);
                        $rowIsMe = (bool) data_get($user, 'is_me', false);
                        $rowBusy = in_array($rowUsername, $busyUsernames ?? [], true);
                        $rowFollowsMe = (bool) data_get($user, 'follows_me', false);
                    @endphp
                    <native:row class="bg-theme-surface border-theme-outline-variant w-full items-center gap-3 rounded-xl border p-3">
                        <native:pressable
                            class="min-w-0 flex-1"
                            @tap="openProfile('{{ $rowUsername }}')"
                            a11y-label="Open {{ '@'.$rowUsername }}’s profile"
                        >
                            <native:row class="min-w-0 flex-1 items-center gap-3">
                                <x-avatar :user="$user" company-radius="md" :link="false" />
                                <native:column class="min-w-0 flex-1 gap-0.5">
                                    <native:row class="items-center gap-1.5">
                                        <native:text
                                            class="text-theme-on-surface text-sm font-medium"
                                            max-lines="1"
                                        >{{ data_get($user, 'name') }}</native:text>
                                        @if (data_get($user, 'verification.profile') || data_get($user, 'verified'))
                                            <native:icon
                                                :ios="\App\Icons\Ios::CheckmarkSealFill"
                                                :android="\App\Icons\Android::Verified"
                                                :size="14"
                                                color="#EC4899"
                                            />
                                        @endif
                                    </native:row>
                                    @if ($rowFollowsMe && ! $rowIsMe)
                                        <native:row class="min-w-0 items-center gap-1.5">
                                            <native:text
                                                class="text-theme-on-surface-variant shrink-0 text-sm"
                                                max-lines="1"
                                            >{{ '@'.$rowUsername }}</native:text>
                                            <native:text
                                                class="bg-theme-secondary text-theme-on-secondary rounded-full px-2 py-1 text-xs leading-none"
                                                max-lines="1"
                                            >Follows you</native:text>
                                        </native:row>
                                    @else
                                        <native:text
                                            class="text-theme-on-surface-variant text-sm"
                                            max-lines="1"
                                        >{{ '@'.$rowUsername }}</native:text>
                                    @endif
                                </native:column>
                            </native:row>
                        </native:pressable>
                        @unless ($rowIsMe || $rowUsername === '')
                            <native:pressable
                                class="shrink-0 px-3 py-1.5 rounded-full border border-theme-outline-variant text-nowrap {{ $rowBusy ? 'opacity-40' : '' }}"
                                @tap="toggleFollow('{{ $rowUsername }}')"
                                a11y-label="{{ $rowFollowed ? 'Unfollow' : ($rowFollowsMe ? 'Follow Back' : 'Follow') }}"
                            >
                                <native:text class="text-theme-on-surface-variant text-xs font-medium">{{ $rowFollowed ? 'Unfollow' : ($rowFollowsMe ? 'Follow Back' : 'Follow') }}</native:text>
                            </native:pressable>
                        @endunless
                    </native:row>
                @endforeach
            </native:column>

            <x-list-footer :loading="$loadingMore" :error="$loadMoreError" tap="loadMore" :has-more="$hasMore" />
        @endif

        <native:column class="h-20 w-full" />
    </native:column>
</native:list>

@include('native.app-menu-sheet')
