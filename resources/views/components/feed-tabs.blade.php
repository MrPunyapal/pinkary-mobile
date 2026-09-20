@props(['tab' => 'recent'])

<native:row class="w-full items-center justify-center gap-1 px-4 py-2.5 bg-theme-surface border-b border-theme-outline-variant">
    <native:pressable class="px-4 py-1.5 rounded-full {{ $tab === 'recent' ? 'bg-theme-primary' : '' }}" @tap="showRecent" a11y-label="Show Recent feed">
        <native:text class="text-sm {{ $tab === 'recent' ? 'text-white font-semibold' : 'text-theme-on-surface-variant' }}">Recent</native:text>
    </native:pressable>
    <native:pressable class="px-4 py-1.5 rounded-full {{ $tab === 'following' ? 'bg-theme-primary' : '' }}" @tap="showFollowing" a11y-label="Show Following feed">
        <native:text class="text-sm {{ $tab === 'following' ? 'text-white font-semibold' : 'text-theme-on-surface-variant' }}">Following</native:text>
    </native:pressable>
    <native:pressable class="px-4 py-1.5 rounded-full {{ $tab === 'trending' ? 'bg-theme-primary' : '' }}" @tap="showTrending" a11y-label="Show Trending feed">
        <native:text class="text-sm {{ $tab === 'trending' ? 'text-white font-semibold' : 'text-theme-on-surface-variant' }}">Trending</native:text>
    </native:pressable>
</native:row>
