<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Support\ApiErrors;
use App\Support\CurrentUser;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class EditProfile extends NativeComponent
{
    use UsesAppMenu;

    /**
     * The API's link_shape values (UpdateProfileRequest). Client state is
     * not trusted, so the picker is validated against this list rather
     * than the (public, client-writable) label map.
     *
     * @var list<string>
     */
    private const array LINK_SHAPES = ['rounded-none', 'rounded-lg', 'rounded-full'];

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
     * Account preferences the profile form owns (web parity with
     * update-profile-information-form). The API accepts them on
     * PATCH /profile; UserResource does not ship them yet, so they start
     * at the account defaults and are only sent when they differ from
     * the values this screen loaded — otherwise saving a name would
     * silently reset a preference the user set on the web.
     */
    public string $mailPreferenceTime = 'daily';

    public string $defaultFeed = 'following';

    /** 'true' = Anonymously, 'false' = Publicly — a select speaks strings. */
    public string $questionVisibility = 'true';

    /**
     * Values the screen loaded, keyed the way the API names them. Public
     * because the component is restored from its serialized state.
     *
     * @var array<string, string>
     */
    public array $loadedPreferences = [];

    /** Link pill shape (link-settings/edit): the web's Square / Round / Extra Round. */
    public string $linkShape = 'rounded-lg';

    /** The edited URL changed, so the click-reset warning applies. */
    public bool $editUrlTouched = false;

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

    /**
     * Option lists mirroring the web's selects (UserMailPreference,
     * UserDefaultFeed, the anonymous-questions pair, and the link-shape
     * radios). The web is the source of truth for these labels.
     *
     * @var array<string, string>
     */
    public array $mailPreferenceOptions = [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'never' => 'Never',
    ];

    /** @var array<string, string> */
    public array $defaultFeedOptions = [
        'following' => 'Following',
        'recent' => 'Recent',
        'trending' => 'Trending',
    ];

    /** @var array<string, string> */
    public array $questionVisibilityOptions = [
        'true' => 'Anonymously',
        'false' => 'Publicly',
    ];

    /** @var array<string, string> */
    public array $linkShapeOptions = [
        'rounded-none' => 'Square',
        'rounded-lg' => 'Round',
        'rounded-full' => 'Extra Round',
    ];

    public function navTitle(): string
    {
        return 'Edit profile';
    }

    public function mount(): void
    {
        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $this->load($token);
    }

    public function retry(): void
    {
        $token = $this->requireToken();

        if ($token === null) {
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

        $token = $this->requireToken();

        if ($token === null) {
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
            ...$this->changedPreferences(),
        ]));

        $this->busy = false;

        if ($response === null) {
            $this->errorMessage = 'Your profile could not be saved. Please try again.';

            return;
        }

        if ($this->handleUnauthorized($response)) {
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
     * Save the link shape (web link-settings/edit). It is a profile
     * setting on the API — PATCH /profile — not a link attribute, so it
     * has its own button like the web's link settings form.
     */
    public function saveLinkShape(): void
    {
        if ($this->linkBusy) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        if (! in_array($this->linkShape, self::LINK_SHAPES, true)) {
            return;
        }

        $this->linkBusy = true;
        $this->linkMessage = '';

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->updateProfile($token, [
            'link_shape' => $this->linkShape,
        ]));

        $this->linkBusy = false;

        if ($response === null) {
            $this->linkMessage = 'The link shape could not be saved. Please try again.';

            return;
        }

        if ($this->handleUnauthorized($response)) {
            return;
        }

        if (! $response->successful()) {
            $this->linkMessage = ApiErrors::first($response, 'The link shape could not be saved. Please try again.');

            return;
        }

        /** @var array<string, mixed>|null $data */
        $data = $response->json('data');

        if (is_array($data)) {
            CurrentUser::store($data);
            $this->fillFromProfile($data);
        }

        $this->linkMessage = 'Link shape saved.';
    }

    /**
     * Add a profile link (description + https URL).
     */
    public function addLink(): void
    {
        if ($this->linkBusy) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
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

        if ($this->handleUnauthorized($response)) {
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
                $this->editUrlTouched = false;
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
        $this->editUrlTouched = false;
    }

    /**
     * The API really does zero the click counter when the URL changes, so
     * the web's warning appears once the edited URL is touched
     * (links/edit:24-26, shown on `wire:dirty` for the url field).
     */
    public function updatingEditUrl(mixed $value, ?string $key = null): void
    {
        $this->editUrlTouched = true;
    }

    /**
     * Save the staged link edit.
     */
    public function saveEdit(int $id): void
    {
        if ($this->linkBusy) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
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

        if ($this->handleUnauthorized($response)) {
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

        $token = $this->requireToken();

        if ($token === null) {
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

        if ($this->handleUnauthorized($response)) {
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

        if ($this->handleUnauthorized($response)) {
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

        $linkShape = (string) data_get($profile, 'link_shape', '');
        $this->linkShape = in_array($linkShape, self::LINK_SHAPES, true) ? $linkShape : 'rounded-lg';

        // Preferences are only known when the payload ships them; the
        // loaded snapshot then freezes whatever the form is showing, so
        // an untouched field is never sent.
        $anonymous = data_get($profile, 'prefers_anonymous_questions');
        $preferences = [
            'mail_preference_time' => $this->optionOr(data_get($profile, 'mail_preference_time'), $this->mailPreferenceOptions, $this->mailPreferenceTime),
            'default_feed' => $this->optionOr(data_get($profile, 'default_feed'), $this->defaultFeedOptions, $this->defaultFeed),
            'prefers_anonymous_questions' => is_bool($anonymous)
                ? ($anonymous ? 'true' : 'false')
                : $this->questionVisibility,
        ];

        $this->mailPreferenceTime = $preferences['mail_preference_time'];
        $this->defaultFeed = $preferences['default_feed'];
        $this->questionVisibility = $preferences['prefers_anonymous_questions'];
        $this->loadedPreferences = $preferences;

        /** @var list<array<string, mixed>> $links */
        $links = array_values(array_filter(
            (array) data_get($profile, 'links', []),
            fn (mixed $link): bool => is_array($link)
        ));
        $this->links = $links;
    }

    /**
     * The preference fields the user actually changed, keyed the way the
     * API names them. Nothing is sent while the value still matches what
     * the screen loaded (the API does not report these yet, so an
     * untouched field must never overwrite a real preference).
     *
     * @return array<string, bool|string>
     */
    private function changedPreferences(): array
    {
        $changed = [];

        if ($this->mailPreferenceTime !== ($this->loadedPreferences['mail_preference_time'] ?? $this->mailPreferenceTime)) {
            $changed['mail_preference_time'] = $this->mailPreferenceTime;
        }

        if ($this->defaultFeed !== ($this->loadedPreferences['default_feed'] ?? $this->defaultFeed)) {
            $changed['default_feed'] = $this->defaultFeed;
        }

        if ($this->questionVisibility !== ($this->loadedPreferences['prefers_anonymous_questions'] ?? $this->questionVisibility)) {
            $changed['prefers_anonymous_questions'] = $this->questionVisibility === 'true';
        }

        return $changed;
    }

    /**
     * A payload value that names a real option, else the current one.
     *
     * @param  array<string, string>  $options
     */
    private function optionOr(mixed $value, array $options, string $fallback): string
    {
        $candidate = is_string($value) ? $value : '';

        return array_key_exists($candidate, $options) ? $candidate : $fallback;
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
        $split = ApiErrors::split($errors, $fields, $fallback);
        $this->fieldErrors = $split['fieldErrors'];
        $this->errorMessage = $split['message'];
    }
}
