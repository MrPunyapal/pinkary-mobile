@props(['bio'])

@php
    $runs = \App\Support\ProfileBio::runs($bio);
@endphp

<native:row class="mt-3 w-full flex-wrap items-center justify-center gap-0">
    @foreach ($runs as $index => $run)
        @if ($run['type'] === 'mention')
            <native:pressable
                native:key="profile-bio-{{ $index }}"
                @tap="openProfile('{{ $run['target'] }}')"
                a11y-label="Open {{ '@'.$run['target'] }}’s profile"
            >
                <native:text :text="$run['text']" class="text-theme-link text-center text-sm leading-relaxed" />
            </native:pressable>
        @elseif ($run['type'] === 'hashtag')
            <native:pressable
                native:key="profile-bio-{{ $index }}"
                @tap="openBioHashtag('{{ $run['target'] }}')"
                a11y-label="Open posts tagged {{ '#'.$run['target'] }}"
            >
                <native:text :text="$run['text']" class="text-theme-link text-center text-sm leading-relaxed" />
            </native:pressable>
        @else
            <native:text
                native:key="profile-bio-{{ $index }}"
                :text="$run['text']"
                class="text-theme-on-surface text-center text-sm leading-relaxed whitespace-pre-wrap"
            />
        @endif
    @endforeach
</native:row>
