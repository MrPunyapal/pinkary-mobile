<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-0">
        <x-page-heading :title="$askUsername !== '' ? 'Ask @'.$askUsername : 'New Thread'" :back="true" />

        <native:column class="w-full px-3.5 py-4 gap-1">
        @if ($errorMessage !== '')
            <native:column class="w-full p-4 rounded-md bg-theme-destructive/15 border border-theme-destructive">
                <native:text class="text-sm text-theme-destructive">{{ $errorMessage }}</native:text>
            </native:column>
        @endif

        @php
            // The web composer draws one hairline down the avatar column and
            // threads every post onto it, so a thread reads as a single unit
            // (livewire/questions/create.blade.php:37-41 for the opening post,
            // :203-212 for each follow-up, :389-393 for the add row). That
            // rail is the composer's signature, so it is reproduced here
            // rather than approximated with labelled rows.
            //
            // The hairline's class is written out literally at each use on
            // purpose: TailwindParser reads the raw class attribute, so a
            // class supplied through a Blade variable parses to nothing and
            // the line renders invisible.
            $me = ['avatar' => $avatar, 'name' => 'You'];
            $limit = \App\NativeComponents\Compose::MAX_CONTENT_LENGTH;

            // The web's counter is a progress ring that turns into a red pill
            // showing the overage (components/character-counter.blade.php:13-16).
            // An arc cannot be drawn natively, so keep the behaviour that
            // carries meaning: silent at zero, a muted count while there is
            // room, a red pill once over.
            $counter = function (?string $value) use ($limit): array {
                $length = mb_strlen((string) $value);

                return [$length, $length > $limit ? $length - $limit : 0];
            };
        @endphp

        @if ($askUsername !== '')
            {{-- Ask mode is a single short question to another user: no
                thread, so no rail and no borderless field. The web draws a
                bordered box for exactly this case
                (create.blade.php:9 -- canThread is false) and puts the
                Anonymously toggle in the footer beside the submit button
                (:423-430), not in a card of its own. --}}
            <native:row class="w-full items-start gap-3">
                <native:column class="w-10 items-center shrink-0">
                    <x-avatar :user="$me" :link="false" />
                </native:column>
                <native:column class="flex-1 gap-1 min-w-0">
                    <native:outlined-text-input label="Ask {{ '@'.$askUsername }}" placeholder="Ask {{ '@'.$askUsername }}..." multiline native:model="content" />
                    @php [$length, $over] = $counter($content); @endphp
                    <native:row class="w-full items-center justify-end">
                        @if ($over > 0)
                            <native:text class="px-1.5 text-xs font-semibold text-theme-destructive rounded-full border-2 border-theme-destructive">{{ $over }}</native:text>
                        @elseif ($length > 0)
                            <native:text class="text-xs text-theme-on-surface-variant">{{ $length }} / {{ \App\NativeComponents\Compose::MAX_ASK_LENGTH }}</native:text>
                        @endif
                    </native:row>
                    @if ($this->contentTooLong())
                        <native:text class="text-xs text-theme-destructive">Over the 255 character limit.</native:text>
                    @endif
                </native:column>
            </native:row>

            {{-- The web's ask footer: submit and the Anonymously toggle on
                one row (create.blade.php:423-430 shows Anonymously whenever
                the composer is not sharing an update). --}}
            <native:row class="w-full items-center justify-between gap-2 mt-4 pt-3 border-t border-theme-outline-variant">
                <native:button variant="primary" :disabled="$busy || ! $this->canPublish()" @tap="publish">{{ $busy ? 'Sending…' : 'Ask' }}</native:button>

                <native:row class="items-center px-3 py-2 rounded-md border border-theme-outline-variant">
                    <native:checkbox label="Anonymously" native:model="anonymous" />
                    <native:text class="ml-2 text-sm text-theme-on-surface-variant">Your name stays hidden.</native:text>
                </native:row>
            </native:row>

            <native:column class="w-full h-10" />
        @else

        <native:row class="w-full items-start gap-3">
            <native:column class="w-10 items-center shrink-0">
                <x-avatar :user="$me" :link="false" />
                @if (count($posts) > 0)
                    <native:column class="w-px flex-1 mt-1 bg-theme-outline-variant" />
                @endif
            </native:column>
            <native:column class="flex-1 gap-1 min-w-0">
                <native:bare-text-input class="w-full px-3.5 text-[15.2px] leading-7 text-theme-on-body" placeholder="Share an update..." multiline native:model="content" />

                {{-- The web's secondary controls sit under the field: a photo
                    button, a poll toggle and the counter pushed right
                    (create.blade.php:85-119). --}}
                <native:column class="w-full">
                    <x-image-preview :images="$images" class="mt-2 flex flex-wrap gap-2" />
                    <native:row class="w-full items-center gap-1 pl-3.5 mt-1">
                        <native:pressable
                            class="w-7 h-7 items-center justify-center {{ $this->canAddImage() ? '' : 'opacity-40' }}"
                            @tap="pickImages"
                            a11y-label="Upload an image"
                        >
                            <native:icon :ios="\App\Icons\Ios::Photo" :android="\App\Icons\AndroidOutlined::Image" :size="16" color="#64748B" dark-color="#94A3B8" />
                        </native:pressable>
                        <native:pressable class="w-7 h-7 items-center justify-center" @tap="togglePoll" a11y-label="Create a poll">
                            <native:icon :ios="\App\Icons\Ios::ChartBar" :android="\App\Icons\AndroidOutlined::InsertChart" :size="16" :color="$isPoll ? '#EC4899' : '#64748B'" dark-color="#94A3B8" />
                        </native:pressable>
                        @php [$length, $over] = $counter($content); @endphp
                        <native:column class="flex-1 items-end">
                            @if ($over > 0)
                                <native:text class="px-1.5 text-xs font-semibold text-theme-destructive rounded-full border-2 border-theme-destructive">{{ $over }}</native:text>
                            @elseif ($length > 0)
                                <native:text class="text-xs text-theme-on-surface-variant">{{ $length }} / {{ $limit }}</native:text>
                            @endif
                        </native:column>
                    </native:row>
                </native:column>

                @if ($over > 0)
                    <native:text class="text-xs text-theme-destructive pl-3.5">Over the {{ $limit }} character limit.</native:text>
                @endif

                @if ($isPoll)
                    {{-- Poll options belong directly under the post they
                        belong to; the web never detaches them into a panel
                        (create.blade.php:121-180). --}}
                    <native:column class="w-full gap-2 mt-1 pl-3.5">
                        @foreach ($pollOptions as $index => $option)
                            <native:row class="w-full items-center gap-2">
                                <native:column class="w-3.5 h-3.5 rounded-full border border-theme-outline shrink-0" />
                                <native:column class="flex-1 min-w-0">
                                    <native:outlined-text-input placeholder="Option {{ $index + 1 }}" :value="$option" @change="updatePollOption({{ $index }})" />
                                </native:column>
                                @if (count($pollOptions) > 2)
                                    <native:pressable class="p-1 shrink-0" @tap="removePollOption({{ $index }})" a11y-label="Remove option {{ $index + 1 }}">
                                        <native:icon :ios="\App\Icons\Ios::Xmark" :android="\App\Icons\AndroidOutlined::Close" :size="16" color="#64748B" dark-color="#94A3B8" />
                                    </native:pressable>
                                @endif
                            </native:row>
                        @endforeach

                        @if (count($pollOptions) < 4)
                            <native:pressable class="w-full items-start py-2 pl-3 rounded-lg border border-theme-outline-variant" @tap="addPollOption" a11y-label="Add another option">
                                <native:text class="text-sm text-theme-on-surface-variant">Add another option</native:text>
                            </native:pressable>
                        @endif

                        <native:row class="w-full items-center justify-between">
                            <native:row class="items-center gap-2 flex-wrap">
                                <native:text class="text-xs text-theme-on-surface-variant">Duration</native:text>
                                @foreach (\App\NativeComponents\Compose::POLL_DURATIONS as $days => $label)
                                    <native:pressable class="px-2.5 py-1.5 rounded-md {{ $pollDuration === $days ? 'bg-theme-primary' : 'border border-theme-outline-variant' }}" @tap="setPollDuration({{ $days }})" a11y-label="Poll duration {{ $label }}">
                                        <native:text class="text-xs {{ $pollDuration === $days ? 'text-white' : 'text-theme-on-surface' }}">{{ $label }}</native:text>
                                    </native:pressable>
                                @endforeach
                            </native:row>
                            <native:pressable class="py-1 shrink-0" @tap="togglePoll" a11y-label="Remove poll">
                                <native:text class="text-xs font-medium text-theme-primary">Remove poll</native:text>
                            </native:pressable>
                        </native:row>
                    </native:column>
                @endif
            </native:column>
        </native:row>

        @foreach ($posts as $index => $post)
            <native:row class="w-full items-start gap-3 mt-1">
                <native:column class="w-10 items-center shrink-0">
                    <native:column class="w-px flex-1 bg-theme-outline-variant" />
                    <x-avatar :user="$me" :size="7" :link="false" />
                    <native:column class="w-px flex-1 bg-theme-outline-variant" />
                </native:column>
                <native:column class="flex-1 gap-1 min-w-0">
                    <native:bare-text-input class="w-full px-3.5 text-[15.2px] leading-7 text-theme-on-body" placeholder="Say more..." multiline :value="$post" @change="updatePost({{ $index }})" />

                    <native:row class="w-full items-center gap-1 pl-3.5">
                        <native:pressable class="w-7 h-7 items-center justify-center" @tap="togglePostPoll({{ $index }})" a11y-label="Create a poll for post {{ $index + 2 }}">
                            <native:icon :ios="\App\Icons\Ios::ChartBar" :android="\App\Icons\AndroidOutlined::InsertChart" :size="16" :color="($postPolls[$index]['isPoll'] ?? false) ? '#EC4899' : '#64748B'" dark-color="#94A3B8" />
                        </native:pressable>
                        <native:pressable class="w-7 h-7 items-center justify-center" @tap="removePost({{ $index }})" a11y-label="Remove post {{ $index + 2 }}">
                            <native:icon :ios="\App\Icons\Ios::Xmark" :android="\App\Icons\AndroidOutlined::Close" :size="16" color="#64748B" dark-color="#94A3B8" />
                        </native:pressable>
                        @php [$length, $over] = $counter($post); @endphp
                        <native:column class="flex-1 items-end">
                            @if ($over > 0)
                                <native:text class="px-1.5 text-xs font-semibold text-theme-destructive rounded-full border-2 border-theme-destructive">{{ $over }}</native:text>
                            @elseif ($length > 0)
                                <native:text class="text-xs text-theme-on-surface-variant">{{ $length }} / {{ $limit }}</native:text>
                            @endif
                        </native:column>
                    </native:row>

                    @if (mb_strlen($post) > $limit)
                        <native:text class="text-xs text-theme-destructive pl-3.5">Over the {{ $limit }} character limit.</native:text>
                    @endif

                    @if ($postPolls[$index]['isPoll'] ?? false)
                        <native:column class="w-full gap-2 mt-1 pl-3.5">
                            @foreach (($postPolls[$index]['options'] ?? []) as $optionIndex => $option)
                                <native:row class="w-full items-center gap-2">
                                    <native:column class="w-3.5 h-3.5 rounded-full border border-theme-outline shrink-0" />
                                    <native:column class="flex-1 min-w-0">
                                        <native:outlined-text-input placeholder="Option {{ $optionIndex + 1 }}" :value="$option" @change="updatePostPollOption({{ $index }}, {{ $optionIndex }})" />
                                    </native:column>
                                    @if (count($postPolls[$index]['options'] ?? []) > 2)
                                        <native:pressable class="p-1 shrink-0" @tap="removePostPollOption({{ $index }}, {{ $optionIndex }})" a11y-label="Remove option {{ $optionIndex + 1 }}">
                                            <native:icon :ios="\App\Icons\Ios::Xmark" :android="\App\Icons\AndroidOutlined::Close" :size="14" color="#64748B" dark-color="#94A3B8" />
                                        </native:pressable>
                                    @endif
                                </native:row>
                            @endforeach

                            @if (count($postPolls[$index]['options'] ?? []) < 4)
                                <native:pressable class="w-full items-start py-2 pl-3 rounded-lg border border-theme-outline-variant" @tap="addPostPollOption({{ $index }})" a11y-label="Add another option to post {{ $index + 2 }}">
                                    <native:text class="text-sm text-theme-on-surface-variant">Add another option</native:text>
                                </native:pressable>
                            @endif

                            <native:row class="w-full items-center justify-between">
                                <native:row class="items-center gap-2 flex-wrap">
                                    <native:text class="text-xs text-theme-on-surface-variant">Duration</native:text>
                                    @foreach (\App\NativeComponents\Compose::THREAD_POLL_DURATIONS as $days => $label)
                                        <native:pressable class="px-2.5 py-1.5 rounded-md {{ ($postPolls[$index]['duration'] ?? 1) === $days ? 'bg-theme-primary' : 'border border-theme-outline-variant' }}" @tap="setPostPollDuration({{ $index }}, {{ $days }})" a11y-label="Post {{ $index + 2 }} poll duration {{ $label }}">
                                            <native:text class="text-xs {{ ($postPolls[$index]['duration'] ?? 1) === $days ? 'text-white' : 'text-theme-on-surface' }}">{{ $label }}</native:text>
                                        </native:pressable>
                                    @endforeach
                                </native:row>
                                <native:pressable class="py-1 shrink-0" @tap="togglePostPoll({{ $index }})" a11y-label="Remove poll from post {{ $index + 2 }}">
                                    <native:text class="text-xs font-medium text-theme-primary">Remove poll</native:text>
                                </native:pressable>
                            </native:row>
                        </native:column>
                    @endif
                </native:column>
            </native:row>
        @endforeach

        @if (count($posts) < \App\NativeComponents\Compose::MAX_THREAD_POSTS)
            {{-- The web hangs this off the same rail: a hairline, then a
                dashed circle with a plus, then the label -- and dims it with
                a tooltip when the current post is still empty
                (create.blade.php:374-398). The row only becomes a tap target
                once a follow-up is allowed; otherwise it is a dimmed hint,
                never a silent no-op. --}}
            <native:row class="w-full items-center gap-3 {{ $this->canAddPost() ? '' : 'opacity-40' }}">
                <native:column class="items-center shrink-0 w-10">
                    <native:column class="w-px flex-1 bg-theme-outline-variant" />
                    <native:column class="my-1 w-7 h-7 items-center justify-center rounded-full border border-theme-outline-variant">
                        <native:icon :ios="\App\Icons\Ios::Plus" :android="\App\Icons\AndroidOutlined::Add" :size="16" color="#64748B" dark-color="#94A3B8" />
                    </native:column>
                </native:column>
                @if ($this->canAddPost())
                    <native:pressable class="flex-1 py-1" @tap="addPost" a11y-label="Add another post">
                        <native:text class="text-sm font-medium text-theme-primary">Add another post</native:text>
                    </native:pressable>
                @else
                    <native:text class="flex-1 py-1 text-sm font-medium text-theme-on-surface-variant">Add another post</native:text>
                @endif
            </native:row>
        @else
            <native:text class="text-sm text-center text-theme-on-surface-variant">A thread can have a maximum of 10 posts.</native:text>
        @endif

        {{-- The web's footer is one row: submit on the left with the channel
            picker beside it, and Anonymously on the right, split by a top
            border in the modal composer (create.blade.php:405-431). --}}
        <native:column class="w-full gap-3 mt-4 pt-3 border-t border-theme-outline-variant">
            <native:row class="w-full items-center justify-between gap-2">
                <native:row class="items-center gap-2">
                    <native:button variant="primary" :disabled="$busy || ! $this->canPublish()" @tap="publish">{{ $busy ? 'Publishing…' : (count($posts) > 0 ? 'Post thread' : 'Post') }}</native:button>

                    @if ($channelId !== null || $channelName !== '')
                        <native:pressable class="flex-row items-center gap-1.5 px-2.5 py-1.5 rounded-md bg-theme-primary/10 border border-theme-primary/30" @tap="clearChannel" a11y-label="Remove channel">
                            <native:icon :ios="\App\Icons\Ios::Tag" :android="\App\Icons\AndroidOutlined::Sell" :size="14" color="#EC4899" />
                            <native:text class="text-xs font-medium text-theme-primary">{{ $channelName }}</native:text>
                            <native:icon :ios="\App\Icons\Ios::Xmark" :android="\App\Icons\AndroidOutlined::Close" :size="12" color="#EC4899" />
                        </native:pressable>
                    @else
                        <native:pressable class="flex-row items-center gap-1.5 px-2.5 py-1.5 rounded-md border border-theme-outline-variant" @tap="togglePicker" a11y-label="Choose a channel">
                            <native:icon :ios="\App\Icons\Ios::Tag" :android="\App\Icons\AndroidOutlined::Sell" :size="14" color="#64748B" dark-color="#94A3B8" />
                            <native:text class="text-xs font-medium text-theme-on-surface-variant">Channel</native:text>
                        </native:pressable>
                    @endif
                </native:row>
            </native:row>

            @if ($pickerOpen && $channelId === null && $channelName === '')
                <native:column class="w-full gap-2">
                    <native:outlined-text-input label="Channel" placeholder="Search or create channel..." native:model.debounce.500ms="channelQuery" />
                    @if ($channelError !== '')
                        <native:text class="text-xs text-theme-destructive">{{ $channelError }}</native:text>
                    @endif
                    @foreach ($channelResults as $channel)
                        <native:pressable class="w-full py-2" @tap="selectChannel({{ (int) data_get($channel, 'id', 0) }})" a11y-label="Use channel {{ data_get($channel, 'name', '') }}">
                            <native:row class="w-full items-center justify-between">
                                <native:text class="text-sm text-theme-on-surface">{{ data_get($channel, 'name', '') }}</native:text>
                                @if ((int) data_get($channel, 'id', 0) === $channelId)
                                    <native:icon :ios="\App\Icons\Ios::Checkmark" :android="\App\Icons\AndroidOutlined::Check" :size="14" color="#EC4899" />
                                @endif
                            </native:row>
                        </native:pressable>
                    @endforeach
                    @if ($this->canCreateChannel())
                        <native:pressable class="w-full items-center py-2 rounded-md border border-dashed border-theme-primary/30 bg-theme-primary/10" @tap="stageChannel" a11y-label="Create channel">
                            <native:text class="text-xs font-medium text-theme-primary">Create "{{ trim($channelQuery) }}"</native:text>
                        </native:pressable>
                    @elseif (trim($channelQuery) === '' && count($channelResults) === 0)
                        <native:text class="text-xs text-center text-theme-on-surface-variant">Type to create a channel</native:text>
                    @elseif (trim($channelQuery) !== '' && mb_strlen(trim($channelQuery)) < 2)
                        <native:text class="text-xs text-center text-theme-on-surface-variant">Min 2 characters required</native:text>
                    @endif
                </native:column>
            @endif
        </native:column>

        <native:column class="w-full h-10" />
        @endif
        </native:column>
    </native:column>
</native:scroll-view>

@include('native.app-menu-sheet')
