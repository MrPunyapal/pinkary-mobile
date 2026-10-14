<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\ProvidesWebPreviewStyles;
use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use App\Support\AccountStore;
use App\Support\ApiErrors;
use App\Support\CurrentUser;
use App\Support\Payload;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Web\Contracts\HasWebHead;
use Native\Mobile\Platform;

final class Login extends NativeComponent implements HasWebHead
{
    use ProvidesWebPreviewStyles;

    public string $email = '';

    public string $password = '';

    public bool $showPasswordRecovery = false;

    public string $recoveryEmail = '';

    public string $resetToken = '';

    public string $resetEmail = '';

    public string $resetPasswordValue = '';

    public string $resetPasswordConfirmation = '';

    public string $successMessage = '';

    public string $errorMessage = '';

    public bool $needsTwoFactor = false;

    public string $twoFactorChallenge = '';

    public string $twoFactorCode = '';

    public bool $usingRecoveryCode = false;

    /**
     * @var array<string, string>
     */
    public array $fieldErrors = [];

    public bool $busy = false;

    public bool $showPassword = false;

    public bool $addingAccount = false;

    public string $activeAccountUsername = '';

    public function mount(): void
    {
        $active = AccountStore::active();

        $this->addingAccount = (bool) session('pinkary_add_account', false) && $active !== null;
        $this->activeAccountUsername = Payload::string($active['username'] ?? '');
        $this->resetToken = Payload::string($this->param('resetToken', $this->param('token', '')));
        $this->resetEmail = Payload::string($this->param('email', ''));
        $this->showPasswordRecovery = $this->resetToken !== '';
        $this->recoveryEmail = $this->resetEmail;
    }

    public function authenticate(): void
    {
        if ($this->busy) {
            return;
        }

        $this->errorMessage = '';
        $this->fieldErrors = [];

        $validator = Validator::make([
            'email' => $this->email,
            'password' => $this->password,
        ], [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            $this->applyFieldErrors($validator->errors()->toArray(), [
                'email' => 'email',
                'password' => 'password',
            ]);

            return;
        }

        $this->busy = true;
        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->login($this->email, $this->password));

        if ($response === null) {
            $this->busy = false;
            $this->errorMessage = 'We could not connect. Check your internet connection and try again.';

            return;
        }

        if ($response->successful() && is_string($token = $response->json('token'))) {
            $this->acceptToken($token, Payload::stringMap($response->json('data')));

            return;
        }

        if ($response->json('code') === 'two_factor_required' && is_string($challenge = $response->json('challenge'))) {
            $this->busy = false;
            $this->twoFactorChallenge = $challenge;
            $this->needsTwoFactor = true;
            $this->twoFactorCode = '';
            $this->usingRecoveryCode = false;

            return;
        }

        $this->busy = false;
        $this->applyFieldErrors(Payload::stringMap($response->json('errors')), [
            'email' => 'email',
            'password' => 'password',
        ], Payload::string($response->json('message') ?? 'We could not log you in. Please check your details and try again.'));
    }

    public function submitTwoFactor(): void
    {
        $this->errorMessage = '';
        $this->fieldErrors = [];

        $code = mb_trim($this->twoFactorCode);

        if ($code === '') {
            $this->errorMessage = $this->usingRecoveryCode
                ? 'Enter one of your recovery codes.'
                : 'Enter the code from your authenticator app.';

            return;
        }

        $this->busy = true;
        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->twoFactorChallenge($this->twoFactorChallenge, $code, $this->usingRecoveryCode));

        if ($response === null) {
            $this->busy = false;
            $this->errorMessage = 'We could not connect. Check your internet connection and try again.';

            return;
        }

        if ($response->successful() && is_string($token = $response->json('token'))) {
            $this->acceptToken($token, Payload::stringMap($response->json('data')));

            return;
        }

        $this->busy = false;
        $this->errorMessage = Payload::string($response->json('message') ?? 'That code was not accepted. Check it and try again.');
    }

    public function toggleTwoFactorMethod(): void
    {
        $this->usingRecoveryCode = ! $this->usingRecoveryCode;
        $this->twoFactorCode = '';
        $this->errorMessage = '';
    }

    public function cancelTwoFactor(): void
    {
        $this->needsTwoFactor = false;
        $this->twoFactorChallenge = '';
        $this->twoFactorCode = '';
        $this->usingRecoveryCode = false;
        $this->errorMessage = '';
    }

    public function register(): void
    {
        $this->navigate($this->route('mobile.register'));
    }

    public function onBackPressed(): void
    {
        if ($this->needsTwoFactor) {
            $this->cancelTwoFactor();

            return;
        }

        if ($this->showPasswordRecovery) {
            $this->cancelPasswordRecovery();

            return;
        }

        session()->forget('pinkary_add_account');

        if ($this->nativeRouter?->isRootScreen() && Platform::current() !== null) {
            $this->replace($this->route('mobile.feed'));

            return;
        }

        $this->back();
    }

    public function togglePasswordVisibility(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    public function forgotPassword(): void
    {
        $this->showPasswordRecovery = true;
        $this->recoveryEmail = $this->email;
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function cancelPasswordRecovery(): void
    {
        $this->showPasswordRecovery = false;
        $this->resetToken = '';
        $this->resetPasswordValue = '';
        $this->resetPasswordConfirmation = '';
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function requestPasswordRecovery(): void
    {
        if ($this->busy) {
            return;
        }

        $this->errorMessage = '';
        $this->successMessage = '';
        $validator = Validator::make(['email' => $this->recoveryEmail], [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            $this->errorMessage = $validator->errors()->first();

            return;
        }

        $this->busy = true;
        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->requestPasswordRecovery($this->recoveryEmail));
        $this->busy = false;

        if ($response === null) {
            $this->errorMessage = 'We could not connect. Check your internet connection and try again.';

            return;
        }

        if (! $response->successful()) {
            $this->errorMessage = ApiErrors::first($response, 'The reset email could not be requested. Please try again.');

            return;
        }

        $this->successMessage = Payload::string(
            $response->json('message'),
            'If an account exists for that email, a password reset link has been sent.'
        );
        $this->resetEmail = $this->recoveryEmail;
    }

    public function resetPassword(): void
    {
        if ($this->busy) {
            return;
        }

        $this->errorMessage = '';
        $this->successMessage = '';
        $validator = Validator::make([
            'token' => $this->resetToken,
            'email' => $this->resetEmail,
            'password' => $this->resetPasswordValue,
            'password_confirmation' => $this->resetPasswordConfirmation,
        ], [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            $this->errorMessage = $validator->errors()->first();

            return;
        }

        $this->busy = true;
        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->resetPassword([
            'token' => $this->resetToken,
            'email' => $this->resetEmail,
            'password' => $this->resetPasswordValue,
            'password_confirmation' => $this->resetPasswordConfirmation,
        ]));
        $this->busy = false;

        if ($response === null) {
            $this->errorMessage = 'We could not connect. Check your internet connection and try again.';

            return;
        }

        if (! $response->successful()) {
            $this->errorMessage = ApiErrors::first($response, 'The reset link is invalid or has expired. Request a new one and try again.');

            return;
        }

        $this->resetToken = '';
        $this->resetPasswordValue = '';
        $this->resetPasswordConfirmation = '';
        $this->successMessage = 'Your password has been reset. You can now sign in.';
    }

    public function render(): View
    {
        return view('native.login');
    }

    /**
     * @param  array<string, mixed>  $userData
     */
    private function acceptToken(string $token, array $userData): void
    {
        $username = Payload::string($userData['username'] ?? '');

        $previous = AccountStore::tokenForProfile($username, $userData);

        if (is_string($previous) && $previous !== '' && $previous !== $token) {
            app(PinkaryApi::class)->attempt(fn () => app(PinkaryApi::class)->logout($previous));
        }

        app(TokenStorage::class)->store($token, $username, $userData);

        if ($username !== '') {
            CurrentUser::store($userData);
        }

        session()->forget('pinkary_add_account');
        $this->busy = false;
        $this->replace($this->route('mobile.feed'));
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, string>  $fields  server key => fieldErrors key
     */
    private function applyFieldErrors(array $errors, array $fields, ?string $fallback = null): void
    {
        $split = ApiErrors::split($errors, $fields, $fallback);
        $this->fieldErrors = $split['fieldErrors'];
        $this->errorMessage = $split['message'];
    }
}
