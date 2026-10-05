<?php

declare(strict_types=1);

use App\Services\TokenStorage;
use App\Support\AccountStore;
use App\Support\CurrentUser;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Native\Mobile\Testing\FakeBridge;

beforeEach(function (): void {
    FakeBridge::enable();
    AccountStore::clear();
});

afterEach(function (): void {
    AccountStore::clear();
    FakeBridge::disable();
});

it('stores the token so it can be read back', function (): void {
    app(TokenStorage::class)->store('test-token', 'ada', ['username' => 'ada']);

    expect(app(TokenStorage::class)->get())->toBe('test-token')
        ->and(AccountStore::activeToken())->toBe('test-token');
});

it('clears the token', function (): void {
    app(TokenStorage::class)->store('test-token', 'ada', ['username' => 'ada']);
    app(TokenStorage::class)->clear();

    expect(app(TokenStorage::class)->get())->toBeNull()
        ->and(AccountStore::activeToken())->toBeNull();
});

it('returns null when nothing is stored', function (): void {
    AccountStore::clear();

    expect(app(TokenStorage::class)->get())->toBeNull();
});

it('restores the selected account and profile after the app session is lost', function (): void {
    $ada = AccountStore::add('ada', 'token-a', ['username' => 'ada', 'name' => 'Ada Lovelace']);
    AccountStore::add('grace', 'token-b', ['username' => 'grace', 'name' => 'Grace Hopper']);
    AccountStore::activate($ada);
    CurrentUser::store(['username' => 'ada', 'name' => 'Ada Byron', 'avatar' => 'https://example.com/ada.png']);

    session()->flush();
    app()->forgetInstance(TokenStorage::class);
    $this->artisan('cache:clear')->assertSuccessful();

    expect(app(TokenStorage::class)->get())->toBe('token-a')
        ->and(AccountStore::activeId())->toBe($ada)
        ->and(AccountStore::count())->toBe(2)
        ->and(CurrentUser::get())->toMatchArray([
            'username' => 'ada',
            'name' => 'Ada Byron',
            'avatar' => 'https://example.com/ada.png',
        ])
        ->and(CurrentUser::get())->not->toHaveKey('token');
});

it('stores account credentials only in an encrypted private file', function (): void {
    app(TokenStorage::class)->store('private-token', 'ada', ['username' => 'ada']);

    $stored = Storage::disk('local')->get('pinkary-accounts.enc');

    expect($stored)->not->toContain('private-token', '"username":"ada"')
        ->and(session('pinkary_accounts'))->toBeNull()
        ->and(json_decode(Crypt::decryptString($stored), true)['accounts'][0]['token'])->toBe('private-token');
});

it('does not restore a logged-out account after reopening the app', function (): void {
    app(TokenStorage::class)->store('private-token', 'ada', ['username' => 'ada']);
    app(TokenStorage::class)->clear();

    session()->flush();

    expect(app(TokenStorage::class)->get())->toBeNull()
        ->and(AccountStore::count())->toBe(0)
        ->and(Crypt::decryptString(Storage::disk('local')->get('pinkary-accounts.enc')))->not->toContain('private-token');
});

it('requires a new login when saved credentials cannot be decrypted', function (): void {
    app(TokenStorage::class)->store('private-token', 'ada', ['username' => 'ada']);
    $differentDevice = new Encrypter(Encrypter::generateKey('AES-256-CBC'), 'AES-256-CBC');
    Crypt::swap($differentDevice);
    session()->flush();

    expect(app(TokenStorage::class)->get())->toBeNull()
        ->and(CurrentUser::get())->toBeNull();
});

it('does not restore legacy credentials when the persistent file is corrupt', function (): void {
    Storage::disk('local')->put('pinkary-accounts.enc', 'tampered-ciphertext');
    session([
        'pinkary_accounts' => [['id' => 'legacy-id', 'username' => 'ada', 'token' => 'stale-token']],
        'pinkary_active_account' => 'legacy-id',
    ]);

    expect(app(TokenStorage::class)->get())->toBeNull();
});

it('requires a new login when the decrypted account payload is invalid', function (string $payload): void {
    Storage::disk('local')->put('pinkary-accounts.enc', Crypt::encryptString($payload));

    expect(app(TokenStorage::class)->get())->toBeNull()
        ->and(AccountStore::count())->toBe(0);
})->with([
    'malformed JSON' => ['{"accounts":'],
    'not an account payload' => ['null'],
    'invalid account records' => ['{"accounts":[{"id":"ada","token":null}],"active":"ada"}'],
]);

it('migrates a legacy session account into encrypted persistent storage', function (): void {
    Storage::disk('local')->delete('pinkary-accounts.enc');
    session([
        'pinkary_accounts' => [['id' => 'legacy-id', 'username' => 'ada', 'name' => 'Ada', 'token' => 'legacy-token']],
        'pinkary_active_account' => 'legacy-id',
    ]);

    expect(app(TokenStorage::class)->get())->toBe('legacy-token');

    session()->flush();

    expect(app(TokenStorage::class)->get())->toBe('legacy-token')
        ->and(AccountStore::activeId())->toBe('legacy-id');
});

it('migrates a legacy secure-storage account without losing the selected account', function (): void {
    Storage::disk('local')->delete('pinkary-accounts.enc');
    FakeBridge::enable()->respondTo('SecureStorage.Get', fn (array $params): array => [
        'value' => $params['key'] === 'pinkary_accounts' ? json_encode([
            'accounts' => [
                ['id' => 'ada-id', 'username' => 'ada', 'token' => 'token-a'],
                ['id' => 'grace-id', 'username' => 'grace', 'token' => 'token-b'],
            ],
            'active' => 'grace-id',
        ]) : null,
    ]);

    expect(app(TokenStorage::class)->get())->toBe('token-b');

    FakeBridge::enable()->respondTo('SecureStorage.Get', null);
    session()->flush();

    expect(app(TokenStorage::class)->get())->toBe('token-b')
        ->and(AccountStore::count())->toBe(2);
});
