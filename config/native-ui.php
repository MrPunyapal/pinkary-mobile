<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Theme
    |---------------------------------------------------------------------------
    |
    | Color tokens (open-ended map), 4 radii, 4 font sizes, font family.
    |
    | "on-X" means "color of content placed ON a surface of color X"
    |   — i.e., text/icons on that background.
    |
    | The token map is OPEN-ENDED: add any key your design needs (e.g. a
    | `warning` pair) to both blocks and `bg-theme-warning` /
    | `text-theme-on-warning` / `border-theme-warning` resolve immediately.
    | Theme classes also accept opacity modifiers — `bg-theme-primary/15`
    | is the tonal-fill idiom (the alpha applies to the dark companion
    | too). In PHP (layout chrome builders, dynamic styling) read tokens
    | with the appearance-aware `theme()` helper: `theme('primary')`.
    |
    | Color tokens accept:
    |   - CSS hex: '#B91C1C', '#F00', or with alpha '#8B5CF680' (#RRGGBBAA)
    |   - Tailwind palette names: 'red-300', 'orange-800'
    |   - Opacity modifiers on either: 'red-300/20', '#8B5CF6/50'
    |
    | Dark mode is auto-derived from `light` when `dark` is not set. To opt
    | into explicit dark tokens, fill out the `dark` block.
    |
    | The default pairs meet WCAG AA (4.5:1) — if you customize, keep each
    | `on-*` color at 4.5:1 contrast against its background token.
    |
    */

    'theme' => [

        'light' => [
            'primary' => '#EC4899',
            'link' => '#2563EB',
            'on-primary' => '#FFFFFF',

            'secondary' => '#475569',
            'on-secondary' => '#FFFFFF',

            'surface' => '#FFFFFF',
            'on-surface' => '#0F172A',
            'background' => '#F1F5F9',
            'on-background' => '#0F172A',
            'on-body' => '#334155',

            'surface-variant' => '#F1F5F9',
            'on-surface-variant' => '#475569',

            'bubble' => '#F8FAFC',
            'on-bubble' => '#0F172A',

            'avatar' => '#F1F5F9',
            'on-avatar' => '#475569',

            'poll-track' => '#E2E8F0',
            'poll-fill' => '#94A3B8',

            'outline' => '#CBD5E1',
            'outline-variant' => '#EDF1F7',

            'destructive' => '#B91C1C',
            'on-destructive' => '#FFFFFF',

            'success' => '#15803D',
            'on-success' => '#FFFFFF',

            'accent' => '#EC4899',
            'on-accent' => '#FFFFFF',
        ],

        'dark' => [
            // Unspecified dark tokens are derived from the light palette.
            'primary' => '#EC4899',
            'link' => '#3B82F6',
            'on-primary' => '#FFFFFF',

            'secondary' => '#151C2A',
            'on-secondary' => '#F8FAFC',

            'surface' => '#07101F',
            'on-surface' => '#F8FAFC',
            'background' => '#060C18',
            'on-background' => '#F8FAFC',
            'on-body' => '#E2E8F0',

            'surface-variant' => '#151C2A',
            'on-surface-variant' => '#94A3B8',

            'bubble' => '#0B1324',
            'on-bubble' => '#F8FAFC',

            'avatar' => '#10182B',
            'on-avatar' => '#94A3B8',

            'poll-track' => '#1E293B',
            'poll-fill' => '#475569',

            'outline' => '#334155',
            'outline-variant' => '#131F30',

            'destructive' => '#F87171',
            'on-destructive' => '#0F172A',

            'success' => '#4ADE80',
            'on-success' => '#052E16',

            'accent' => '#EC4899',
            'on-accent' => '#FFFFFF',
        ],

        // Corner radii (points / dp).
        'radius-sm' => 4,
        'radius-md' => 8,
        'radius-lg' => 16,
        'radius-full' => 9999,

        // Font size scale (points / sp).
        'font-sm' => 14,
        'font-md' => 16,
        'font-lg' => 20,
        'font-xl' => 24,

    ],

    'profile-gradients' => [
        'from-blue-500 to-purple-600' => ['direction' => [1, 0], 'from' => '#3B82F6', 'to' => '#9333EA'],
        'from-blue-500 to-teal-700' => ['direction' => [1, 0], 'from' => '#3B82F6', 'to' => '#0F766E'],
        'from-red-500 to-orange-600' => ['direction' => [1, 0], 'from' => '#EF4444', 'to' => '#EA580C'],
        'from-purple-500 to-pink-500' => ['direction' => [1, 0], 'from' => '#A855F7', 'to' => '#EC4899'],
        'from-indigo-500 to-lime-700' => ['direction' => [1, 0], 'from' => '#6366F1', 'to' => '#4D7C0F'],
        'from-yellow-600 to-blue-600' => ['direction' => [1, 0], 'from' => '#CA8A04', 'to' => '#2563EB'],
    ],

    /*
    |---------------------------------------------------------------------------
    | Fonts
    |---------------------------------------------------------------------------
    |
    | Semantic names for bundled fonts (resources/fonts/ file tokens, minus
    | the extension). Use an alias anywhere a font token works — the `font`
    | attribute (`font="accent"`), chrome ->font() builders, or the layout
    | $font property. The `default` alias is the app-wide default font:
    | 'System' resolves to the platform face (San Francisco on iOS, Roboto
    | on Android); set a bundled token to apply it everywhere. Download one
    | with `php artisan native:font Inter --default`. Per-element `font`
    | attributes and font-serif / font-mono classes still win over the default.
    |
    |   'fonts' => [
    |       'default' => 'Inter-Regular',
    |       'accent'  => 'DynaPuff-Regular',
    |   ],
    |
    */

    'fonts' => [
        'default' => 'System',
    ],

];
