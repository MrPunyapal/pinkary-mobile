<native:bottom-sheet native:key="post-menu-sheet" :visible="$menuPostId !== ''" @dismiss="closeMenu">
    @if ($menuPostId !== '')
        <native:column class="bg-theme-surface border-theme-outline-variant w-full gap-1 border-t px-5 py-4">
            @if ($menuError !== '')
                <native:text class="text-theme-destructive text-sm">{{ $menuError }}</native:text>
            @endif

            @if ($menuStep === 'confirm-delete')
                <native:column class="w-full gap-2 py-2">
                    <native:text class="text-theme-on-surface text-base font-semibold">Delete this post?</native:text>
                    <native:text class="text-theme-on-surface-variant text-sm">Are you sure you want to delete this post?</native:text>
                    <native:row class="w-full items-center gap-3 pt-2">
                        <native:button variant="secondary" @tap="menuBack">Cancel</native:button>
                        <native:button
                            variant="primary"
                            :disabled="$menuBusy"
                            @tap="deleteMenuPost"
                        >{{ $menuBusy ? 'Removing…' : 'Delete' }}</native:button>
                    </native:row>
                </native:column>
            @elseif ($menuStep === 'confirm-ignore' || $menuStep === 'confirm-report')
                <native:column class="w-full gap-2 py-2">
                    <native:text class="text-theme-on-surface text-base font-semibold">{{ $menuStep === 'confirm-report' ? 'Report this question?' : 'Ignore this question?' }}</native:text>
                    <native:text class="text-theme-on-surface-variant text-sm">{{ $menuStep === 'confirm-report' ? 'The question will be sent for review.' : 'This question will be removed from your received questions.' }}</native:text>
                    <native:row class="w-full items-center gap-3 pt-2">
                        <native:button variant="secondary" @tap="menuBack">Cancel</native:button>
                        @if ($menuStep === 'confirm-report')
                            <native:button variant="primary" :disabled="$menuBusy" @tap="reportMenuPost">
                                {{ $menuBusy ? 'Reporting…' : 'Report' }}
                            </native:button>
                        @else
                            <native:button variant="primary" :disabled="$menuBusy" @tap="ignoreMenuPost">
                                {{ $menuBusy ? 'Ignoring…' : 'Ignore' }}
                            </native:button>
                        @endif
                    </native:row>
                </native:column>
            @elseif ($menuStep === 'edit' || $menuStep === 'answer')
                <native:column class="w-full gap-3 py-2">
                    <native:text class="text-theme-on-surface text-base font-semibold">{{ $menuStep === 'answer' ? 'Answer question' : 'Edit answer' }}</native:text>
                    <native:column class="w-full gap-1">
                        <native:outlined-text-input
                            label="Answer"
                            placeholder="Write your answer..."
                            multiline
                            native:model="editAnswer"
                            :selection-debounce-ms="400"
                            @selectionChange="updateMentionSelection(-2)"
                        />
                        @if ($mentionPostIndex === -2)
                            @if ($mentionKind === 'hashtag')
                                <x-hashtag-suggestions :hashtags="$mentionResults" />
                            @else
                                <x-mention-suggestions :users="$mentionResults" />
                            @endif
                        @endif
                        @if (mb_strlen($editAnswer) > 800)
                            <native:text class="text-xs text-right {{ mb_strlen($editAnswer) >= 1000 ? 'text-theme-destructive' : 'text-theme-on-surface-variant' }}">{{ mb_strlen($editAnswer) }} / 1000</native:text>
                        @endif
                    </native:column>
                    <native:row class="w-full items-center gap-3">
                        <native:button variant="secondary" @tap="menuBack">Cancel</native:button>
                        <native:button
                            variant="primary"
                            :disabled="$menuBusy"
                            @tap="saveAnswerMenu"
                        >{{ $menuBusy ? ($menuStep === 'answer' ? 'Answering…' : 'Saving…') : ($menuStep === 'answer' ? 'Post answer' : 'Save answer') }}</native:button>
                    </native:row>
                </native:column>
            @elseif ($menuStep === 'likers')
                <native:column class="w-full gap-1 py-2">
                    <native:row class="w-full items-center gap-3">
                        <native:pressable class="py-1" @tap="menuBack" a11y-label="Back to menu">
                            <native:text class="text-theme-primary text-sm font-semibold">Back</native:text>
                        </native:pressable>
                        <native:text class="text-theme-on-surface text-base font-semibold">{{ count($likers) > 0 ? 'Liked by' : 'No one has liked this post yet' }}</native:text>
                    </native:row>
                    @if ($likersError !== '')
                        <native:text class="text-theme-destructive text-sm">{{ $likersError }}</native:text>
                    @endif
                    @foreach ($likers as $liker)
                        <x-user-row :user="$liker" />
                    @endforeach
                    {{-- Native lists cannot be nested inside a bottom sheet. --}}
                    @if ($likersHasMore)
                        <x-load-more tap="loadMoreLikers" :error="$likersError" />
                    @endif
                </native:column>
            @else
                @if (\App\Support\DisplayText::plain(data_get($menuPost, 'answer')) !== '')
                    <native:pressable
                        class="w-full py-3"
                        @tap="translatePost('{{ $menuPostId }}', 'answer')"
                        a11y-label="Translate answer"
                    >
                        <native:row class="w-full items-center gap-3">
                            <native:icon
                                :ios="\App\Icons\Ios::Globe"
                                :android="\App\Icons\AndroidOutlined::Language"
                                :size="20"
                                color="#64748B"
                                dark-color="#94A3B8"
                            />
                            <native:text class="text-theme-on-surface text-sm font-medium">Translate answer</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                @if (\App\Support\DisplayText::plain(data_get($menuPost, 'content')) !== '')
                    <native:pressable
                        class="w-full py-3"
                        @tap="translatePost('{{ $menuPostId }}', 'content')"
                        a11y-label="Translate question"
                    >
                        <native:row class="w-full items-center gap-3">
                            <native:icon
                                :ios="\App\Icons\Ios::Globe"
                                :android="\App\Icons\AndroidOutlined::Language"
                                :size="20"
                                color="#64748B"
                                dark-color="#94A3B8"
                            />
                            <native:text class="text-theme-on-surface text-sm font-medium">Translate question</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                @if ($canPinMenu)
                    <native:pressable
                        class="w-full py-3"
                        @tap="togglePinMenu"
                        a11y-label="{{ $menuPinned ? 'Unpin this post' : 'Pin this post' }}"
                    >
                        <native:row class="w-full items-center gap-3">
                            <native:icon
                                :ios="\App\Icons\Ios::Pin"
                                :android="\App\Icons\Android::PushPin"
                                :size="20"
                                color="#EC4899"
                                dark-color="#EC4899"
                            />
                            <native:text class="text-theme-on-surface text-sm font-medium">{{ $menuPinned ? 'Unpin' : 'Pin' }}</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                @if ($canAnswerMenu)
                    <native:pressable class="w-full py-3" @tap="startAnswerMenu" a11y-label="Answer this question">
                        <native:row class="w-full items-center gap-3">
                            <native:icon
                                :ios="\App\Icons\Ios::Pencil"
                                :android="\App\Icons\AndroidOutlined::Edit"
                                :size="20"
                                color="#64748B"
                                dark-color="#94A3B8"
                            />
                            <native:text class="text-theme-on-surface text-sm font-medium">Answer</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                @if ($canEditMenu)
                    <native:pressable class="w-full py-3" @tap="startEditMenu" a11y-label="Edit answer">
                        <native:row class="w-full items-center gap-3">
                            <native:icon
                                :ios="\App\Icons\Ios::Pencil"
                                :android="\App\Icons\AndroidOutlined::Edit"
                                :size="20"
                                color="#64748B"
                                dark-color="#94A3B8"
                            />
                            <native:text class="text-theme-on-surface text-sm font-medium">Edit</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                @if ($canDeleteMenu)
                    <native:pressable class="w-full py-3" @tap="askDeleteMenu" a11y-label="Delete this post">
                        <native:row class="w-full items-center gap-3">
                            <native:icon
                                :ios="\App\Icons\Ios::Trash"
                                :android="\App\Icons\AndroidOutlined::Delete"
                                :size="20"
                                color="#64748B"
                                dark-color="#94A3B8"
                            />
                            <native:text class="text-theme-destructive text-sm font-medium">Delete</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                @if ($canIgnoreMenu)
                    <native:pressable class="w-full py-3" @tap="askIgnoreMenu" a11y-label="Ignore this question">
                        <native:row class="w-full items-center gap-3">
                            <native:icon
                                :ios="\App\Icons\Ios::EyeSlash"
                                :android="\App\Icons\AndroidOutlined::VisibilityOff"
                                :size="20"
                                color="#64748B"
                                dark-color="#94A3B8"
                            />
                            <native:text class="text-theme-on-surface text-sm font-medium">Ignore</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                @if ($canReportMenu)
                    <native:pressable class="w-full py-3" @tap="askReportMenu" a11y-label="Report this question">
                        <native:row class="w-full items-center gap-3">
                            <native:icon
                                :ios="\App\Icons\Ios::ExclamationmarkTriangle"
                                :android="\App\Icons\AndroidOutlined::Flag"
                                :size="20"
                                color="#64748B"
                                dark-color="#94A3B8"
                            />
                            <native:text class="text-theme-destructive text-sm font-medium">Report</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                @if ($canViewMenuLikes)
                    <native:pressable class="w-full py-3" @tap="openLikersMenu" a11y-label="View likes">
                        <native:row class="w-full items-center gap-3">
                            <native:icon
                                :ios="\App\Icons\Ios::Heart"
                                :android="\App\Icons\AndroidOutlined::FavoriteBorder"
                                :size="20"
                                color="#EC4899"
                                dark-color="#EC4899"
                            />
                            <native:text class="text-theme-on-surface text-sm font-medium">View likes</native:text>
                        </native:row>
                    </native:pressable>
                @endif
            @endif
        </native:column>
    @endif
</native:bottom-sheet>

<native:modal
    native:key="post-image-preview"
    :visible="$activeImageGallery !== []"
    @dismiss="closeImageFullscreen"
    a11y-label="Full-screen post image gallery"
>
    <native:column class="bg-theme-surface h-full w-full">
        @if ($activeImageGallery !== [])
            <native:pager
                native:key="post-image-gallery"
                class="h-full w-full flex-1"
                :count="count($activeImageGallery)"
                :page="$activeImageIndex"
                horizontal
                on-page-change="setImageGalleryPage"
                a11y-label="Post image gallery"
            >
                @foreach ($activeImageGallery as $index => $image)
                    <native:column
                        native:key="post-image-gallery-{{ $index }}"
                        class="bg-theme-surface h-full w-full items-center justify-center"
                    >
                        <native:image
                            :src="$image"
                            alt="Post image {{ $index + 1 }} of {{ count($activeImageGallery) }}"
                            class="h-full w-full object-contain"
                            a11y-label="Post image {{ $index + 1 }} of {{ count($activeImageGallery) }}"
                        />
                    </native:column>
                @endforeach
            </native:pager>
        @endif
    </native:column>
</native:modal>
