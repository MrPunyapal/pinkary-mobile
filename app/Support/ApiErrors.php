<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\Response;

final class ApiErrors
{
    /**
     * Surface the most useful server message: the first validation error
     * for a 422, the rate-limit message for a 429, else a safe fallback.
     */
    public static function first(Response $response, string $fallback): string
    {
        /** @var array<string, list<string>> $errors */
        $errors = $response->json('errors', []);
        $first = collect($errors)->flatten()->first();

        if (is_string($first) && $first !== '') {
            return $first;
        }

        $message = $response->json('message');

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return $fallback;
    }
}
