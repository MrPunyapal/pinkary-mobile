<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use App\Support\AccountStore;
use App\Support\ApiErrors;
use App\Support\CurrentUser;
use App\Support\Payload;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Native\Mobile\Browser;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Platform;

final class Login extends NativeComponent
{
    public string $email = '';

    public string $password = '';

    public string $errorMessage = '';

    public bool $needsTwoFactor = false;

    public string $twoFactorChallenge = '';

    public string $twoFactorCode = '';

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
    }

    public function navTitle(): string
    {
        return $this->addingAccount ? 'Add Account' : 'Log in';
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
        $response = app(PinkaryApi::class)->login($this->email, $this->password);

        if ($response->successful() && is_string($token = $response->json('token'))) {
            $this->acceptToken($token, Payload::stringMap($response->json('data')));

            return;
        }

        if ($response->json('code') === 'two_factor_required' && is_string($challenge = $response->json('challenge'))) {
            $this->busy = false;
            $this->twoFactorChallenge = $challenge;
            $this->needsTwoFactor = true;
            $this->twoFactorCode = '';

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
            $this->errorMessage = 'Enter the code from your authenticator app.';

            return;
        }

        $this->busy = true;
        $response = app(PinkaryApi::class)->twoFactorChallenge($this->twoFactorChallenge, $code);

        if ($response->successful() && is_string($token = $response->json('token'))) {
            $this->acceptToken($token, Payload::stringMap($response->json('data')));

            return;
        }

        $this->busy = false;
        $this->errorMessage = Payload::string($response->json('message') ?? 'That code was not accepted. Check it and try again.');
    }

    public function cancelTwoFactor(): void
    {
        $this->needsTwoFactor = false;
        $this->twoFactorChallenge = '';
        $this->twoFactorCode = '';
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
        $this->openWebPage('/forgot-password');
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

        $previous = $username !== '' ? AccountStore::tokenForUsername($username) : null;

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

    private function openWebPage(string $path): void
    {
        $url = mb_rtrim(Payload::string(config('services.pinkary.url')), '/').$path;

        if (! preg_match('#^https?://#i', $url)) {
            return;
        }

        app(Browser::class)->inApp($url);
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
