<native:scroll-view class="bg-theme-background h-full w-full">
    <native:column class="w-full gap-0">
        <x-page-heading :title="$askUsername !== '' ? 'Ask @'.$askUsername : 'New Thread'" :back="true" />

        <native:column class="w-full gap-1 px-3.5 py-4">
            @if ($errorMessage !== '')
                <native:column class="bg-theme-destructive/15 border-theme-destructive w-full rounded-md border p-4">
                    <native:text class="text-theme-destructive text-sm">{{ $errorMessage }}</native:text>
                </native:column>
            @endif

            @php
                // Use literal classes: TailwindParser cannot resolve Blade class variables.
                $me = ['avatar' => $avatar, 'name' => 'You'];
                $limit = \App\NativeComponents\Compose::MAX_CONTENT_LENGTH;

                $counter = function (?string $value) use ($limit): array {
                    $length = mb_strlen((string) $value);

                    return [$length, $length > $limit ? $length - $limit : 0];
                };
            @endphp

            @if ($askUsername !== '')
                <native:row class="w-full items-start gap-3">
                    <native:column class="w-10 shrink-0 items-center">
                        <x-avatar :user="$me" :link="false" />
                    </native:column>
                    <native:column class="min-w-0 flex-1 gap-1">
                        <native:outlined-text-input
                            label="Ask {{ '@'.$askUsername }}"
                            placeholder="Ask {{ '@'.$askUsername }}..."
                            multiline
                            native:model="content"
                            :selection-debounce-ms="400"
                            @selectionChange="updateMentionSelection(-1)"
                        />
                        @if ($mentionPostIndex === -1)
                            @if ($mentionKind === 'hashtag')
                                <x-hashtag-suggestions :hashtags="$mentionResults" />
                            @else
                                <x-mention-suggestions :users="$mentionResults" />
                            @endif
                        @endif
                        @php [$length, $over] = $counter($content); @endphp
                        <native:row class="w-full items-center justify-end">
                            @if ($over > 0)
                                <native:text class="text-theme-destructive border-theme-destructive rounded-full border-2 px-1.5 text-xs font-semibold">{{ $over }}</native:text>
                            @elseif ($length > 0)
                                <native:text class="text-theme-on-surface-variant text-xs">{{ $length }} / {{ \App\NativeComponents\Compose::MAX_ASK_LENGTH }}</native:text>
                            @endif
                        </native:row>
                        @if ($this->contentTooLong())
                            <native:text class="text-theme-destructive text-xs">Over the 255 character limit.</native:text>
                        @endif
                    </native:column>
                </native:row>

                <x-captcha-check
                    :required="$captchaRequired"
                    :loading="$captchaLoading"
                    :completed="$captchaCompleted"
                    :url="$captchaUrl"
                    :error="$captchaError"
                />

                <native:row class="border-theme-outline-variant mt-4 w-full items-center justify-between gap-2 border-t pt-3">
                    <native:button
                        variant="primary"
                        :disabled="$busy || $captchaLoading || ! $this->canPublish()"
                        @tap="publish"
                    >{{ $busy ? 'Sending…' : 'Ask' }}</native:button>

                    <native:row class="border-theme-outline-variant items-center rounded-md border px-3 py-2">
                        <native:checkbox label="Anonymously" native:model="anonymous" />
                        <native:text class="text-theme-on-surface-variant ml-2 text-sm">Your name stays hidden.</native:text>
                    </native:row>
                </native:row>

                <native:column class="h-10 w-full" />
            @else
                <native:row class="w-full items-start gap-3">
                    <native:column class="w-10 shrink-0 items-center">
                        <x-avatar :user="$me" :link="false" />
                        @if (count($posts) > 0)
                            <native:column class="bg-theme-outline-variant mt-1 w-px flex-1" />
                        @endif
                    </native:column>
                    <native:column class="min-w-0 flex-1 gap-1">
                        <native:bare-text-input
                            native:key="compose-content"
                            class="text-theme-on-body w-full"
                            placeholder="Share an update..."
                            multiline
                            line-height="1.4"
                            native:model="content"
                            :selection-debounce-ms="400"
                            @selectionChange="updateMentionSelection(-1)"
                        />
                        @if ($mentionPostIndex === -1)
                            @if ($mentionKind === 'hashtag')
                                <x-hashtag-suggestions :hashtags="$mentionResults" />
                            @else
                                <x-mention-suggestions :users="$mentionResults" />
                            @endif
                        @endif

                        <native:column class="w-full">
                            <x-image-preview :images="$this->images" class="mt-2 flex flex-wrap gap-2" />
                            <native:row class="mt-1 w-full items-center gap-1 pl-3.5">
                                @if ($this->supportsImagePicker())
                                    <native:pressable
                                        class="w-7 h-7 items-center justify-center {{ $this->canAddImage() ? '' : 'opacity-40' }}"
                                        @tap="pickImages"
                                        a11y-label="Upload an image"
                                    >
                                        <native:icon
                                            :ios="\App\Icons\Ios::Photo"
                                            :android="\App\Icons\AndroidOutlined::Image"
                                            :size="16"
                                            color="#64748B"
                                            dark-color="#94A3B8"
                                        />
                                    </native:pressable>
                                @endif
                                <native:pressable
                                    class="h-7 w-7 items-center justify-center"
                                    @tap="togglePoll"
                                    a11y-label="Create a poll"
                                >
                                    <native:icon
                                        :ios="\App\Icons\Ios::ChartBar"
                                        :android="\App\Icons\AndroidOutlined::InsertChart"
                                        :size="16"
                                        :color="$isPoll ? '#EC4899' : '#64748B'"
                                        dark-color="#94A3B8"
                                    />
                                </native:pressable>
                                @php [$length, $over] = $counter($content); @endphp
                                <native:column class="flex-1 items-end">
                                    @if ($over > 0)
                                        <native:text class="text-theme-destructive border-theme-destructive rounded-full border-2 px-1.5 text-xs font-semibold">{{ $over }}</native:text>
                                    @elseif ($length > 0)
                                        <native:text class="text-theme-on-surface-variant text-xs">{{ $length }} / {{ $limit }}</native:text>
                                    @endif
                                </native:column>
                            </native:row>
                        </native:column>

                        @if ($over > 0)
                            <native:text class="text-theme-destructive pl-3.5 text-xs">Over the {{ $limit }} character limit.</native:text>
                        @endif

                        @if ($isPoll)
                            <native:column class="mt-1 w-full gap-2 pl-3.5">
                                @foreach ($pollOptions as $index => $option)
                                    <native:row class="w-full items-center gap-2">
                                        <native:column class="border-theme-outline h-3.5 w-3.5 shrink-0 rounded-full border" />
                                        <native:column class="min-w-0 flex-1">
                                            <native:outlined-text-input
                                                placeholder="Option {{ $index + 1 }}"
                                                :value="$option"
                                                @change="updatePollOption({{ $index }})"
                                            />
                                        </native:column>
                                        @if (count($pollOptions) > 2)
                                            <native:pressable
                                                class="shrink-0 p-1"
                                                @tap="removePollOption({{ $index }})"
                                                a11y-label="Remove option {{ $index + 1 }}"
                                            >
                                                <native:icon
                                                    :ios="\App\Icons\Ios::Xmark"
                                                    :android="\App\Icons\AndroidOutlined::Close"
                                                    :size="16"
                                                    color="#64748B"
                                                    dark-color="#94A3B8"
                                                />
                                            </native:pressable>
                                        @endif
                                    </native:row>
                                @endforeach

                                @if (count($pollOptions) < 4)
                                    <native:pressable
                                        class="border-theme-outline-variant w-full items-start rounded-lg border py-2 pl-3"
                                        @tap="addPollOption"
                                        a11y-label="Add another option"
                                    >
                                        <native:text class="text-theme-on-surface-variant text-sm">Add another option</native:text>
                                    </native:pressable>
                                @endif

                                <native:row class="w-full items-center justify-between">
                                    <native:row class="flex-wrap items-center gap-2">
                                        <native:text class="text-theme-on-surface-variant text-xs">Duration</native:text>
                                        @foreach (\App\NativeComponents\Compose::POLL_DURATIONS as $days => $label)
                                            <native:pressable
                                                class="px-2.5 py-1.5 rounded-md {{ $pollDuration === $days ? 'bg-theme-primary' : 'border border-theme-outline-variant' }}"
                                                @tap="setPollDuration({{ $days }})"
                                                a11y-label="Poll duration {{ $label }}"
                                            >
                                                <native:text class="text-xs {{ $pollDuration === $days ? 'text-white' : 'text-theme-on-surface' }}">{{ $label }}</native:text>
                                            </native:pressable>
                                        @endforeach
                                    </native:row>
                                    <native:pressable class="shrink-0 py-1" @tap="togglePoll" a11y-label="Remove poll">
                                        <native:text class="text-theme-primary text-xs font-medium">Remove poll</native:text>
                                    </native:pressable>
                                </native:row>
                            </native:column>
                        @endif
                    </native:column>
                </native:row>

                @foreach ($posts as $index => $post)
                    <native:row class="mt-1 w-full items-start gap-3">
                        <native:column class="w-10 shrink-0 items-center">
                            <native:column class="bg-theme-outline-variant w-px flex-1" />
                            <x-avatar :user="$me" :size="7" :link="false" />
                            <native:column class="bg-theme-outline-variant w-px flex-1" />
                        </native:column>
                        <native:column class="min-w-0 flex-1 gap-1">
                            <native:bare-text-input
                                native:key="compose-post-{{ $index }}"
                                ref="post-{{ $index }}"
                                class="text-theme-on-body w-full"
                                placeholder="Say more..."
                                multiline
                                line-height="1.4"
                                :value="$post"
                                @change="updatePost({{ $index }})"
                                :selection-debounce-ms="400"
                                @selectionChange="updateMentionSelection({{ $index }})"
                            />
                            @if ($mentionPostIndex === $index)
                                @if ($mentionKind === 'hashtag')
                                    <x-hashtag-suggestions :hashtags="$mentionResults" />
                                @else
                                    <x-mention-suggestions :users="$mentionResults" />
                                @endif
                            @endif

                            <native:row class="w-full items-center gap-1 pl-3.5">
                                <native:pressable
                                    class="h-7 w-7 items-center justify-center"
                                    @tap="togglePostPoll({{ $index }})"
                                    a11y-label="Create a poll for post {{ $index + 2 }}"
                                >
                                    <native:icon
                                        :ios="\App\Icons\Ios::ChartBar"
                                        :android="\App\Icons\AndroidOutlined::InsertChart"
                                        :size="16"
                                        :color="($postPolls[$index]['isPoll'] ?? false) ? '#EC4899' : '#64748B'"
                                        dark-color="#94A3B8"
                                    />
                                </native:pressable>
                                <native:pressable
                                    class="h-7 w-7 items-center justify-center"
                                    @tap="removePost({{ $index }})"
                                    a11y-label="Remove post {{ $index + 2 }}"
                                >
                                    <native:icon
                                        :ios="\App\Icons\Ios::Xmark"
                                        :android="\App\Icons\AndroidOutlined::Close"
                                        :size="16"
                                        color="#64748B"
                                        dark-color="#94A3B8"
                                    />
                                </native:pressable>
                                @php [$length, $over] = $counter($post); @endphp
                                <native:column class="flex-1 items-end">
                                    @if ($over > 0)
                                        <native:text class="text-theme-destructive border-theme-destructive rounded-full border-2 px-1.5 text-xs font-semibold">{{ $over }}</native:text>
                                    @elseif ($length > 0)
                                        <native:text class="text-theme-on-surface-variant text-xs">{{ $length }} / {{ $limit }}</native:text>
                                    @endif
                                </native:column>
                            </native:row>

                            @if (mb_strlen($post) > $limit)
                                <native:text class="text-theme-destructive pl-3.5 text-xs">Over the {{ $limit }} character limit.</native:text>
                            @endif

                            @if ($postPolls[$index]['isPoll'] ?? false)
                                <native:column class="mt-1 w-full gap-2 pl-3.5">
                                    @foreach (($postPolls[$index]['options'] ?? []) as $optionIndex => $option)
                                        <native:row class="w-full items-center gap-2">
                                            <native:column class="border-theme-outline h-3.5 w-3.5 shrink-0 rounded-full border" />
                                            <native:column class="min-w-0 flex-1">
                                                <native:outlined-text-input
                                                    placeholder="Option {{ $optionIndex + 1 }}"
                                                    :value="$option"
                                                    @change="updatePostPollOption({{ $index }}, {{ $optionIndex }})"
                                                />
                                            </native:column>
                                            @if (count($postPolls[$index]['options'] ?? []) > 2)
                                                <native:pressable
                                                    class="shrink-0 p-1"
                                                    @tap="removePostPollOption({{ $index }}, {{ $optionIndex }})"
                                                    a11y-label="Remove option {{ $optionIndex + 1 }}"
                                                >
                                                    <native:icon
                                                        :ios="\App\Icons\Ios::Xmark"
                                                        :android="\App\Icons\AndroidOutlined::Close"
                                                        :size="14"
                                                        color="#64748B"
                                                        dark-color="#94A3B8"
                                                    />
                                                </native:pressable>
                                            @endif
                                        </native:row>
                                    @endforeach

                                    @if (count($postPolls[$index]['options'] ?? []) < 4)
                                        <native:pressable
                                            class="border-theme-outline-variant w-full items-start rounded-lg border py-2 pl-3"
                                            @tap="addPostPollOption({{ $index }})"
                                            a11y-label="Add another option to post {{ $index + 2 }}"
                                        >
                                            <native:text class="text-theme-on-surface-variant text-sm">Add another option</native:text>
                                        </native:pressable>
                                    @endif

                                    <native:row class="w-full items-center justify-between">
                                        <native:row class="flex-wrap items-center gap-2">
                                            <native:text class="text-theme-on-surface-variant text-xs">Duration</native:text>
                                            @foreach (\App\NativeComponents\Compose::THREAD_POLL_DURATIONS as $days => $label)
                                                <native:pressable
                                                    class="px-2.5 py-1.5 rounded-md {{ ($postPolls[$index]['duration'] ?? 1) === $days ? 'bg-theme-primary' : 'border border-theme-outline-variant' }}"
                                                    @tap="setPostPollDuration({{ $index }}, {{ $days }})"
                                                    a11y-label="Post {{ $index + 2 }} poll duration {{ $label }}"
                                                >
                                                    <native:text class="text-xs {{ ($postPolls[$index]['duration'] ?? 1) === $days ? 'text-white' : 'text-theme-on-surface' }}">{{ $label }}</native:text>
                                                </native:pressable>
                                            @endforeach
                                        </native:row>
                                        <native:pressable
                                            class="shrink-0 py-1"
                                            @tap="togglePostPoll({{ $index }})"
                                            a11y-label="Remove poll from post {{ $index + 2 }}"
                                        >
                                            <native:text class="text-theme-primary text-xs font-medium">Remove poll</native:text>
                                        </native:pressable>
                                    </native:row>
                                </native:column>
                            @endif
                        </native:column>
                    </native:row>
                @endforeach

                @if (count($posts) < \App\NativeComponents\Compose::MAX_THREAD_POSTS)
                    <native:row class="w-full items-center gap-3 {{ $this->canAddPost() ? '' : 'opacity-40' }}">
                        <native:column class="w-10 shrink-0 items-center">
                            <native:column class="bg-theme-outline-variant w-px flex-1" />
                            <native:column class="border-theme-outline-variant my-1 h-7 w-7 items-center justify-center rounded-full border">
                                <native:icon
                                    :ios="\App\Icons\Ios::Plus"
                                    :android="\App\Icons\AndroidOutlined::Add"
                                    :size="16"
                                    color="#64748B"
                                    dark-color="#94A3B8"
                                />
                            </native:column>
                        </native:column>
                        @if ($this->canAddPost())
                            <native:pressable class="flex-1 py-1" @tap="addPost" a11y-label="Add another post">
                                <native:text class="text-theme-primary text-sm font-medium">Add another post</native:text>
                            </native:pressable>
                        @else
                            <native:text class="text-theme-on-surface-variant flex-1 py-1 text-sm font-medium">Add another post</native:text>
                        @endif
                    </native:row>
                @else
                    <native:text class="text-theme-on-surface-variant text-center text-sm">A thread can have a maximum of 10 posts.</native:text>
                @endif

                <native:column class="border-theme-outline-variant mt-4 w-full gap-3 border-t pt-3">
                    <x-captcha-check
                        :required="$captchaRequired"
                        :loading="$captchaLoading"
                        :completed="$captchaCompleted"
                        :url="$captchaUrl"
                        :error="$captchaError"
                    />

                    <native:row class="w-full items-center justify-between gap-2">
                        <native:row class="items-center gap-2">
                            <native:button
                                variant="primary"
                                :disabled="$busy || $captchaLoading || ! $this->canPublish()"
                                @tap="publish"
                            >{{ $busy ? 'Publishing…' : (count($posts) > 0 ? 'Post thread' : 'Post') }}</native:button>

                            @if ($channelId !== null || $channelName !== '')
                                <native:pressable
                                    class="bg-theme-primary/10 border-theme-primary/30 flex-row items-center gap-1.5 rounded-md border px-2.5 py-1.5"
                                    @tap="clearChannel"
                                    a11y-label="Remove channel"
                                >
                                    <native:icon
                                        :ios="\App\Icons\Ios::Tag"
                                        :android="\App\Icons\AndroidOutlined::Sell"
                                        :size="14"
                                        color="#EC4899"
                                    />
                                    <native:text class="text-theme-primary text-xs font-medium">{{ $channelName }}</native:text>
                                    <native:icon
                                        :ios="\App\Icons\Ios::Xmark"
                                        :android="\App\Icons\AndroidOutlined::Close"
                                        :size="12"
                                        color="#EC4899"
                                    />
                                </native:pressable>
                            @else
                                <native:pressable
                                    class="border-theme-outline-variant flex-row items-center gap-1.5 rounded-md border px-2.5 py-1.5"
                                    @tap="togglePicker"
                                    a11y-label="Choose a channel"
                                >
                                    <native:icon
                                        :ios="\App\Icons\Ios::Tag"
                                        :android="\App\Icons\AndroidOutlined::Sell"
                                        :size="14"
                                        color="#64748B"
                                        dark-color="#94A3B8"
                                    />
                                    <native:text class="text-theme-on-surface-variant text-xs font-medium">Channel</native:text>
                                </native:pressable>
                            @endif
                        </native:row>
                    </native:row>

                    @if ($pickerOpen && $channelId === null && $channelName === '')
                        <native:column class="w-full gap-2">
                            <native:outlined-text-input
                                label="Channel"
                                placeholder="Search or create channel..."
                                native:model.debounce.500ms="channelQuery"
                            />
                            @if ($channelError !== '')
                                <native:text class="text-theme-destructive text-xs">{{ $channelError }}</native:text>
                            @endif
                            @foreach ($channelResults as $channel)
                                <native:pressable
                                    class="w-full py-2"
                                    @tap="selectChannel({{ (int) data_get($channel, 'id', 0) }})"
                                    a11y-label="Use channel {{ data_get($channel, 'name', '') }}"
                                >
                                    <native:row class="w-full items-center justify-between">
                                        <native:text class="text-theme-on-surface text-sm">{{ data_get($channel, 'name', '') }}</native:text>
                                        @if ((int) data_get($channel, 'id', 0) === $channelId)
                                            <native:icon
                                                :ios="\App\Icons\Ios::Checkmark"
                                                :android="\App\Icons\AndroidOutlined::Check"
                                                :size="14"
                                                color="#EC4899"
                                            />
                                        @endif
                                    </native:row>
                                </native:pressable>
                            @endforeach
                            @if ($this->canCreateChannel())
                                <native:pressable
                                    class="border-theme-primary/30 bg-theme-primary/10 w-full items-center rounded-md border border-dashed py-2"
                                    @tap="stageChannel"
                                    a11y-label="Create channel"
                                >
                                    <native:text class="text-theme-primary text-xs font-medium">Create "{{ trim($channelQuery) }}"</native:text>
                                </native:pressable>
                            @elseif (mb_trim($channelQuery) === '' && count($channelResults) === 0)
                                <native:text class="text-theme-on-surface-variant text-center text-xs">Type to create a channel</native:text>
                            @elseif (mb_trim($channelQuery) !== '' && mb_strlen(mb_trim($channelQuery)) < 2)
                                <native:text class="text-theme-on-surface-variant text-center text-xs">Min 2 characters required</native:text>
                            @endif
                        </native:column>
                    @endif
                </native:column>

                <native:column class="h-10 w-full" />
            @endif
        </native:column>
    </native:column>
</native:scroll-view>

@include('native.app-menu-sheet')
