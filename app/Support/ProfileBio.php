<?php

declare(strict_types=1);

namespace App\Support;

final class ProfileBio
{
    /**
     * @return list<array{text: string, type: string, target: string}>
     */
    public static function runs(mixed $bio): array
    {
        if (! is_string($bio) || $bio === '') {
            return [];
        }

        $parts = preg_split(
            '~((?<![\p{L}\p{N}_@./])@[A-Za-z0-9_]{4,50}(?![A-Za-z0-9_])|(?<![\p{L}\p{N}_/#])#[A-Za-z0-9]{1,200}(?![A-Za-z0-9]))~u',
            $bio,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if ($parts === false) {
            return [['text' => $bio, 'type' => 'text', 'target' => '']];
        }

        $runs = [];

        foreach ($parts as $part) {
            if (preg_match('/^@([A-Za-z0-9_]{4,50})$/D', $part, $match) === 1
                && preg_match('/[A-Za-z].*[A-Za-z]/', $match[1]) === 1) {
                $runs[] = ['text' => $part, 'type' => 'mention', 'target' => $match[1]];

                continue;
            }

            if (preg_match('/^#([A-Za-z0-9]{1,200})$/D', $part, $match) === 1) {
                $runs[] = ['text' => $part, 'type' => 'hashtag', 'target' => $match[1]];

                continue;
            }

            $runs[] = ['text' => $part, 'type' => 'text', 'target' => ''];
        }

        return $runs;
    }
}
