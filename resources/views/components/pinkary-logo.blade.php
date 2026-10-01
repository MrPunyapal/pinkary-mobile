@props(['dark' => null, 'height' => 48])

{{-- Wordmark parity with the web's components/pinkary-logo: the guest
     screens show a white wordmark on the dark card and a slate one on
     light. public_path() was a server disk path the phone cannot load, so
     the wordmark is fetched over http from the site that serves it — the
     same absolute-URL pattern the profile QR sheet uses. The site ships
     one wordmark PNG (transparent, white), so light mode tints it to the
     web's slate-950 fill instead of requesting a second asset. --}}
@php
    $isDark = $dark ?? isDark();
    $wordmark = rtrim((string) config('services.pinkary.url'), '/').'/img/logo-text.png';
@endphp

<native:image src="{{ $wordmark }}" alt="Pinkary" class="w-40 h-12 object-contain" :tintColor="$isDark ? '#FFFFFF' : '#0F172A'" />
