<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class Login extends NativeComponent
{
    public string $email = '';

    public string $password = '';

    public string $errorMessage = '';

    public bool $busy = false;

    public function navTitle(): string
    {
        return 'Log in';
    }

    public function authenticate(): void
    {
        $this->errorMessage = '';

        $validator = Validator::make([
            'email' => $this->email,
            'password' => $this->password,
        ], [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            $this->errorMessage = $validator->errors()->first();

            return;
        }

        $this->busy = true;
        $response = app(PinkaryApi::class)->login($this->email, $this->password);

        if ($response->successful() && is_string($token = $response->json('token'))) {
            app(TokenStorage::class)->store($token);
            $this->replace($this->route('mobile.feed'));

            return;
        }

        $this->busy = false;
        $this->errorMessage = $response->json('errors.email.0')
            ?? $response->json('message')
            ?? 'We could not log you in. Please check your details and try again.';
    }

    public function register(): void
    {
        $this->navigate($this->route('mobile.register'));
    }

    public function render(): View
    {
        return view('native.login');
    }
}
