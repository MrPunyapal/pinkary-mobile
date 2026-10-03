<?php

declare(strict_types=1);

namespace App\NativeComponents\Concerns;

use App\Services\PinkaryApi;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Native\Mobile\Edge\NativeComponent;
use Throwable;

/** @mixin NativeComponent */
trait UsesCaptcha
{
    public bool $captchaRequired = false;

    public bool $captchaLoading = false;

    public bool $captchaCompleted = false;

    public string $captchaUrl = '';

    public string $captchaError = '';

    public string $captchaState = '';

    private string $captchaAction = '';

    private string $captchaWidgetPath = '';

    private string $captchaReturnPath = '';

    private string $captchaToken = '';

    private int $captchaExpiresAt = 0;

    private ?string $captchaAccountToken = null;

    private function loadCaptcha(string $action, ?string $token = null): void
    {
        if ($this->captchaLoading) {
            return;
        }

        $this->captchaAction = $action;
        $this->captchaAccountToken = $token;
        $this->captchaLoading = true;
        $this->captchaError = '';

        $this->async(static function () use ($action, $token): array {
            $response = app(PinkaryApi::class)->captcha($action, $token);

            return ['success' => $response->successful(), 'data' => $response->json('data', [])];
        })->finished(function (array $result): void {
            $this->captchaLoading = false;
            $data = $result['data'];

            if (! $result['success'] || ! is_array($data) || ! is_bool($data['required'] ?? null)
                || ! $this->validCaptchaPath($data['widget_path'] ?? null) || ! $this->validCaptchaPath($data['return_path'] ?? null)) {
                $this->captchaError = 'Could not load the security check. Please retry.';

                return;
            }

            $this->captchaRequired = $data['required'];
            $this->captchaWidgetPath = $data['widget_path'];
            $this->captchaReturnPath = $data['return_path'];
            $this->resetCaptcha();
        })->failed(function (Throwable $exception): void {
            $this->captchaLoading = false;
            $this->captchaError = 'Could not load the security check. Please retry.';
        });
    }

    public function retryCaptcha(): void
    {
        if ($this->captchaLoading) {
            return;
        }

        $this->captchaError = '';

        if ($this->captchaWidgetPath === '') {
            $this->loadCaptcha($this->captchaAction, $this->captchaAccountToken);

            return;
        }

        $this->resetCaptcha();
    }

    public function captchaNavigated(string $url): void
    {
        $base = parse_url((string) config('services.pinkary.url'));
        $callback = parse_url($url);

        if (! $this->captchaRequired || $this->captchaState === '' || ! is_array($base) || ! is_array($callback)
            || ($callback['scheme'] ?? '') !== ($base['scheme'] ?? '')
            || ($callback['host'] ?? '') !== ($base['host'] ?? '')
            || ($callback['port'] ?? null) !== ($base['port'] ?? null)
            || isset($callback['user']) || isset($callback['pass'])
            || ($callback['path'] ?? '') !== $this->captchaReturnPath) {
            return;
        }

        parse_str($callback['query'] ?? '', $query);

        if (($query['state'] ?? null) !== $this->captchaState) {
            return;
        }

        $status = $query['status'] ?? '';

        if ($status === 'success') {
            $token = rawurldecode($callback['fragment'] ?? '');

            if ($token === '' || strlen($token) > 2048 || preg_match('/[\x00-\x20\x7f]/', $token)) {
                return;
            }

            $this->captchaToken = $token;
            $this->captchaExpiresAt = now()->timestamp + 240;
            $this->captchaCompleted = true;
            $this->captchaUrl = '';
            $this->captchaError = '';
        } elseif (in_array($status, ['expired', 'error', 'timeout'], true)) {
            $this->resetCaptcha();
            $this->captchaError = 'The security check expired or failed. Please try again.';
        }
    }

    private function captchaReadyForSubmission(): bool
    {
        if (! $this->captchaRequired) {
            return true;
        }

        if ($this->captchaToken !== '' && $this->captchaExpiresAt > now()->timestamp) {
            return true;
        }

        if ($this->captchaCompleted) {
            $this->resetCaptcha();
        }

        $this->captchaError = 'Please complete the security check before submitting.';

        return false;
    }

    /** @return array{cf-turnstile-response?: string, captcha_state?: string} */
    private function captchaPayload(): array
    {
        return $this->captchaToken !== '' && $this->captchaExpiresAt > now()->timestamp
            ? ['cf-turnstile-response' => $this->captchaToken, 'captcha_state' => $this->captchaState]
            : [];
    }

    private function captchaAttempted(?Response $response): void
    {
        if ($this->captchaToken !== '') {
            $this->resetCaptcha();
        }

        $errors = $response?->json('errors', []);

        if (is_array($errors) && (isset($errors['cf-turnstile-response']) || isset($errors['captcha_state']))) {
            $this->captchaRequired = true;
            $this->captchaError = 'Please complete a new security check and try again.';

            if ($this->captchaWidgetPath === '') {
                $this->loadCaptcha($this->captchaAction, $this->captchaAccountToken);
            }
        }
    }

    private function resetCaptcha(): void
    {
        $this->captchaToken = '';
        $this->captchaExpiresAt = 0;
        $this->captchaCompleted = false;
        $this->captchaState = (string) Str::uuid();
        $this->captchaUrl = $this->captchaRequired && $this->captchaWidgetPath !== ''
            ? rtrim((string) config('services.pinkary.url'), '/').$this->captchaWidgetPath.'?'.http_build_query([
                'action' => $this->captchaAction, 'state' => $this->captchaState,
            ]) : '';
    }

    private function validCaptchaPath(mixed $path): bool
    {
        return is_string($path) && preg_match('~^/(?!/)[A-Za-z0-9/_-]+$~D', $path) === 1;
    }
}
