<native:scroll-view class="bg-theme-background h-full w-full">
    <native:column class="w-full gap-0">
        <x-page-heading title="Edit profile" :back="true" />

        @if ($refreshing ?? false)
            <x-refresh-bar />
        @endif

        <native:column class="w-full gap-5 px-5 py-6">
            @if ($loading)
                <x-skeleton-settings />
            @elseif ($errorMessage !== '' && $name === '' && $email === '')
                <native:column class="bg-theme-destructive/15 border-theme-destructive w-full gap-3 rounded-md border p-4">
                    <native:text class="text-theme-destructive text-sm">{{ $errorMessage }}</native:text>
                    <native:pressable class="self-start py-1" @tap="retry">
                        <native:text class="text-theme-destructive text-sm font-semibold">Try again</native:text>
                    </native:pressable>
                </native:column>
            @else
                @if ($errorMessage !== '')
                    <native:column class="bg-theme-destructive/15 border-theme-destructive w-full rounded-md border p-4">
                        <native:text class="text-theme-destructive text-sm">{{ $errorMessage }}</native:text>
                    </native:column>
                @endif

                @if ($successMessage !== '')
                    <native:column class="bg-theme-primary/10 border-theme-primary/30 w-full rounded-md border p-4">
                        <native:text class="text-theme-primary text-sm">{{ $successMessage }}</native:text>
                    </native:column>
                @endif

                <native:row class="bg-theme-surface border-theme-outline-variant w-full items-center gap-3 rounded-md border p-4">
                    <x-avatar :user="['avatar' => $avatar, 'name' => $name]" :size="12" :link="false" />
                    <native:column class="min-w-0 flex-1 gap-0.5">
                        <native:text class="text-theme-on-surface text-base font-medium">{{ $name }}</native:text>
                        <native:text class="text-theme-on-surface-variant text-sm">{{ $username !== '' ? '@'.$username : '' }}</native:text>
                        <native:text class="text-theme-on-surface-variant text-xs">Profile photos can’t be changed on mobile yet.</native:text>
                    </native:column>
                </native:row>

                <native:column class="bg-theme-surface border-theme-outline-variant w-full gap-4 rounded-md border p-4">
                    <native:column class="w-full gap-1">
                        <native:text class="text-theme-on-surface-variant text-lg font-medium">Profile Information</native:text>
                        <native:text class="text-theme-on-surface-variant text-sm">Update your account’s profile information and email address.</native:text>
                    </native:column>

                    <native:outlined-text-input
                        label="Name"
                        native:model="name"
                        :supporting="$fieldErrors['name'] ?? ''"
                        :error="isset($fieldErrors['name'])"
                    />
                    <native:outlined-text-input
                        label="Username"
                        native:model="username"
                        :supporting="$fieldErrors['username'] ?? ''"
                        :error="isset($fieldErrors['username'])"
                    />
                    <native:outlined-text-input
                        label="Email"
                        keyboard="email"
                        native:model="email"
                        :supporting="$fieldErrors['email'] ?? ''"
                        :error="isset($fieldErrors['email'])"
                    />
                    <native:outlined-text-input
                        label="Bio"
                        placeholder="Tell people about yourself"
                        multiline
                        native:model="bio"
                        :supporting="$fieldErrors['bio'] ?? ''"
                        :error="isset($fieldErrors['bio'])"
                    />

                    <native:column class="w-full gap-1">
                        <native:text class="text-theme-on-surface text-sm font-medium">Mail Preference Time</native:text>
                        <native:select
                            label="Mail Preference Time"
                            :options="$mailPreferenceOptions"
                            native:model="mailPreferenceTime"
                        />
                    </native:column>

                    <native:column class="w-full gap-1">
                        <native:text class="text-theme-on-surface text-sm font-medium">Default Feed</native:text>
                        <native:select label="Default Feed" :options="$defaultFeedOptions" native:model="defaultFeed" />
                    </native:column>

                    <native:column class="w-full gap-1">
                        <native:text class="text-theme-on-surface text-sm font-medium">Who can ask you questions?</native:text>
                        <native:select
                            label="Who can ask you questions?"
                            :options="$questionPreferenceOptions"
                            native:model="questionPreference"
                        />
                    </native:column>

                    <native:column class="w-full gap-1">
                        <native:text class="text-theme-on-surface text-sm font-medium">How would you like to do questions by default?</native:text>
                        <native:select
                            label="How would you like to do questions by default?"
                            :options="$questionVisibilityOptions"
                            native:model="questionVisibility"
                        />
                    </native:column>

                    <native:button variant="primary" :disabled="$busy" @tap="save">Save</native:button>
                </native:column>

                <native:column class="bg-theme-surface border-theme-outline-variant w-full gap-3 rounded-md border p-4">
                    <native:text class="text-theme-on-surface-variant text-sm font-semibold tracking-widest uppercase">Links</native:text>

                    @if ($linkMessage !== '')
                        <native:text class="text-theme-on-surface-variant text-sm">{{ $linkMessage }}</native:text>
                    @endif

                    @if (count($links) === 0)
                        <native:text class="text-theme-on-surface-variant text-sm">No links yet. Add your first one below.</native:text>
                    @else
                        @foreach ($links as $link)
                            @php $linkId = (int) data_get($link, 'id', 0); @endphp
                            <native:column class="border-theme-outline-variant w-full gap-2 rounded-md border p-3">
                                @if (($editingLinkId ?? null) === $linkId)
                                    <native:outlined-text-input label="Description" native:model="editDescription" />
                                    <native:outlined-text-input label="URL" keyboard="url" native:model="editUrl" />
                                    @if ($editUrlTouched)
                                        <native:text class="text-theme-on-surface-variant text-sm">Editing the URL will reset the click counter to zero</native:text>
                                    @endif
                                    <native:row class="w-full items-center gap-2">
                                        <native:pressable
                                            class="px-3 py-1.5 rounded-full bg-theme-primary {{ $linkBusy ? 'opacity-40' : '' }}"
                                            @tap="saveEdit({{ $linkId }})"
                                            a11y-label="Save link"
                                        >
                                            <native:text class="text-xs font-medium text-white">Save</native:text>
                                        </native:pressable>
                                        <native:pressable
                                            class="px-3 py-1.5"
                                            @tap="cancelEdit"
                                            a11y-label="Cancel editing"
                                        >
                                            <native:text class="text-theme-on-surface-variant text-xs font-medium">Cancel</native:text>
                                        </native:pressable>
                                    </native:row>
                                @else
                                    <native:text class="text-theme-on-surface text-sm font-medium">{{ data_get($link, 'description', '') }}</native:text>
                                    <native:text class="text-theme-on-surface-variant text-xs">{{ data_get($link, 'url', '') }}</native:text>
                                    @php $linkClicks = data_get($link, 'click_count'); @endphp
                                    @if (is_int($linkClicks) || (is_string($linkClicks) && $linkClicks !== ''))
                                        <native:text class="text-theme-on-surface-variant text-xs">{{ $linkClicks }} {{ (int) $linkClicks === 1 ? 'click' : 'clicks' }}</native:text>
                                    @endif
                                    <native:row class="w-full items-center gap-2">
                                        <native:pressable
                                            class="px-3 py-1.5"
                                            @tap="startEdit({{ $linkId }})"
                                            a11y-label="Edit link"
                                        >
                                            <native:text class="text-theme-primary text-xs font-medium">Edit</native:text>
                                        </native:pressable>
                                        <native:pressable
                                            class="px-3 py-1.5 rounded-full {{ ($confirmingDeleteId ?? null) === $linkId ? 'bg-theme-destructive' : '' }}"
                                            @tap="deleteLink({{ $linkId }})"
                                            a11y-label="Delete link"
                                        >
                                            <native:text class="text-xs font-medium {{ ($confirmingDeleteId ?? null) === $linkId ? 'text-white' : 'text-theme-destructive' }}">{{ ($confirmingDeleteId ?? null) === $linkId ? 'Tap again to confirm' : 'Delete' }}</native:text>
                                        </native:pressable>
                                    </native:row>
                                @endif
                            </native:column>
                        @endforeach
                    @endif

                    <native:column class="border-theme-outline-variant w-full gap-2 border-t pt-3">
                        <native:text class="text-theme-on-surface-variant text-base font-semibold">Link Shape</native:text>
                        <native:text class="text-theme-on-surface-variant text-sm">In what shape do you want to present your links?</native:text>
                        <native:select label="Link Shape" :options="$linkShapeOptions" native:model="linkShape" />
                        <native:pressable
                            class="self-start px-4 py-2 rounded-full bg-theme-primary {{ $linkBusy ? 'opacity-40' : '' }}"
                            @tap="saveLinkShape"
                            a11y-label="Save link shape"
                        >
                            <native:text class="text-sm font-medium text-white">Save</native:text>
                        </native:pressable>
                    </native:column>

                    <native:column class="border-theme-outline-variant w-full gap-2 border-t pt-3">
                        <native:outlined-text-input
                            label="Description"
                            placeholder="My website"
                            native:model="newDescription"
                        />
                        <native:outlined-text-input
                            label="URL"
                            placeholder="https://example.com"
                            keyboard="url"
                            native:model="newUrl"
                        />
                        <native:button variant="secondary" :disabled="$linkBusy" @tap="addLink">Create</native:button>
                    </native:column>
                </native:column>

                <native:pressable class="w-full items-center py-2" @tap="viewProfile" a11y-label="View profile">
                    <native:text class="text-theme-primary text-sm font-medium">View profile</native:text>
                </native:pressable>
            @endif

            <native:column class="h-10 w-full" />
        </native:column>
    </native:column>
</native:scroll-view>

@include('native.app-menu-sheet')
