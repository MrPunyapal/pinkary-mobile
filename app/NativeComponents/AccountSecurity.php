<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\ProvidesWebPreviewStyles;
use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Support\AccountStore;
use App\Support\ApiErrors;
use App\Support\Payload;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Native\Mobile\Browser;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Web\Contracts\HasWebHead;

final class AccountSecurity extends NativeComponent implements HasWebHead
{
    use ProvidesWebPreviewStyles;
    use UsesAppMenu;

    public bool $loading = true;

    public bool $busy = false;

    public string $errorMessage = '';

    public string $successMessage = '';

    public string $email = '';

    public bool $emailVerified = false;

    public ?string $emailVerifiedAt = null;

    public string $verificationMessage = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $passwordConfirmation = '';

    public bool $twoFactorEnabled = false;

    public bool $twoFactorPending = false;

    public bool $twoFactorStatusLoaded = false;

    public bool $githubConnected = false;

    public bool $githubEligible = false;

    public bool $profileVerified = false;

    public string $githubUsername = '';

    public string $githubMessage = '';

    public string $githubHandoffId = '';

    public bool $githubBusy = false;

    public bool $confirmGithubDisconnect = false;

    public string $twoFactorPassword = '';

    public string $twoFactorCode = '';

    public string $twoFactorSecret = '';

    public string $twoFactorQrCode = '';

    /** @var list<string> */
    public array $recoveryCodes = [];

    public string $recoveryCodesPassword = '';

    public string $disableTwoFactorPassword = '';

    public string $deletePassword = '';

    public string $deleteConfirmation = '';

    public bool $showPassword = false;

    public function hidesDrawer(): bool
    {
        return true;
    }

    public function mount(): void
    {
        $token = $this->requireToken(replaceScreen: true);

        if ($token !== null) {
            $this->loadStatus($token);
        }
    }

    public function retry(): void
    {
        $token = $this->requireToken();

        if ($token !== null) {
            $this->loadStatus($token);
        }
    }

    public function refreshStatus(): void
    {
        $token = $this->requireToken();

        if ($token !== null) {
            $this->loadStatus($token);
        }
    }

    public function sendVerificationEmail(): void
    {
        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->errorMessage = '';
        $this->verificationMessage = '';
        $this->busy = true;

        $api = app(PinkaryApi::class);
        $response = $this->authenticatedCall(
            fn () => $api->sendEmailVerification($token),
            'The verification email could not be sent. Please try again.'
        );

        $this->busy = false;

        if ($response === null) {
            return;
        }

        $this->emailVerified = (bool) $response->json('verified', $this->emailVerified);
        $this->verificationMessage = Payload::string(
            $response->json('message'),
            'If this account still needs verification, a link has been sent.'
        );
    }

    public function changePassword(): void
    {
        if ($this->busy) {
            return;
        }

        $this->errorMessage = '';
        $validator = Validator::make([
            'current_password' => $this->currentPassword,
            'password' => $this->newPassword,
            'password_confirmation' => $this->passwordConfirmation,
        ], [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            $this->errorMessage = $validator->errors()->first();

            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->busy = true;
        $api = app(PinkaryApi::class);
        $response = $this->authenticatedCall(
            fn () => $api->changePassword($token, [
                'current_password' => $this->currentPassword,
                'password' => $this->newPassword,
                'password_confirmation' => $this->passwordConfirmation,
            ]),
            'Your password could not be changed. Please try again.'
        );
        $this->busy = false;

        if ($response === null) {
            return;
        }

        $this->currentPassword = '';
        $this->newPassword = '';
        $this->passwordConfirmation = '';
        $this->successMessage = 'Your password has been changed.';
    }

    public function startTwoFactor(): void
    {
        if ($this->busy) {
            return;
        }

        if (mb_trim($this->twoFactorPassword) === '') {
            $this->errorMessage = 'Enter your current password to start two-factor setup.';

            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->errorMessage = '';
        $this->busy = true;
        $api = app(PinkaryApi::class);
        $response = $this->authenticatedCall(
            fn () => $api->startTwoFactor($token, ['current_password' => $this->twoFactorPassword]),
            'Two-factor setup could not be started. Please try again.'
        );
        $this->busy = false;
        $this->twoFactorPassword = '';

        if ($response === null) {
            return;
        }

        $this->twoFactorSecret = Payload::string($response->json('secret'));
        $svg = Payload::string($response->json('qr_code_svg'));
        $this->twoFactorQrCode = $svg === '' ? '' : 'data:image/svg+xml;base64,'.base64_encode($svg);
        $this->twoFactorPending = (bool) $response->json('pending_confirmation', true);
        $this->successMessage = 'Scan the code, then confirm with a code from your authenticator app.';
    }

    public function confirmTwoFactor(): void
    {
        if ($this->busy) {
            return;
        }

        if (mb_trim($this->twoFactorPassword) === '' || mb_trim($this->twoFactorCode) === '') {
            $this->errorMessage = 'Enter your current password and the six-digit authenticator code.';

            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->errorMessage = '';
        $this->busy = true;
        $api = app(PinkaryApi::class);
        $response = $this->authenticatedCall(
            fn () => $api->confirmTwoFactor($token, [
                'current_password' => $this->twoFactorPassword,
                'code' => mb_trim($this->twoFactorCode),
            ]),
            'Two-factor authentication could not be confirmed. Check the code and try again.'
        );
        $this->busy = false;
        $this->twoFactorPassword = '';
        $this->twoFactorCode = '';

        if ($response === null) {
            return;
        }

        $this->twoFactorEnabled = (bool) $response->json('enabled', true);
        $this->twoFactorPending = false;
        $this->twoFactorSecret = '';
        $this->twoFactorQrCode = '';
        $this->recoveryCodes = array_values(array_filter(
            Payload::array($response->json('recovery_codes')),
            is_string(...)
        ));
        $this->successMessage = 'Two-factor authentication is enabled. Save your recovery codes somewhere safe.';
    }

    public function showRecoveryCodes(): void
    {
        $this->loadRecoveryCodes(regenerate: false);
    }

    public function regenerateRecoveryCodes(): void
    {
        $this->loadRecoveryCodes(regenerate: true);
    }

    public function hideRecoveryCodes(): void
    {
        $this->recoveryCodes = [];
        $this->recoveryCodesPassword = '';
    }

    public function disableTwoFactor(): void
    {
        if ($this->busy) {
            return;
        }

        if (mb_trim($this->disableTwoFactorPassword) === '') {
            $this->errorMessage = 'Enter your current password to turn off two-factor authentication.';

            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->errorMessage = '';
        $this->busy = true;
        $api = app(PinkaryApi::class);
        $response = $this->authenticatedCall(
            fn () => $api->disableTwoFactor($token, ['current_password' => $this->disableTwoFactorPassword]),
            'Two-factor authentication could not be disabled. Please try again.'
        );
        $this->busy = false;
        $this->disableTwoFactorPassword = '';

        if ($response === null) {
            return;
        }

        $this->twoFactorEnabled = false;
        $this->twoFactorPending = false;
        $this->twoFactorSecret = '';
        $this->twoFactorQrCode = '';
        $this->hideRecoveryCodes();
        $this->successMessage = 'Two-factor authentication has been turned off.';
    }

    public function refreshGithubStatus(): void
    {
        $token = $this->requireToken();

        if ($token !== null) {
            $this->loadGithubStatus($token);
        }
    }

    public function connectGithub(): void
    {
        if ($this->githubBusy || ! $this->githubEligible || $this->githubConnected) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->githubBusy = true;
        $this->githubMessage = '';
        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->startGithubHandoff($token));
        $this->githubBusy = false;

        if ($response === null) {
            $this->githubMessage = 'GitHub connection could not be started. Check your internet connection and try again.';

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->githubMessage = ApiErrors::first($response, 'GitHub connection could not be started. Please try again.');

            return;
        }

        $handoffId = Payload::string($response->json('id'));
        $browserUrl = Payload::string($response->json('browser_url'));

        if ($handoffId === '' || ! $this->isTrustedPinkaryUrl($browserUrl)) {
            $this->githubMessage = 'Pinkary returned an invalid GitHub authorization link.';

            return;
        }

        $this->githubHandoffId = $handoffId;
        $this->githubMessage = 'Finish the GitHub authorization in the browser, then check the connection here.';
        app(Browser::class)->inApp($browserUrl);
    }

    public function pollGithubHandoff(): void
    {
        if ($this->githubBusy || $this->githubHandoffId === '') {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->githubBusy = true;
        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->githubHandoffStatus($token, $this->githubHandoffId));
        $this->githubBusy = false;

        if ($response === null) {
            $this->githubMessage = 'GitHub connection status could not be checked. Please try again.';

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->githubMessage = ApiErrors::first($response, 'GitHub connection status could not be checked. Please try again.');

            return;
        }

        $status = Payload::string($response->json('status'));

        if ($status === 'completed') {
            $this->githubConnected = true;
            $this->githubUsername = Payload::string($response->json('github_username'));
            $this->githubEligible = false;
            $this->githubHandoffId = '';
            $this->loadGithubStatus($token);
            $this->githubMessage = 'GitHub is connected.';

            return;
        }

        if (in_array($status, ['failed', 'expired'], true)) {
            $this->githubHandoffId = '';
            $this->githubMessage = $status === 'expired'
                ? 'This GitHub authorization link expired. Start again to get a new one.'
                : 'GitHub authorization did not complete. You can try again.';

            return;
        }

        $this->githubMessage = 'Waiting for GitHub authorization to finish.';
    }

    public function askDisconnectGithub(): void
    {
        $this->confirmGithubDisconnect = true;
    }

    public function cancelDisconnectGithub(): void
    {
        $this->confirmGithubDisconnect = false;
    }

    public function disconnectGithub(): void
    {
        if ($this->githubBusy || ! $this->confirmGithubDisconnect) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->githubBusy = true;
        $this->githubMessage = '';
        $api = app(PinkaryApi::class);
        $response = $this->authenticatedCall(
            fn () => $api->disconnectGithub($token),
            'GitHub could not be disconnected. Please try again.'
        );
        $this->githubBusy = false;
        $this->confirmGithubDisconnect = false;

        if ($response === null) {
            $this->githubMessage = $this->errorMessage;
            $this->errorMessage = '';

            return;
        }

        $this->githubConnected = false;
        $this->githubUsername = '';
        $this->githubHandoffId = '';
        $this->loadGithubStatus($token);
        $this->githubMessage = 'GitHub has been disconnected.';
    }

    public function deleteAccount(): void
    {
        if ($this->busy) {
            return;
        }

        if (mb_trim($this->deletePassword) === '' || mb_strtoupper(mb_trim($this->deleteConfirmation)) !== 'DELETE') {
            $this->errorMessage = 'Enter your password and type DELETE to confirm account deletion.';

            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->errorMessage = '';
        $this->busy = true;
        $api = app(PinkaryApi::class);
        $response = $this->authenticatedCall(
            fn () => $api->deleteAccount($token, $this->deletePassword),
            'Your account could not be deleted. Please try again.'
        );
        $this->busy = false;
        $this->deletePassword = '';
        $this->deleteConfirmation = '';

        if ($response === null) {
            return;
        }

        AccountStore::removeActive();
        $this->afterActiveAccountRemoved();
    }

    public function togglePasswordVisibility(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    public function onBackPressed(): void
    {
        $this->clearSensitiveState();
        $this->back();
    }

    public function render(): View
    {
        return view('native.account-security');
    }

    private function loadStatus(string $token): void
    {
        $this->loading = true;
        $this->errorMessage = '';
        $this->twoFactorStatusLoaded = false;
        $api = app(PinkaryApi::class);
        $emailResponse = $this->authenticatedCall(
            fn () => $api->emailVerificationStatus($token),
            'Email verification status could not be loaded.'
        );

        if ($emailResponse === null) {
            $this->loading = false;

            return;
        }

        $this->email = Payload::string($emailResponse->json('email'));
        $this->emailVerified = (bool) $emailResponse->json('verified', false);
        $verifiedAt = $emailResponse->json('verified_at');
        $this->emailVerifiedAt = is_string($verifiedAt) ? $verifiedAt : null;

        $twoFactorResponse = $this->authenticatedCall(
            fn () => $api->twoFactorStatus($token),
            'Two-factor status could not be loaded.'
        );
        $this->loading = false;

        if ($twoFactorResponse === null) {
            return;
        }

        $this->twoFactorEnabled = (bool) $twoFactorResponse->json('enabled', false);
        $this->twoFactorPending = (bool) $twoFactorResponse->json('pending_confirmation', false);
        $this->twoFactorStatusLoaded = true;
        $this->loadGithubStatus($token);
    }

    private function loadGithubStatus(string $token): void
    {
        $this->githubMessage = '';
        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->githubConnection($token));

        if ($response === null) {
            $this->githubMessage = 'GitHub status could not be loaded. Please try again.';

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->githubMessage = ApiErrors::first($response, 'GitHub status could not be loaded. Please try again.');

            return;
        }

        $this->githubConnected = (bool) $response->json('connected', false);
        $this->githubEligible = (bool) $response->json('eligible', false);
        $this->githubUsername = Payload::string($response->json('github_username'));
        $this->profileVerified = (bool) $response->json('verification.profile', false);

        if (! $this->githubConnected && ! $this->githubEligible) {
            $this->githubMessage = $this->githubEligibilityMessage(
                Payload::string($response->json('eligibility_reason'))
            );
        }
    }

    private function githubEligibilityMessage(string $reason): string
    {
        return match ($reason) {
            'provider_unavailable' => 'GitHub connection is unavailable right now.',
            'already_connected' => 'A GitHub account is already connected.',
            default => 'GitHub connection is not available for this account right now.',
        };
    }

    private function isTrustedPinkaryUrl(string $url): bool
    {
        $base = parse_url(Payload::string(config('services.pinkary.url')));
        $candidate = parse_url($url);

        if (! is_array($base) || ! is_array($candidate)) {
            return false;
        }

        $baseScheme = mb_strtolower(Payload::string($base['scheme'] ?? ''));
        $candidateScheme = mb_strtolower(Payload::string($candidate['scheme'] ?? ''));
        $defaultPort = $candidateScheme === 'https' ? 443 : 80;

        return in_array($candidateScheme, ['https', 'http'], true)
            && $candidateScheme === $baseScheme
            && isset($base['host'], $candidate['host'])
            && mb_strtolower(Payload::string($base['host'])) === mb_strtolower(Payload::string($candidate['host']))
            && (Payload::int($base['port'] ?? null, $defaultPort) === Payload::int($candidate['port'] ?? null, $defaultPort))
            && ! isset($candidate['user'])
            && ! isset($candidate['pass'])
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function loadRecoveryCodes(bool $regenerate): void
    {
        if ($this->busy) {
            return;
        }

        if (mb_trim($this->recoveryCodesPassword) === '') {
            $this->errorMessage = 'Enter your current password to access recovery codes.';

            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->errorMessage = '';
        $this->busy = true;
        $api = app(PinkaryApi::class);
        $payload = ['current_password' => $this->recoveryCodesPassword];
        $response = $this->authenticatedCall(
            fn () => $regenerate
                ? $api->regenerateTwoFactorRecoveryCodes($token, $payload)
                : $api->showTwoFactorRecoveryCodes($token, $payload),
            'Recovery codes could not be loaded. Please try again.'
        );
        $this->busy = false;
        $this->recoveryCodesPassword = '';

        if ($response === null) {
            return;
        }

        $this->recoveryCodes = array_values(array_filter(
            Payload::array($response->json('recovery_codes')),
            is_string(...)
        ));
        $this->successMessage = $regenerate
            ? 'Your recovery codes were replaced. Save these new codes somewhere safe.'
            : 'Keep these recovery codes somewhere safe. Each code can only be used once.';
    }

    private function authenticatedCall(callable $request, string $fallback): ?Response
    {
        $api = app(PinkaryApi::class);
        $response = $api->attempt($request);

        if ($response === null) {
            $this->errorMessage = $fallback;

            return null;
        }

        if ($this->handleUnauthorized($response)) {
            return null;
        }

        if (! $response->successful()) {
            $this->errorMessage = ApiErrors::first($response, $fallback);

            return null;
        }

        return $response;
    }

    private function clearSensitiveState(): void
    {
        $this->currentPassword = '';
        $this->newPassword = '';
        $this->passwordConfirmation = '';
        $this->twoFactorPassword = '';
        $this->twoFactorCode = '';
        $this->twoFactorSecret = '';
        $this->twoFactorQrCode = '';
        $this->recoveryCodes = [];
        $this->recoveryCodesPassword = '';
        $this->disableTwoFactorPassword = '';
        $this->deletePassword = '';
        $this->deleteConfirmation = '';
    }
}
