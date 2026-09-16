<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class PinkaryApi
{
    /**
     * Authenticate a user with the Pinkary API.
     */
    public function login(string $email, string $password): Response
    {
        return $this->request()->post('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * Create a Pinkary account and return its access token.
     *
     * @param  array{name: string, username: string, email: string, password: string, password_confirmation: string, terms: bool}  $data
     */
    public function register(array $data): Response
    {
        return $this->request()->post('/api/v1/auth/register', $data);
    }

    /**
     * Load the authenticated user's profile.
     */
    public function profile(string $token): Response
    {
        return $this->request()->withToken($token)->get('/api/v1/profile');
    }

    /**
     * Revoke the current access token.
     */
    public function logout(string $token): Response
    {
        return $this->request()->withToken($token)->post('/api/v1/auth/logout');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.pinkary.url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }
}
