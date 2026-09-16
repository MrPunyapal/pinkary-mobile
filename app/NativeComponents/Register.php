<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Services\PinkaryApi;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
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

    public bool $busy = false;

    public function navTitle(): string
    {
        return 'Create account';
    }

    public function createAccount(): void
    {
        $this->errorMessage = '';

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

        if ($validator->fails()) {
            $this->errorMessage = $validator->errors()->first();

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
            session(['pinkary_api_token' => $token]);
            $this->replace($this->route('mobile.profile'));

            return;
        }

        $this->busy = false;
        $this->errorMessage = $response->json('errors.email.0')
            ?? $response->json('errors.username.0')
            ?? $response->json('message')
            ?? 'We could not create your account. Please check your details and try again.';
    }

    public function login(): void
    {
        $this->navigate($this->route('mobile.login'));
    }

    public function render(): View
    {
        return view('native.register');
    }
}
