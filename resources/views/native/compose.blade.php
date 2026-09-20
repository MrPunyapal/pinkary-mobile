<native:top-bar title="New Thread" back />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full px-5 py-6 gap-5">
        @if ($errorMessage !== '')
            <native:column class="w-full p-4 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
            </native:column>
        @endif

        {{-- No connector rails: step order is carried by the Post N labels
            and the "Add another post" row (plain row/column, the
            super-native compose-tweet pattern) — nothing to stitch. --}}
        <native:row class="w-full items-start gap-3">
            <native:column class="w-10 items-center shrink-0">
                <x-avatar :user="['avatar' => $avatar, 'name' => 'You']" :link="false" />
            </native:column>
            <native:column class="flex-1 gap-1 min-w-0">
                <native:outlined-text-input label="Share an update..." placeholder="Share an update..." multiline native:model="content" />
                @if (mb_strlen($content) > 800)
                    <native:text class="text-xs text-right {{ mb_strlen($content) >= 1000 ? 'text-theme-destructive' : 'text-theme-on-surface-variant' }}">{{ mb_strlen($content) }} / 1000</native:text>
                @endif
            </native:column>
        </native:row>

        @foreach ($posts as $index => $post)
            <native:row class="w-full items-start gap-3">
                <native:column class="w-10 items-center shrink-0">
                    <x-avatar :user="['avatar' => $avatar, 'name' => 'You']" :size="7" :link="false" />
                </native:column>
                    <native:column class="flex-1 gap-1 min-w-0">
                    <native:outlined-text-input label="Post {{ $index + 2 }}" placeholder="Say more..." multiline :value="$post" @change="updatePost({{ $index }})" />
                    <native:row class="w-full items-center justify-between">
                        <native:row class="items-center gap-1">
                            <native:pressable class="p-1" @tap="togglePostPoll({{ $index }})" a11y-label="Poll for post {{ $index + 2 }}">
                                <native:icon :ios="\App\Icons\Ios::ChartBar" :android="\App\Icons\Android::BarChart" :size="16" color="{{ ($postPolls[$index]['isPoll'] ?? false) ? '#EC4899' : '#64748B' }}" />
                            </native:pressable>
                            <native:pressable class="p-1" @tap="removePost({{ $index }})" a11y-label="Remove post {{ $index + 2 }}">
                                <native:icon :ios="\App\Icons\Ios::Xmark" :android="\App\Icons\Android::Close" :size="16" color="#64748B" dark-color="#94A3B8" />
                            </native:pressable>
                        </native:row>
                        @if (mb_strlen($post) > 800)
                            <native:text class="text-xs {{ mb_strlen($post) >= 1000 ? 'text-theme-destructive' : 'text-theme-on-surface-variant' }}">{{ mb_strlen($post) }} / 1000</native:text>
                        @endif
                    </native:row>

                    @if ($postPolls[$index]['isPoll'] ?? false)
                        <native:column class="w-full gap-2 mt-1">
                            @foreach (($postPolls[$index]['options'] ?? []) as $optionIndex => $option)
                                <native:row class="w-full items-center gap-2">
                                    <native:column class="w-3.5 h-3.5 rounded-full border border-theme-outline shrink-0" />
                                    <native:column class="flex-1 min-w-0">
                                        <native:outlined-text-input label="Option {{ $optionIndex + 1 }}" placeholder="Option {{ $optionIndex + 1 }}" :value="$option" @change="updatePostPollOption({{ $index }}, {{ $optionIndex }})" />
                                    </native:column>
                                    @if (count($postPolls[$index]['options'] ?? []) > 2)
                                        <native:pressable class="p-1 shrink-0" @tap="removePostPollOption({{ $index }}, {{ $optionIndex }})" a11y-label="Remove option">
                                            <native:icon :ios="\App\Icons\Ios::Xmark" :android="\App\Icons\Android::Close" :size="14" color="#64748B" dark-color="#94A3B8" />
                                        </native:pressable>
                                    @endif
                                </native:row>
                            @endforeach
                            @if (count($postPolls[$index]['options'] ?? []) < 4)
                                <native:pressable class="w-full items-center py-2 rounded-md border border-dashed border-theme-outline" @tap="addPostPollOption({{ $index }})">
                                    <native:text class="text-sm text-theme-on-surface-variant">Add another option</native:text>
                                </native:pressable>
                            @endif
                            <native:column class="w-full gap-1.5">
                                <native:text class="text-xs text-theme-on-surface-variant">Duration</native:text>
                                <native:row class="w-full items-center gap-2 flex-wrap">
                                    @foreach (\App\NativeComponents\Compose::THREAD_POLL_DURATIONS as $days => $label)
                                        <native:pressable class="px-2.5 py-1.5 rounded-md {{ ($postPolls[$index]['duration'] ?? 1) === $days ? 'bg-theme-primary' : 'border border-theme-outline-variant' }}" @tap="setPostPollDuration({{ $index }}, {{ $days }})">
                                            <native:text class="text-xs {{ ($postPolls[$index]['duration'] ?? 1) === $days ? 'text-white' : 'text-theme-on-surface' }}">{{ $label }}</native:text>
                                        </native:pressable>
                                    @endforeach
                                </native:row>
                            </native:column>
                        </native:column>
                    @endif
                </native:column>
            </native:row>
        @endforeach

        @if (count($posts) < 9)
            {{-- The row only carries a tap target once a follow-up is
                actually allowed — otherwise it is a dimmed hint, never
                a silent no-op. (native:pressable has no disabled prop.) --}}
            <native:row class="w-full items-center gap-3 {{ $this->canAddPost() ? '' : 'opacity-40' }}">
                <native:column class="items-center shrink-0 w-10">
                    <native:column class="w-7 h-7 items-center justify-center rounded-full border border-dashed border-theme-outline">
                        <native:text class="text-sm text-theme-on-surface-variant">+</native:text>
                    </native:column>
                </native:column>
                @if ($this->canAddPost())
                    <native:pressable class="flex-1 py-1" @tap="addPost" a11y-label="Add another post">
                        <native:text class="text-sm font-medium text-theme-primary">Add another post</native:text>
                    </native:pressable>
                @else
                    <native:text class="text-sm font-medium text-theme-on-surface-variant">Add another post</native:text>
                @endif
            </native:row>
        @else
            <native:text class="text-sm text-center text-theme-on-surface-variant">A thread can have a maximum of 10 posts.</native:text>
        @endif

        <native:column class="w-full gap-2 p-4 rounded-md bg-theme-surface border border-theme-outline-variant">
            <native:row class="w-full items-center justify-between">
                <native:text class="text-base font-semibold text-theme-on-surface">Poll</native:text>
                <native:pressable class="py-1" @tap="togglePoll">
                    <native:text class="text-sm font-semibold text-theme-primary">{{ $isPoll ? 'Remove poll' : 'Add poll' }}</native:text>
                </native:pressable>
            </native:row>

            @if ($isPoll)
                @foreach ($pollOptions as $index => $option)
                    <native:row class="w-full items-center gap-2">
                        <native:column class="w-3.5 h-3.5 rounded-full border border-theme-outline shrink-0" />
                        <native:column class="flex-1 min-w-0">
                            <native:outlined-text-input label="Option {{ $index + 1 }}" placeholder="Option {{ $index + 1 }} (max 40 chars)" :value="$option" @change="updatePollOption({{ $index }})" />
                        </native:column>
                        @if (count($pollOptions) > 2)
                            <native:pressable class="p-1 shrink-0" @tap="removePollOption({{ $index }})" a11y-label="Remove option">
                                <native:icon :ios="\App\Icons\Ios::Xmark" :android="\App\Icons\Android::Close" :size="14" color="#64748B" dark-color="#94A3B8" />
                            </native:pressable>
                        @endif
                    </native:row>
                @endforeach

                @if (count($pollOptions) < 4)
                    <native:pressable class="w-full items-center py-2 rounded-md border border-dashed border-theme-outline" @tap="addPollOption">
                        <native:text class="text-sm text-theme-on-surface-variant">Add another option</native:text>
                    </native:pressable>
                @endif

                <native:column class="w-full gap-1.5">
                    <native:text class="text-xs text-theme-on-surface-variant">Duration</native:text>
                    <native:row class="w-full items-center gap-2 flex-wrap">
                        @foreach (\App\NativeComponents\Compose::POLL_DURATIONS as $days => $label)
                            <native:pressable class="px-2.5 py-1.5 rounded-md {{ $pollDuration === $days ? 'bg-theme-primary' : 'border border-theme-outline-variant' }}" @tap="setPollDuration({{ $days }})">
                                <native:text class="text-xs {{ $pollDuration === $days ? 'text-white' : 'text-theme-on-surface' }}">{{ $label }}</native:text>
                            </native:pressable>
                        @endforeach
                    </native:row>
                </native:column>
            @else
                <native:text class="text-sm text-theme-on-surface-variant">Ask a question with up to 4 answers.</native:text>
            @endif
        </native:column>

        <native:column class="w-full gap-2">
            @if ($channelId !== null || $channelName !== '')
                <native:row class="items-center gap-1.5 px-2.5 py-1.5 rounded-md bg-theme-primary/10 border border-theme-primary/30 self-start">
                    <native:icon :ios="\App\Icons\Ios::Tag" :android="\App\Icons\AndroidOutlined::Sell" :size="14" color="#EC4899" />
                    <native:text class="text-xs font-medium text-theme-primary">{{ $channelName }}</native:text>
                    <native:pressable class="p-0.5" @tap="clearChannel" a11y-label="Remove channel">
                        <native:icon :ios="\App\Icons\Ios::Xmark" :android="\App\Icons\Android::Close" :size="12" color="#EC4899" />
                    </native:pressable>
                </native:row>
            @else
                <native:pressable class="flex-row items-center gap-1.5 px-2.5 py-1.5 rounded-md border border-theme-outline-variant self-start" @tap="togglePicker" a11y-label="Choose a channel">
                    <native:icon :ios="\App\Icons\Ios::Tag" :android="\App\Icons\AndroidOutlined::Sell" :size="14" color="#64748B" dark-color="#94A3B8" />
                    <native:text class="text-xs font-medium text-theme-on-surface-variant">Channel</native:text>
                </native:pressable>
            @endif

            @if ($pickerOpen && $channelId === null && $channelName === '')
                <native:outlined-text-input label="Channel" placeholder="Search or create channel..." native:model.debounce.500ms="channelQuery" />
                @if ($channelError !== '')
                    <native:text class="text-xs text-theme-destructive">{{ $channelError }}</native:text>
                @endif
                @foreach ($channelResults as $channel)
                    <native:pressable class="w-full py-2" @tap="selectChannel({{ (int) data_get($channel, 'id', 0) }})">
                        <native:row class="w-full items-center justify-between">
                            <native:text class="text-sm text-theme-on-surface">{{ data_get($channel, 'name', '') }}</native:text>
                            @if ((int) data_get($channel, 'id', 0) === $channelId)
                                <native:icon :ios="\App\Icons\Ios::Checkmark" :android="\App\Icons\Android::Check" :size="14" color="#EC4899" />
                            @endif
                        </native:row>
                    </native:pressable>
                @endforeach
                @if ($this->canCreateChannel())
                    <native:pressable class="w-full items-center py-2 rounded-md border border-dashed border-theme-primary/30 bg-theme-primary/10" @tap="stageChannel">
                        <native:text class="text-xs font-medium text-theme-primary">Create "{{ trim($channelQuery) }}"</native:text>
                    </native:pressable>
                @elseif (trim($channelQuery) === '' && count($channelResults) === 0)
                    <native:text class="text-xs text-center text-theme-on-surface-variant">Type to create a channel</native:text>
                @elseif (trim($channelQuery) !== '' && mb_strlen(trim($channelQuery)) < 2)
                    <native:text class="text-xs text-center text-theme-on-surface-variant">Min 2 characters required</native:text>
                @endif
            @endif
        </native:column>

        <native:button variant="primary" :disabled="$busy" @tap="publish">{{ $busy ? 'Publishing…' : (count($posts) > 0 ? 'Post thread' : 'Post') }}</native:button>

        <native:column class="w-full h-10" />
    </native:column>
</native:scroll-view>
