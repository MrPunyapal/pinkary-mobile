<?php

declare(strict_types=1);

namespace App\NativeComponents\Concerns;

trait ProvidesWebPreviewStyles
{
    public function webHead(): string
    {
        return <<<'HTML'
<style>
select option {
    background-color: var(--color-theme-surface);
    color: var(--color-theme-on-surface);
}
</style>
HTML;
    }
}
