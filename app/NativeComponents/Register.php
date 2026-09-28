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

final class Register extends NativeComponent
{
    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public bool $terms = false;

    public string $errorMessage = '';

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

    public function navTitle(): string
    {
        return 'Create account';
    }

    public function createAccount(): void
    {
        $this->errorMessage = '';
        $this->fieldErrors = [];

        $validator = Validator::make([
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->passwordConfirmation,
            'terms' => $this->terms,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:4', 'max:50'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'terms' => ['accepted'],
        ]);

        // Server keys map to inputs; the confirmation error reads best
        // under the confirmation field, terms has no input so it lands
        // in the summary banner.
        $fields = [
            'name' => 'name',
            'username' => 'username',
            'email' => 'email',
            'password' => 'password',
            'password_confirmation' => 'passwordConfirmation',
        ];

        if ($validator->fails()) {
            $this->applyFieldErrors($validator->errors()->toArray(), $fields);

            return;
        }

        $this->busy = true;
        $response = app(PinkaryApi::class)->register([
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->passwordConfirmation,
            'terms' => $this->terms,
        ]);

        if ($response->successful() && is_string($token = $response->json('token'))) {
            $userData = $response->json('data') ?? $response->json('user');
            $userData = is_array($userData) ? $userData : [];
            $username = (string) ($userData['username'] ?? $this->username);

            // Re-registration mints a fresh token: revoke the previous
            // one for this username server-side so dead tokens never
            // pile up.
            $previous = $username !== '' ? AccountStore::tokenForUsername($username) : null;

            if (is_string($previous) && $previous !== '' && $previous !== $token) {
                app(PinkaryApi::class)->attempt(fn () => app(PinkaryApi::class)->logout($previous));
            }

            app(TokenStorage::class)->store($token, $username, $userData !== [] ? $userData : [
                'name' => $this->name,
                'username' => $username,
            ]);

            if ($username !== '') {
                CurrentUser::store($userData !== [] ? $userData : [
                    'name' => $this->name,
                    'username' => $username,
                ]);
            }

            session()->forget('pinkary_add_account');
            $this->replace($this->route('mobile.feed'));

            return;
        }

        $this->busy = false;
        $this->applyFieldErrors((array) $response->json('errors', []), $fields, (string) ($response->json('message') ?? 'We could not create your account. Please check your details and try again.'));
    }

    public function login(): void
    {
        $this->navigate($this->route('mobile.login'));
    }

    public function togglePasswordVisibility(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    /**
     * The consent sentence links to the site's legal pages, which this
     * app does not host — hand them to the in-app browser like the app
     * menu's Source code row does.
     */
    public function openTerms(): void
    {
        $this->openWebPage('/terms');
    }

    public function openPrivacy(): void
    {
        $this->openWebPage('/privacy');
    }

    public function render(): View
    {
        return view('native.register');
    }

    /**
     * Open a page of the Pinkary site in the in-app browser, dropped when
     * the API host is missing or not http so nothing half-formed is ever
     * handed to the browser.
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
