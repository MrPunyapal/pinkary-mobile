@props(['dark' => false, 'height' => 48])

<native:image src="{{ public_path('img/logo/pinkary-logo-'.($dark ? 'black' : 'white').'.png') }}" alt="Pinkary" class="h-{{ $height }} w-auto" />
