<native:bottom-sheet native:key="post-menu-sheet" :visible="$menuPostId !== ''" @dismiss="closeMenu">
    @if ($menuPostId !== '')
        {{-- The hairline mirrors the web sheet edge (border-slate-200/80). --}}
        <native:column class="w-full gap-1 px-5 py-4 bg-theme-surface border-t border-theme-outline-variant">
            @if ($menuError !== '')
                <native:text class="text-sm text-theme-destructive">{{ $menuError }}</native:text>
            @endif

            @if ($menuStep === 'confirm-delete')
                <native:column class="w-full gap-2 py-2">
                    <native:text class="text-base font-semibold text-theme-on-surface">Delete this post?</native:text>
                    <native:text class="text-sm text-theme-on-surface-variant">Are you sure you want to delete this post?</native:text>
                    <native:row class="w-full items-center gap-3 pt-2">
                        <native:button variant="secondary" @tap="menuBack">Cancel</native:button>
                        <native:button variant="primary" :disabled="$menuBusy" @tap="ignoreMenuPost">{{ $menuBusy ? 'Deleting…' : 'Delete' }}</native:button>
                    </native:row>
                </native:column>
            @elseif ($menuStep === 'edit')
                <native:column class="w-full gap-3 py-2">
                    <native:text class="text-base font-semibold text-theme-on-surface">Edit answer</native:text>
                    <native:column class="w-full gap-1">
                        <native:outlined-text-input label="Answer" placeholder="Write your answer..." multiline native:model="editAnswer" />
                        @if (mb_strlen($editAnswer) > 800)
                            <native:text class="text-xs text-right {{ mb_strlen($editAnswer) >= 1000 ? 'text-theme-destructive' : 'text-theme-on-surface-variant' }}">{{ mb_strlen($editAnswer) }} / 1000</native:text>
                        @endif
                    </native:column>
                    <native:row class="w-full items-center gap-3">
                        <native:button variant="secondary" @tap="menuBack">Cancel</native:button>
                        <native:button variant="primary" :disabled="$menuBusy" @tap="saveAnswerMenu">{{ $menuBusy ? 'Saving…' : 'Save answer' }}</native:button>
                    </native:row>
                </native:column>
            @elseif ($menuStep === 'likers')
                {{-- Web parity: livewire/likes/index.blade.php:7-13 titles the
                    sheet "Liked by", or states the empty case outright. --}}
                <native:column class="w-full gap-1 py-2">
                    <native:row class="w-full items-center gap-3">
                        <native:pressable class="py-1" @tap="menuBack" a11y-label="Back to menu">
                            <native:text class="text-sm font-semibold text-theme-primary">Back</native:text>
                        </native:pressable>
                        <native:text class="text-base font-semibold text-theme-on-surface">{{ count($likers) > 0 ? 'Liked by' : 'No one has liked this post yet' }}</native:text>
                    </native:row>
                    @if ($likersError !== '')
                        <native:text class="text-sm text-theme-destructive">{{ $likersError }}</native:text>
                    @endif
                    @foreach ($likers as $liker)
                        <x-user-row :user="$liker" />
                    @endforeach
                    {{-- x-load-more, not a native:list: a bottom sheet cannot host
                        one, and the project rule allows it here. --}}
                    @if ($likersHasMore)
                        <x-load-more tap="loadMoreLikers" :error="$likersError" />
                    @endif
                </native:column>
            @else
                @if ($canPinMenu)
                    <native:pressable class="w-full py-3" @tap="togglePinMenu" a11y-label="{{ $menuPinned ? 'Unpin this post' : 'Pin this post' }}">
                        <native:row class="w-full items-center gap-3">
                            <native:icon :ios="\App\Icons\Ios::Pin" :android="\App\Icons\Android::PushPin" :size="20" color="#EC4899" dark-color="#EC4899" />
                            <native:text class="text-sm font-medium text-theme-on-surface">{{ $menuPinned ? 'Unpin' : 'Pin' }}</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                @if ($canEditMenu)
                    {{-- The web menu item is a bare "Edit"
                        (../pinkary/resources/views/livewire/questions/show.blade.php:394);
                        its modal heading is the one that says "Edit Answer". --}}
                    <native:pressable class="w-full py-3" @tap="startEditMenu" a11y-label="Edit answer">
                        <native:row class="w-full items-center gap-3">
                            <native:icon :ios="\App\Icons\Ios::Pencil" :android="\App\Icons\AndroidOutlined::Edit" :size="20" color="#64748B" dark-color="#94A3B8" />
                            <native:text class="text-sm font-medium text-theme-on-surface">Edit</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                @if ($canDeleteMenu)
                    <native:pressable class="w-full py-3" @tap="askDeleteMenu" a11y-label="Delete this post">
                        <native:row class="w-full items-center gap-3">
                            <native:icon :ios="\App\Icons\Ios::Trash" :android="\App\Icons\AndroidOutlined::Delete" :size="20" color="#64748B" dark-color="#94A3B8" />
                            <native:text class="text-sm font-medium text-theme-destructive">Delete</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                @if ($canViewMenuLikes)
                    <native:pressable class="w-full py-3" @tap="openLikersMenu" a11y-label="View likes">
                        <native:row class="w-full items-center gap-3">
                            <native:icon :ios="\App\Icons\Ios::Heart" :android="\App\Icons\AndroidOutlined::FavoriteBorder" :size="20" color="#EC4899" dark-color="#EC4899" />
                            <native:text class="text-sm font-medium text-theme-on-surface">View likes</native:text>
                        </native:row>
                    </native:pressable>
                @endif
                {{-- No Share row: the post action row already carries a share
                    control (components/post.blade.php:220). No Copy link row
                    either — the platform has no clipboard, so it would be a
                    dead control. --}}
            @endif
        </native:column>
    @endif
</native:bottom-sheet>
