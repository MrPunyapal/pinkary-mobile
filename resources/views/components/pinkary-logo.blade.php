@props(['dark' => null, 'height' => 48])

{{-- Native images need device-accessible URLs, not PHP filesystem paths. --}}
@php
    $isDark = $dark ?? isDark();
    $wordmark = rtrim((string) config('services.pinkary.url'), '/').'/img/logo-text.png';
@endphp

<native:image src="{{ $wordmark }}" alt="Pinkary" class="w-40 h-12 object-contain" :tintColor="$isDark ? '#FFFFFF' : '#0F172A'" />
