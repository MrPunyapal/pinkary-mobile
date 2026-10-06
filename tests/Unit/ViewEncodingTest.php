<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * A UTF-8 byte sequence decoded as cp1252 and re-encoded: â€™, â€œ, â€¦, Ã©.
 * None of these letters appear in the interface, so finding one always means a file
 * was written with the wrong encoding, and it renders as garbage to the user.
 */
function mojibakeOffenders(string $directory, string $suffix): array
{
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), $suffix)) {
            continue;
        }

        if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Icons'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        if (preg_match('/[\x{00C2}\x{00C3}\x{00E2}]/u', (string) file_get_contents($file->getPathname())) === 1) {
            $offenders[] = str_replace('\\', '/', mb_substr($file->getPathname(), mb_strlen($directory) + 1));
        }
    }

    sort($offenders);

    return $offenders;
}

it('ships blade views without windows-1252 mojibake', function (): void {
    expect(mojibakeOffenders(resource_path('views'), '.blade.php'))->toBe([]);
});

it('ships php sources without windows-1252 mojibake', function (): void {
    expect(mojibakeOffenders(app_path(), '.php'))->toBe([]);
});

it('ships blade views and php sources as utf-8 without a byte order mark', function (): void {
    $withBom = [];

    foreach ([resource_path('views'), app_path()] as $directory) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php') && ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $bytes = (string) file_get_contents($file->getPathname());

            if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
                $withBom[] = str_replace('\\', '/', mb_substr($file->getPathname(), mb_strlen($directory) + 1));
            }
        }
    }

    sort($withBom);

    expect($withBom)->toBe([]);
});
