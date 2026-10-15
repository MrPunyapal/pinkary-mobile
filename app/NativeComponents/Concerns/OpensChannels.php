<?php

declare(strict_types=1);

namespace App\NativeComponents\Concerns;

use Native\Mobile\Edge\NativeComponent;

/** @mixin NativeComponent */
trait OpensChannels
{
    public function openChannel(string $slug, string $name): void
    {
        if (mb_trim($slug) === '') {
            return;
        }

        $this->navigate(
            $this->route('mobile.channel', ['slug' => $slug]),
            ['channelName' => $name],
        );
    }
}
