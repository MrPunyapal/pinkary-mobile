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
use Native\Mobile\Edge\NativeComponent;

final class Login extends NativeComponent
{
    public string $email = '';

    public string $password = '';

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
        return 'Log in';
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
            $userData = $response->json('data') ?? $response->json('user');
            $userData = is_array($userData) ? $userData : [];
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
            $this->replace($this->route('mobile.feed'));

            return;
        }

        $this->busy = false;
        $this->applyFieldErrors((array) $response->json('errors', []), [
            'email' => 'email',
            'password' => 'password',
        ], (string) ($response->json('message') ?? 'We could not log you in. Please check your details and try again.'));
    }

    public function register(): void
    {
        $this->navigate($this->route('mobile.register'));
    }

    public function togglePasswordVisibility(): void
    {
        $this->showPassword = ! $this->showPassword;
    }

    public function render(): View
    {
        return view('native.login');
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
