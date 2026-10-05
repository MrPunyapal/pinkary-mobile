<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LogicException;
use Native\Mobile\Facades\SecureStorage;

final class PostViews
{
    private const int BATCH_SIZE = 10;

    private const string VIEWER_KEY = 'pinkary_impression_viewer';

    public static function viewerId(): string
    {
        $stored = SecureStorage::read(self::VIEWER_KEY);
        $viewer = $stored->found() ? $stored->value : session(self::VIEWER_KEY);
        if (is_string($viewer) && Str::isUuid($viewer)) {
            return $viewer;
        }

        $viewer = (string) Str::uuid();
        if (! SecureStorage::set(self::VIEWER_KEY, $viewer)) {
            session([self::VIEWER_KEY => $viewer]);
        }

        return $viewer;
    }

    public static function enqueue(string $id, ?string $token, string $viewer): bool
    {
        $key = self::queueKey($token, $viewer);
        $ready = false;

        self::lock($key)->block(1, static function () use ($key, $id, &$ready): void {
            if (self::cache()->has($key.':seen:'.$id)) {
                return;
            }

            $queue = self::queue($key);
            if (! in_array($id, $queue['pending'], true)) {
                $queue['pending'][] = $id;
                self::cache()->put($key, $queue, now()->addHours(2));
            }

            $ready = count($queue['pending']) >= self::BATCH_SIZE
                && $queue['lease_until'] <= now()->getTimestamp()
                && $queue['retry_at'] <= now()->getTimestamp();
        });

        return $ready;
    }

    /** @return array{key: string, lease: string, ids: list<string>}|null */
    public static function takeBatch(?string $token, string $viewer, bool $force = false): ?array
    {
        $key = self::queueKey($token, $viewer);
        $batch = null;

        self::lock($key)->block(1, static function () use ($key, $force, &$batch): void {
            $queue = self::queue($key);
            $timestamp = now()->getTimestamp();

            if ($queue['pending'] === [] || $queue['retry_at'] > $timestamp || $queue['lease_until'] > $timestamp) {
                return;
            }

            if (! $force && count($queue['pending']) < self::BATCH_SIZE) {
                return;
            }

            $queue['lease'] = (string) Str::uuid();
            $queue['lease_until'] = $timestamp + 30;
            self::cache()->put($key, $queue, now()->addHours(2));

            $batch = ['key' => $key, 'lease' => $queue['lease'], 'ids' => array_slice($queue['pending'], 0, self::BATCH_SIZE)];
        });

        return $batch;
    }

    /**
     * Acknowledge from the background task, not a screen-scoped callback.
     * Expiring leases let a restarted app recover interrupted deliveries.
     *
     * @param  array{key: string, lease: string, ids: list<string>}  $batch
     */
    public static function finishBatch(array $batch, bool $accepted, int $retryAfter = 0): void
    {
        $key = $batch['key'];

        self::lock($key)->block(1, static function () use ($key, $batch, $accepted, $retryAfter): void {
            $queue = self::queue($key);
            if ($queue['lease'] !== $batch['lease']) {
                return;
            }

            if ($accepted) {
                foreach ($batch['ids'] as $id) {
                    self::cache()->put($key.':seen:'.$id, true, now()->addHours(2));
                }

                $queue['pending'] = array_values(array_diff($queue['pending'], $batch['ids']));
                $queue['attempts'] = 0;
                $queue['retry_at'] = 0;
            } else {
                $queue['attempts']++;
                $delay = max(min(60, 5 * (2 ** min($queue['attempts'] - 1, 4))), min(3600, $retryAfter));
                $queue['retry_at'] = now()->getTimestamp() + $delay;
            }

            $queue['lease'] = null;
            $queue['lease_until'] = 0;
            self::cache()->put($key, $queue, now()->addHours(2));
        });
    }

    private static function queueKey(?string $token, string $viewer): string
    {
        return 'pinkary:impressions:'.hash('sha256', $token ?? $viewer);
    }

    /** The default store is flushed by NativePHP's startup optimize:clear. */
    private static function cache(): Repository
    {
        return Cache::store('impressions');
    }

    private static function lock(string $key): Lock
    {
        $store = self::cache()->getStore();

        return $store instanceof LockProvider
            ? $store->lock($key.':mutex', 5)
            : throw new LogicException('The impressions cache store does not support locks.');
    }

    /** @return array{pending: list<string>, lease: ?string, lease_until: int, retry_at: int, attempts: int} */
    private static function queue(string $key): array
    {
        $queue = Payload::array(self::cache()->get($key));

        return [
            'pending' => self::pending($queue['pending'] ?? []),
            'lease' => is_string($queue['lease'] ?? null) ? $queue['lease'] : null,
            'lease_until' => Payload::int($queue['lease_until'] ?? 0),
            'retry_at' => Payload::int($queue['retry_at'] ?? 0),
            'attempts' => Payload::int($queue['attempts'] ?? 0),
        ];
    }

    /** @return list<string> */
    private static function pending(mixed $ids): array
    {
        $pending = [];

        foreach (Payload::array($ids) as $id) {
            if (is_string($id) && $id !== '') {
                $pending[] = $id;
            }
        }

        return $pending;
    }
}
