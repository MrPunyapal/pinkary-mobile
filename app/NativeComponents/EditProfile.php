<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\NativeComponents\Concerns\UsesAppMenu;
use App\Services\PinkaryApi;
use App\Support\ApiErrors;
use App\Support\CurrentUser;
use App\Support\Payload;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class EditProfile extends NativeComponent
{
    use UsesAppMenu;

    /**
     * @var list<string>
     */
    private const array LINK_SHAPES = ['rounded-none', 'rounded-lg', 'rounded-full'];

    public bool $loading = true;

    public string $errorMessage = '';

    public string $successMessage = '';

    public string $name = '';

    public string $username = '';

    public string $bio = '';

    public string $email = '';

    public ?string $avatar = null;

    public string $mailPreferenceTime = 'daily';

    public string $defaultFeed = 'following';

    public string $questionVisibility = 'true';

    /**
     * @var array<string, string>
     */
    public array $loadedPreferences = [];

    public string $linkShape = 'rounded-lg';

    public bool $editUrlTouched = false;

    /**
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

    public function hidesDrawer(): bool
    {
        return true;
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
            'name' => mb_trim($this->name),
            'username' => mb_trim($this->username),
            'email' => mb_trim($this->email),
            'bio' => mb_trim($this->bio),
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

        $bio = mb_trim($this->bio);

        $api = app(PinkaryApi::class);
        $response = $api->attempt(fn () => $api->updateProfile($token, [
            'name' => mb_trim($this->name),
            'username' => mb_trim($this->username),
            'email' => mb_trim($this->email),
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
            $this->applyFieldErrors(Payload::stringMap($response->json('errors')), [
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

        $description = mb_trim($this->newDescription);
        $url = mb_trim($this->newUrl);

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

    public function startEdit(int $id): void
    {
        foreach ($this->links as $link) {
            if (Payload::int(data_get($link, 'id', 0)) === $id) {
                $this->editingLinkId = $id;
                $this->editDescription = Payload::string(data_get($link, 'description', ''));
                $this->editUrl = $this->bareUrl(Payload::string(data_get($link, 'url', '')));
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

    public function updatingEditUrl(mixed $value, ?string $key = null): void
    {
        $this->editUrlTouched = true;
    }

    public function saveEdit(int $id): void
    {
        if ($this->linkBusy) {
            return;
        }

        $token = $this->requireToken();

        if ($token === null) {
            return;
        }

        $description = mb_trim($this->editDescription);
        $url = mb_trim($this->editUrl);

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
                if (Payload::int(data_get($row, 'id', 0)) === $id) {
                    $this->links[$key] = $link;

                    break;
                }
            }
        }

        $this->cancelEdit();
        $this->linkMessage = 'Link saved.';
    }

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

        if ($response !== null && ($response->successful() || $response->notFound())) {
            $this->links = array_values(array_filter(
                $this->links,
                fn (mixed $link): bool => Payload::int(data_get($link, 'id', 0)) !== $id
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
        $this->name = Payload::string(data_get($profile, 'name', ''));
        $this->username = Payload::string(data_get($profile, 'username', ''));
        $this->bio = Payload::string(data_get($profile, 'bio', ''));
        $this->email = Payload::string(data_get($profile, 'email', ''));

        $avatar = data_get($profile, 'avatar');
        $this->avatar = is_string($avatar) && $avatar !== '' ? $avatar : null;

        $linkShape = Payload::string(data_get($profile, 'link_shape', ''));
        $this->linkShape = in_array($linkShape, self::LINK_SHAPES, true) ? $linkShape : 'rounded-lg';

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
            is_array(...)
        ));
        $this->links = $links;
    }

    /**
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
     * @param  array<string, string>  $options
     */
    private function optionOr(mixed $value, array $options, string $fallback): string
    {
        $candidate = is_string($value) ? $value : '';

        return array_key_exists($candidate, $options) ? $candidate : $fallback;
    }

    private function isLinkUrl(string $url): bool
    {
        if ($url === '' || mb_strlen($url) > 100) {
            return false;
        }

        if (! preg_match('#^https://#i', $url)) {
            return false;
        }

        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }

    private function bareUrl(string $url): string
    {
        $position = mb_strpos($url, '?ref=pinkary');

        if ($position !== false) {
            return mb_substr($url, 0, $position);
        }

        return Payload::string(preg_replace('/([?&])ref=pinkary(&|$)/', '$1', $url));
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
