<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\UsesCaptcha;
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

final class Register extends NativeComponent
{
    use UsesCaptcha;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public bool $terms = false;

    public string $errorMessage = '';

    /**
     * @var array<string, string>
     */
    public array $fieldErrors = [];

    public bool $busy = false;

    public bool $showPassword = false;

    public function mount(): void
    {
        $this->loadCaptcha('register');
    }

    public function createAccount(): void
    {
        if ($this->busy) {
            return;
        }

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

        if (! $this->captchaReadyForSubmission()) {
            return;
        }

        $this->busy = true;
        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->register([
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->passwordConfirmation,
            'terms' => $this->terms,
            ...$this->captchaPayload(),
        ]));
        $this->captchaAttempted($response);

        if ($response === null) {
            $this->busy = false;
            $this->errorMessage = 'We could not reach Pinkary. Please try again.';

            return;
        }

        if ($response->successful() && is_string($token = $response->json('token'))) {
            $userData = Payload::stringMap($response->json('data') ?? $response->json('user'));
            $username = Payload::string($userData['username'] ?? $this->username);

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
        $this->applyFieldErrors(Payload::stringMap($response->json('errors')), $fields, Payload::string($response->json('message') ?? 'We could not create your account. Please check your details and try again.'));
    }

    public function login(): void
    {
        $this->navigate($this->route('mobile.login'));
    }

    public function onBackPressed(): void
    {
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
