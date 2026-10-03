<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\Response;

final class ApiErrors
{
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

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, string>  $fields  server key => fieldErrors key
     * @return array{fieldErrors: array<string, string>, message: string}
     */
    public static function split(array $errors, array $fields, ?string $fallback = null): array
    {
        /** @var array<string, string> $fieldErrors */
        $fieldErrors = [];
        $first = null;
        $unmappedFirst = null;

        foreach ($errors as $key => $messages) {
            $message = is_array($messages) ? ($messages[0] ?? null) : $messages;

            if (! is_string($message) || $message === '') {
                continue;
            }

            $first ??= $message;

            if (isset($fields[$key])) {
                $fieldErrors[$fields[$key]] = $message;
            } else {
                $unmappedFirst ??= $message;
            }
        }

        return [
            'fieldErrors' => $fieldErrors,
            'message' => $unmappedFirst ?? $first ?? $fallback ?? 'Something went wrong. Please try again.',
        ];
    }
}
