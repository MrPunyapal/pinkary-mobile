<?php

declare(strict_types=1);

use App\NativeComponents\EditProfile;
use Native\Mobile\Edge\Web\Contracts\HasWebHead;

it('styles select options in the Jump web preview', function (): void {
    $component = new EditProfile;

    expect($component)
        ->toBeInstanceOf(HasWebHead::class)
        ->and($component->webHead())
        ->toContain('select option')
        ->toContain('var(--color-theme-surface)')
        ->toContain('var(--color-theme-on-surface)');
});
