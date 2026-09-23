@props([
    'src' => '',
    'alt' => '',
    'class' => '',
])

<native:image src="{{ $src }}" alt="{{ $alt }}" class="bg-theme-surface-variant {{ $class }}" />
