@php
    $isFollowing = ($mode ?? 'followers') === 'following';
    $title = $username !== '' ? '@'.$username.' · '.($isFollowing ? 'Following' : 'Followers') : ($isFollowing ? 'Following' : 'Followers');
    $emptyTitle = $isFollowing ? 'No following yet.' : 'No followers yet.';
    $emptySubtitle = $username !== ''
        ? ($isFollowing ? '@'.$username.' isn’t following anyone yet.' : 'Nobody follows @'.$username.' yet.')
        : ($isFollowing ? 'This user isn’t following anyone yet.' : 'Nobody follows this user yet.');
@endphp

<native:list on-end-reached="loadMore" class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        <x-page-heading :title="$title" :back="true" />
        @if ($loading)
            <native:column class="w-full items-center gap-3 px-5 py-10">
                <native:activity-indicator />
                <native:text class="text-sm text-theme-on-surface-variant">Loading this list…</native:text>
            </native:column>
        @elseif ($errorMessage !== '')
            <native:column class="w-full mx-4 my-5 p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry">
                    <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                </native:pressable>
            </native:column>
        @elseif (count($users) === 0)
            <x-empty-state :title="$emptyTitle" :subtitle="$emptySubtitle" />
        @else
            @foreach ($users as $user)
                @php
                    $rowUsername = (string) data_get($user, 'username', '');
                    $rowFollowed = (bool) data_get($user, 'followed_by_me', false);
                    $rowIsMe = (bool) data_get($user, 'is_me', false);
                    $rowBusy = in_array($rowUsername, $busyUsernames ?? [], true);
                @endphp
                <native:row class="w-full items-center gap-3 px-4 py-3 bg-theme-background border-b border-theme-outline-variant">
                    <native:pressable class="flex-1 min-w-0" @tap="openProfile('{{ $rowUsername }}')" a11y-label="Open {{ '@'.$rowUsername }}’s profile">
                        <native:row class="flex-1 items-center gap-3 min-w-0">
                            <x-avatar :user="$user" company-radius="md" :link="false" />
                            <native:column class="flex-1 gap-0.5 min-w-0">
                                <native:row class="items-center gap-1.5">
                                    <native:text class="text-sm font-medium text-theme-on-surface">{{ data_get($user, 'name') }}</native:text>
                                    @if (data_get($user, 'verification.profile') || data_get($user, 'verification.company') || data_get($user, 'verified') || data_get($user, 'company_verified'))
                                        <native:icon :ios="\App\Icons\Ios::CheckmarkSealFill" :android="\App\Icons\Android::Verified" :size="14" color="#EC4899" />
                                    @endif
                                </native:row>
                                <native:text class="text-sm text-theme-on-surface-variant">{{ '@'.$rowUsername }}</native:text>
                            </native:column>
                        </native:row>
                    </native:pressable>
                    @unless ($rowIsMe || $rowUsername === '')
                        <native:pressable class="shrink-0 px-3 py-1.5 rounded-full {{ $rowFollowed ? 'border border-theme-outline-variant' : 'bg-theme-primary' }} {{ $rowBusy ? 'opacity-40' : '' }}" @tap="toggleFollow('{{ $rowUsername }}')" a11y-label="{{ $rowFollowed ? 'Unfollow' : 'Follow' }}">
                            <native:text class="text-xs font-medium {{ $rowFollowed ? 'text-theme-on-surface-variant' : 'text-white' }}">{{ $rowBusy ? '…' : ($rowFollowed ? 'Following' : 'Follow') }}</native:text>
                        </native:pressable>
                    @endunless
                </native:row>
            @endforeach

            <x-list-footer :loading="$loadingMore" :error="$loadMoreError" tap="loadMore" :has-more="$hasMore" />
        @endif

        <native:column class="w-full h-20" />
    </native:column>
</native:list>
