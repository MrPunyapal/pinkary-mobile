<?php

declare(strict_types=1);

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Welcome extends NativeComponent
{
    public function navTitle(): string
    {
        return 'Pinkary';
    }

    public function login(): void
    {
        $this->navigate($this->route('mobile.login'));
    }

    public function register(): void
    {
        $this->navigate($this->route('mobile.register'));
    }

    public function render(): View
    {
        return view('native.welcome');
    }
}
