<?php

/**
 * Native UI — Theme Tokens
 *
 * Published via `php artisan vendor:publish --tag=native-ui-config`.
 * Edit to customize your app's visual identity in one place.
 *
 * For dynamic per-tenant theming, use Native\Mobile\UI\Theme::merge([...])
 * from a service provider. Runtime merges deep-merge on top of these values.
 *
 * Decision log: /docs/NATIVE-UI-REWRITE-PLAN.md (D — theme layer)
 */

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
            // Primary brand color — Pinkary pink, used for filled buttons, active states, key accents.
            'primary' => '#EC4899',
            'link' => '#2563EB',
            'on-primary' => '#FFFFFF',

            // Secondary / muted action color.
            'secondary' => '#475569',
            'on-secondary' => '#FFFFFF',

            // Surface = cards, sheets, dialogs. Background = page root.
            'surface' => '#FFFFFF',
            'on-surface' => '#0F172A',
            'background' => '#F1F5F9',
            'on-background' => '#0F172A',
            // on-body = body copy on the page background (slate-700 /
            // slate-200 on web) — answers, question text, poll labels.
            'on-body' => '#334155',

            // Surface variant = filled text fields, muted tonal surfaces.
            // on-surface-variant = muted label/hint text on those surfaces.
            'surface-variant' => '#F1F5F9',
            'on-surface-variant' => '#475569',

            // Question bubble = asked-question card (slate-50 on web).
            'bubble' => '#F8FAFC',
            'on-bubble' => '#0F172A',

            // Avatar fallback fill (slate-100 on web).
            'avatar' => '#F1F5F9',
            'on-avatar' => '#475569',

            // Poll bar: track (slate-200 / slate-800) and idle fill (slate-400 / slate-600).
            // The voted fill reuses primary; text on it is white in both modes.
            'poll-track' => '#E2E8F0',
            'poll-fill' => '#94A3B8',

            // Outline = neutral borders (text fields, dividers, cards).
            // outline-variant = softer edges: hairline dividers, card seams.
            'outline' => '#CBD5E1',
            'outline-variant' => '#E2E8F0',

            // Destructive actions — maps to `variant="destructive"` on components.
            'destructive' => '#B91C1C',
            'on-destructive' => '#FFFFFF',

            // Success / "safe to proceed" — confirmations, verified badges.
            'success' => '#15803D',
            'on-success' => '#FFFFFF',

            // Tertiary accent — for highlights, badges, emphasis not covered by primary.
            'accent' => '#EC4899',
            'on-accent' => '#FFFFFF',
        ],

        'dark' => [
            // Leave empty or partial to auto-derive from `light` (luminance inversion).
            // Specify any token here to override the derived value.
            // Pink stays identical in both modes on web.
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
            'outline-variant' => '#1E293B',

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
