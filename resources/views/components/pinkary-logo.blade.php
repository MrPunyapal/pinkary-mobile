@props(['dark' => null, 'height' => 48])

{{-- Native images need device-accessible URLs, not PHP filesystem paths. --}}
@php
    $isDark = $dark ?? isDark();
    $wordmark = mb_rtrim((string) config('services.pinkary.url'), '/').'/img/logo-text.png';
@endphp

<native:image
    src="{{ $wordmark }}"
    alt="Pinkary"
    class="h-12 w-40 object-contain"
    :tintColor="$isDark ? '#FFFFFF' : '#0F172A'"
/>
