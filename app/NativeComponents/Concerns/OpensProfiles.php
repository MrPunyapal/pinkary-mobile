<?php

declare(strict_types=1);

namespace App\NativeComponents\Concerns;

use Native\Mobile\Edge\NativeComponent;

/**
 * Every screen that renders a post row links a username to that profile. The
 * navigation was copied verbatim into four components; a change to the route
 * or the empty-handling had to be made four times.
 *
 * @mixin NativeComponent
 */
trait OpensProfiles
{
    public function openProfile(string $username): void
    {
        if ($username === '') {
            return;
        }

        $this->navigate($this->route('mobile.user', ['username' => $username]));
    }
}
