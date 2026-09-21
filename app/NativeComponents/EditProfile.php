<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Services\TokenStorage;
use App\Support\ApiErrors;
use App\Support\CurrentUser;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class EditProfile extends NativeComponent
{
    use UsesAppMenu;

    // The in-content top bar carries its own back arrow instead of the
    // drawer hamburger (no overlap), like other pushed screens.
    public function hidesDrawer(): bool
    {
        return true;
    }

    public bool $loading = true;

    public string $errorMessage = '';

    public string $successMessage = '';

    public string $name = '';

    public string $username = '';

    public string $bio = '';

    public string $email = '';

    public ?string $avatar = null;

    /**
     * First server/validation message per input, shown under the field.
     *
     * @var array<string, string>
     */
    public array $fieldErrors = [];

    public bool $busy = false;

    /** @var list<array<string, mixed>> */
    public array $links = [];

    public string $newDescription = '';

    public string $newUrl = '';

    public string $linkMessage = '';

    public bool $linkBusy = false;

    public ?int $editingLinkId = null;

    public string $editDescription = '';

    public string $editUrl = '';

    public ?int $confirmingDeleteId = null;

    public function navTitle(): string
    {
        return 'Edit profile';
    }

    public function mount(): void
    {
        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $this->load($token);
    }

    public function retry(): void
    {
        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $this->load($token);
    }

    /**
     * Save the text fields, then refresh the cached card so the drawer
     * and profile show the new values.
     */
    public function save(): void
    {
        if ($this->busy) {
            return;
        }

        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $this->errorMessage = '';
        $this->successMessage = '';
        $this->fieldErrors = [];

        $validator = Validator::make([
            'name' => trim($this->name),
            'username' => trim($this->username),
            'email' => trim($this->email),
            'bio' => trim($this->bio),
        ], [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:4', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'bio' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            $this->applyFieldErrors($validator->errors()->toArray(), [
                'name' => 'name',
                'username' => 'username',
                'email' => 'email',
                'bio' => 'bio',
            ]);

            return;
        }

        $this->busy = true;

        $bio = trim($this->bio);

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->updateProfile($token, [
            'name' => trim($this->name),
            'username' => trim($this->username),
            'email' => trim($this->email),
            'bio' => $bio === '' ? null : $bio,
        ]));

        $this->busy = false;

        if ($response === null) {
            $this->errorMessage = 'Your profile could not be saved. Please try again.';

            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->applyFieldErrors((array) $response->json('errors', []), [
                'name' => 'name',
                'username' => 'username',
                'email' => 'email',
                'bio' => 'bio',
            ], ApiErrors::first($response, 'Your profile could not be saved. Please try again.'));

            return;
        }

        /** @var array<string, mixed>|null $data */
        $data = $response->json('data');

        if (is_array($data)) {
            CurrentUser::store($data);
            $this->fillFromProfile($data);
        }

        $this->successMessage = 'Your profile has been saved.';
    }

    /**
     * Add a profile link (description + https URL).
     */
    public function addLink(): void
    {
        if ($this->linkBusy) {
            return;
        }

        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $this->linkMessage = '';

        $description = trim($this->newDescription);
        $url = trim($this->newUrl);

        if ($description === '') {
            $this->linkMessage = 'Add a short description for the link.';

            return;
        }

        if (mb_strlen($description) > 100) {
            $this->linkMessage = 'Link descriptions may not be greater than 100 characters.';

            return;
        }

        if (! $this->isLinkUrl($url)) {
            $this->linkMessage = 'Links must be valid https:// URLs.';

            return;
        }

        $this->linkBusy = true;

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->createLink($token, $description, $url));

        $this->linkBusy = false;

        if ($response === null) {
            $this->linkMessage = 'This link could not be added. Please try again.';

            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            // Limit responses (10 links, 20 for verified) arrive as 422
            // messages — surface them as-is.
            $this->linkMessage = ApiErrors::first($response, 'This link could not be added. Please try again.');

            return;
        }

        /** @var array<string, mixed>|null $link */
        $link = $response->json('data');

        if (is_array($link)) {
            $this->links = [...$this->links, $link];
        }

        $this->newDescription = '';
        $this->newUrl = '';
        $this->linkMessage = 'Link added.';
    }

    /**
     * Stage a link for inline editing.
     */
    public function startEdit(int $id): void
    {
        foreach ($this->links as $link) {
            if ((int) data_get($link, 'id', 0) === $id) {
                $this->editingLinkId = $id;
                $this->editDescription = (string) data_get($link, 'description', '');
                $this->editUrl = $this->bareUrl((string) data_get($link, 'url', ''));
                $this->linkMessage = '';
                $this->confirmingDeleteId = null;

                return;
            }
        }
    }

    public function cancelEdit(): void
    {
        $this->editingLinkId = null;
        $this->editDescription = '';
        $this->editUrl = '';
    }

    /**
     * Save the staged link edit.
     */
    public function saveEdit(int $id): void
    {
        if ($this->linkBusy) {
            return;
        }

        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        $description = trim($this->editDescription);
        $url = trim($this->editUrl);

        if ($description === '') {
            $this->linkMessage = 'Add a short description for the link.';

            return;
        }

        if (mb_strlen($description) > 100) {
            $this->linkMessage = 'Link descriptions may not be greater than 100 characters.';

            return;
        }

        if (! $this->isLinkUrl($url)) {
            $this->linkMessage = 'Links must be valid https:// URLs.';

            return;
        }

        $this->linkBusy = true;

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->updateLink($token, $id, $description, $url));

        $this->linkBusy = false;

        if ($response === null) {
            $this->linkMessage = 'This link could not be saved. Please try again.';

            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->linkMessage = ApiErrors::first($response, 'This link could not be saved. Please try again.');

            return;
        }

        /** @var array<string, mixed>|null $link */
        $link = $response->json('data');

        if (is_array($link)) {
            foreach ($this->links as $key => $row) {
                if ((int) data_get($row, 'id', 0) === $id) {
                    $this->links[$key] = $link;

                    break;
                }
            }
        }

        $this->cancelEdit();
        $this->linkMessage = 'Link saved.';
    }

    /**
     * Delete a link with a two-tap confirm (no dialog API): the first
     * tap arms the row, the second tap deletes it.
     */
    public function deleteLink(int $id): void
    {
        if ($this->linkBusy) {
            return;
        }

        $token = $this->token();

        if ($token === null) {
            $this->replace($this->route('mobile.login'));

            return;
        }

        if ($this->confirmingDeleteId !== $id) {
            $this->confirmingDeleteId = $id;
            $this->editingLinkId = null;
            $this->linkMessage = 'Tap delete again to confirm.';

            return;
        }

        $this->linkBusy = true;

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->deleteLink($token, $id));

        $this->linkBusy = false;
        $this->confirmingDeleteId = null;

        // A missing link is already gone — treat it as deleted.
        if ($response !== null && ($response->successful() || $response->notFound())) {
            $this->links = array_values(array_filter(
                $this->links,
                fn (mixed $link): bool => (int) data_get($link, 'id', 0) !== $id
            ));
            $this->linkMessage = 'Link deleted.';

            return;
        }

        if ($response === null) {
            $this->linkMessage = 'This link could not be deleted. Please try again.';

            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        $this->linkMessage = 'This link could not be deleted. Please try again.';
    }

    public function viewProfile(): void
    {
        $this->navigate($this->route('mobile.profile'));
    }

    public function render(): View
    {
        return view('native.edit-profile');
    }

    private function load(string $token): void
    {
        $this->loading = true;
        $this->errorMessage = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->profile($token));

        if ($response === null) {
            $this->loading = false;
            $this->errorMessage = 'Your profile could not be loaded. Please try again.';

            return;
        }

        if ($response->unauthorized()) {
            app(TokenStorage::class)->clear();
            $this->replace($this->route('mobile.login'));

            return;
        }

        if (! $response->successful()) {
            $this->loading = false;
            $this->errorMessage = 'Your profile could not be loaded. Please try again.';

            return;
        }

        /** @var array<string, mixed>|null $profile */
        $profile = $response->json('data');

        if (is_array($profile)) {
            $this->fillFromProfile($profile);
        }

        $this->loading = false;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function fillFromProfile(array $profile): void
    {
        $this->name = (string) data_get($profile, 'name', '');
        $this->username = (string) data_get($profile, 'username', '');
        $this->bio = (string) data_get($profile, 'bio', '');
        $this->email = (string) data_get($profile, 'email', '');

        $avatar = data_get($profile, 'avatar');
        $this->avatar = is_string($avatar) && $avatar !== '' ? $avatar : null;

        /** @var list<array<string, mixed>> $links */
        $links = array_values(array_filter(
            (array) data_get($profile, 'links', []),
            fn (mixed $link): bool => is_array($link)
        ));
        $this->links = $links;
    }

    /**
     * Links must be https URLs (the API requires https and rejects the
     * rest with a 422 — catch the obvious cases up front).
     */
    private function isLinkUrl(string $url): bool
    {
        if ($url === '' || mb_strlen($url) > 100) {
            return false;
        }

        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }

        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }

    /**
     * Show the stored URL without the ?ref=pinkary marker the API
     * appends, so edits start from what the user typed.
     */
    private function bareUrl(string $url): string
    {
        $position = strpos($url, '?ref=pinkary');

        if ($position !== false) {
            return substr($url, 0, $position);
        }

        return (string) preg_replace('/([?&])ref=pinkary(&|$)/', '$1', $url);
    }

    /**
     * Split a Laravel errors bag into per-field messages (shown under
     * each input) plus a summary: the first message from fields
     * without their own input, else the first message overall.
     *
     * @param  array<string, mixed>  $errors
     * @param  array<string, string>  $fields  server key => fieldErrors key
     */
    private function applyFieldErrors(array $errors, array $fields, ?string $fallback = null): void
    {
        $first = null;
        $unmappedFirst = null;

        foreach ($errors as $key => $messages) {
            $message = is_array($messages) ? ($messages[0] ?? null) : $messages;

            if (! is_string($message) || $message === '') {
                continue;
            }

            $first ??= $message;

            if (isset($fields[$key])) {
                $this->fieldErrors[$fields[$key]] = $message;
            } else {
                $unmappedFirst ??= $message;
            }
        }

        $this->errorMessage = $unmappedFirst ?? $first ?? $fallback ?? 'Something went wrong. Please try again.';
    }

    private function token(): ?string
    {
        $token = app(TokenStorage::class)->get();

        return is_string($token) && $token !== '' ? $token : null;
    }
}
