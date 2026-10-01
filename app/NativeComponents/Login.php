<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use App\Support\AccountStore;
use App\Support\ApiErrors;
use App\Support\CurrentUser;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Native\Mobile\Browser;
use Native\Mobile\Edge\NativeComponent;

final class Login extends NativeComponent
{
    public string $email = '';

    public string $password = '';

    public string $errorMessage = '';

    /**
     * Whether the password was accepted and a second factor is pending.
     */
    public bool $needsTwoFactor = false;

    /**
     * The short-lived challenge the server issued for this sign in attempt.
     */
    public string $twoFactorChallenge = '';

    /**
     * The one-time password or recovery code the user typed.
     */
    public string $twoFactorCode = '';

    /**
     * First server/validation message per input, shown under the field.
     * The summary banner stays as the fallback for older clients.
     *
     * @var array<string, string>
     */
    public array $fieldErrors = [];

    public bool $busy = false;

    /**
     * Client-side only password visibility. Rendered through the
     * existing `secure` input prop — no new APIs involved.
     */
    public bool $showPassword = false;

    /**
     * Add-another-account mode: the app menu sets the session flag before
     * routing here, and the card switches to the web's signed-in-as copy.
     * A stored account is required — the flag alone would leave a guest
     * looking at an "Add Account" form with nobody signed in.
     */
    public bool $addingAccount = false;

    /**
     * Username of the account being kept signed in while another one is
     * added. Read from AccountStore (never a token) for the card.
     */
    public string $activeAccountUsername = '';

    public function mount(): void
    {
        $active = AccountStore::active();

        $this->addingAccount = (bool) session('pinkary_add_account', false) && $active !== null;
        $this->activeAccountUsername = (string) ($active['username'] ?? '');
    }

    public function navTitle(): string
    {
        return $this->addingAccount ? 'Add Account' : 'Log in';
    }

    public function authenticate(): void
    {
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
            $this->acceptToken($token, (array) ($response->json('data') ?? []));

            return;
        }

        // A 2FA account is not a failed login: the password was right and the
        // server is holding the second step for a challenge token.
        if ($response->json('code') === 'two_factor_required' && is_string($challenge = $response->json('challenge'))) {
            $this->busy = false;
            $this->twoFactorChallenge = $challenge;
            $this->needsTwoFactor = true;
            $this->twoFactorCode = '';

            return;
        }

        $this->busy = false;
        $this->applyFieldErrors((array) $response->json('errors', []), [
            'email' => 'email',
            'password' => 'password',
        ], (string) ($response->json('message') ?? 'We could not log you in. Please check your details and try again.'));
    }

    /**
     * Answer a pending two factor challenge.
     *
     * Recovery codes go in the same box: the server accepts either and the
     * field is one line, where a separate recovery screen would be more UI
     * than the case deserves.
     */
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
            $this->acceptToken($token, (array) ($response->json('data') ?? []));

            return;
        }

        $this->busy = false;
        $this->errorMessage = (string) ($response->json('message') ?? 'That code was not accepted. Check it and try again.');
    }

    /**
     * Abandon a pending challenge and go back to the password form.
     */
    public function cancelTwoFactor(): void
    {
        $this->needsTwoFactor = false;
        $this->twoFactorChallenge = '';
        $this->twoFactorCode = '';
        $this->errorMessage = '';
    }

    /**
     * Store a freshly minted token and go to the feed.
     *
     * @param  array<string, mixed>  $userData
     */
    private function acceptToken(string $token, array $userData): void
    {
        $username = (string) ($userData['username'] ?? '');

        // Re-login mints a fresh token: revoke the previous one for
        // this username server-side so dead tokens never pile up.
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

    public function register(): void
    {
        $this->navigate($this->route('mobile.register'));
    }

    public function togglePasswordVisibility(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    /**
     * Password reset lives on the site (Fortify's guest route), so the
     * link hands the web page to the in-app browser like Source code does.
     */
    public function forgotPassword(): void
    {
        $this->openWebPage('/forgot-password');
    }

    public function render(): View
    {
        return view('native.login');
    }

    /**
     * Open a page of the Pinkary site in the in-app browser. The site is
     * the only place these pages exist — this app has no such routes — so
     * the URL is built from the API host and dropped when it is not
     * configured (or not http), never handed over half-formed.
     */
    private function openWebPage(string $path): void
    {
        $url = rtrim((string) config('services.pinkary.url'), '/').$path;

        if (! preg_match('#^https?://#i', $url)) {
            return;
        }

        app(Browser::class)->inApp($url);
    }

    /**
     * Split a Laravel errors bag into per-field messages (shown under
     * each input) plus a summary for the banner: the first message from
     * fields without their own input, else the first message overall.
     *
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
