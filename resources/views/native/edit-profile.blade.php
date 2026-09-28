<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        <x-page-heading title="Edit profile" :back="true" />

        @if ($refreshing ?? false)
            <x-refresh-bar />
        @endif

        <native:column class="w-full px-5 py-6 gap-5">
        @if ($loading)
            <x-skeleton-settings />
        @elseif ($errorMessage !== '' && $name === '' && $email === '')
            <native:column class="w-full p-4 gap-3 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                <native:pressable class="self-start py-1" @tap="retry">
                    <native:text class="text-sm font-semibold text-theme-destructive">Try again</native:text>
                </native:pressable>
            </native:column>
        @else
            @if ($errorMessage !== '')
                <native:column class="w-full p-4 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                    <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
                </native:column>
            @endif

            @if ($successMessage !== '')
                <native:column class="w-full p-4 rounded-md bg-theme-primary/10 border border-theme-primary/30">
                    <native:text class="text-sm text-theme-primary">{{ $successMessage }}</native:text>
                </native:column>
            @endif

            {{-- Avatar is read-only: no file picker exists on native
                clients, so photos stay web-only for now. --}}
            <native:row class="w-full items-center gap-3 p-4 rounded-md bg-theme-surface border border-theme-outline-variant">
                <x-avatar :user="['avatar' => $avatar, 'name' => $name]" :size="12" :link="false" />
                <native:column class="flex-1 gap-0.5 min-w-0">
                    <native:text class="text-base font-medium text-theme-on-surface">{{ $name }}</native:text>
                    <native:text class="text-sm text-theme-on-surface-variant">{{ $username !== '' ? '@'.$username : '' }}</native:text>
                    <native:text class="text-xs text-theme-on-surface-variant">Profile photos can’t be changed on mobile yet.</native:text>
                </native:column>
            </native:row>

            {{-- Web parity (profile/partials/update-profile-information-form):
                the heading and its sub-line, the same field order, the
                three preference selects, and the Save button. --}}
            <native:column class="w-full gap-4 p-4 rounded-md bg-theme-surface border border-theme-outline-variant">
                <native:column class="w-full gap-1">
                    <native:text class="text-lg font-medium text-theme-on-surface-variant">Profile Information</native:text>
                    <native:text class="text-sm text-theme-on-surface-variant">Update your account’s profile information and email address.</native:text>
                </native:column>

                <native:outlined-text-input label="Name" native:model="name" :supporting="$fieldErrors['name'] ?? ''" :error="isset($fieldErrors['name'])" />
                <native:outlined-text-input label="Username" native:model="username" :supporting="$fieldErrors['username'] ?? ''" :error="isset($fieldErrors['username'])" />
                <native:outlined-text-input label="Email" keyboard="email" native:model="email" :supporting="$fieldErrors['email'] ?? ''" :error="isset($fieldErrors['email'])" />
                <native:outlined-text-input label="Bio" placeholder="Tell people about yourself" multiline native:model="bio" :supporting="$fieldErrors['bio'] ?? ''" :error="isset($fieldErrors['bio'])" />

                <native:column class="w-full gap-1">
                    <native:text class="text-sm font-medium text-theme-on-surface">Mail Preference Time</native:text>
                    <native:select label="Mail Preference Time" :options="$mailPreferenceOptions" native:model="mailPreferenceTime" />
                </native:column>

                <native:column class="w-full gap-1">
                    <native:text class="text-sm font-medium text-theme-on-surface">Default Feed</native:text>
                    <native:select label="Default Feed" :options="$defaultFeedOptions" native:model="defaultFeed" />
                </native:column>

                <native:column class="w-full gap-1">
                    <native:text class="text-sm font-medium text-theme-on-surface">How would you like to do questions by default?</native:text>
                    <native:select label="How would you like to do questions by default?" :options="$questionVisibilityOptions" native:model="questionVisibility" />
                </native:column>

                {{-- Busy rides on :disabled so the label never reflows. --}}
                <native:button variant="primary" :disabled="$busy" @tap="save">Save</native:button>
            </native:column>

            <native:column class="w-full gap-3 p-4 rounded-md bg-theme-surface border border-theme-outline-variant">
                <native:text class="text-sm font-semibold uppercase tracking-widest text-theme-on-surface-variant">Links</native:text>

                @if ($linkMessage !== '')
                    <native:text class="text-sm text-theme-on-surface-variant">{{ $linkMessage }}</native:text>
                @endif

                @if (count($links) === 0)
                    <native:text class="text-sm text-theme-on-surface-variant">No links yet. Add your first one below.</native:text>
                @else
                    @foreach ($links as $link)
                        @php $linkId = (int) data_get($link, 'id', 0); @endphp
                        <native:column class="w-full gap-2 p-3 rounded-md border border-theme-outline-variant">
                            @if (($editingLinkId ?? null) === $linkId)
                                <native:outlined-text-input label="Description" native:model="editDescription" />
                                <native:outlined-text-input label="URL" keyboard="url" native:model="editUrl" />
                                @if ($editUrlTouched)
                                    <native:text class="text-sm text-theme-on-surface-variant">Editing the URL will reset the click counter to zero</native:text>
                                @endif
                                <native:row class="w-full items-center gap-2">
                                    <native:pressable class="px-3 py-1.5 rounded-full bg-theme-primary {{ $linkBusy ? 'opacity-40' : '' }}" @tap="saveEdit({{ $linkId }})" a11y-label="Save link">
                                        <native:text class="text-xs font-medium text-white">Save</native:text>
                                    </native:pressable>
                                    <native:pressable class="px-3 py-1.5" @tap="cancelEdit" a11y-label="Cancel editing">
                                        <native:text class="text-xs font-medium text-theme-on-surface-variant">Cancel</native:text>
                                    </native:pressable>
                                </native:row>
                            @else
                                <native:text class="text-sm font-medium text-theme-on-surface">{{ data_get($link, 'description', '') }}</native:text>
                                <native:text class="text-xs text-theme-on-surface-variant">{{ data_get($link, 'url', '') }}</native:text>
                                @php $linkClicks = data_get($link, 'click_count'); @endphp
                                @if (is_int($linkClicks) || (is_string($linkClicks) && $linkClicks !== ''))
                                    <native:text class="text-xs text-theme-on-surface-variant">{{ $linkClicks }} {{ (int) $linkClicks === 1 ? 'click' : 'clicks' }}</native:text>
                                @endif
                                <native:row class="w-full items-center gap-2">
                                    <native:pressable class="px-3 py-1.5" @tap="startEdit({{ $linkId }})" a11y-label="Edit link">
                                        <native:text class="text-xs font-medium text-theme-primary">Edit</native:text>
                                    </native:pressable>
                                    <native:pressable class="px-3 py-1.5 rounded-full {{ ($confirmingDeleteId ?? null) === $linkId ? 'bg-theme-destructive' : '' }}" @tap="deleteLink({{ $linkId }})" a11y-label="Delete link">
                                        <native:text class="text-xs font-medium {{ ($confirmingDeleteId ?? null) === $linkId ? 'text-white' : 'text-theme-destructive' }}">{{ ($confirmingDeleteId ?? null) === $linkId ? 'Tap again to confirm' : 'Delete' }}</native:text>
                                    </native:pressable>
                                </native:row>
                            @endif
                        </native:column>
                    @endforeach
                @endif

                {{-- Link shape (web link-settings/edit): a profile setting on
                    the API, so it keeps its own Save. Gradients stay web-only
                    — native clients cannot render them. --}}
                <native:column class="w-full gap-2 pt-3 border-t border-theme-outline-variant">
                    <native:text class="text-base font-semibold text-theme-on-surface-variant">Link Shape</native:text>
                    <native:text class="text-sm text-theme-on-surface-variant">In what shape do you want to present your links?</native:text>
                    <native:select label="Link Shape" :options="$linkShapeOptions" native:model="linkShape" />
                    <native:pressable class="self-start px-4 py-2 rounded-full bg-theme-primary {{ $linkBusy ? 'opacity-40' : '' }}" @tap="saveLinkShape" a11y-label="Save link shape">
                        <native:text class="text-sm font-medium text-white">Save</native:text>
                    </native:pressable>
                </native:column>

                <native:column class="w-full gap-2 pt-3 border-t border-theme-outline-variant">
                    <native:outlined-text-input label="Description" placeholder="My website" native:model="newDescription" />
                    <native:outlined-text-input label="URL" placeholder="https://example.com" keyboard="url" native:model="newUrl" />
                    <native:button variant="secondary" :disabled="$linkBusy" @tap="addLink">Create</native:button>
                </native:column>
            </native:column>

            <native:pressable class="w-full items-center py-2" @tap="viewProfile" a11y-label="View profile">
                <native:text class="text-sm font-medium text-theme-primary">View profile</native:text>
            </native:pressable>
        @endif

        <native:column class="w-full h-10" />
        </native:column>
    </native:column>
</native:scroll-view>

{{-- This screen keeps the Menu tab in the bottom bar, so the sheet has to
    be reachable here too — EditProfile uses UsesAppMenu, so $appMenuOpen is
    live and tapping Menu would otherwise do nothing. --}}
@include('native.app-menu-sheet')
