# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to | Rule file |
| --- | --- |
| app/** | .ai/rules/app.md |
| app/NativeComponents/**, app/NativeLayout/** | .ai/rules/native-components.md |
| app/Services/** | .ai/rules/services.md |
| app/Support/** | .ai/rules/support.md |
| resources/views/**/*.blade.php | .ai/rules/views.md |
| routes/*.php | .ai/rules/routes.md |
| tests/** | .ai/rules/tests.md |

## Not carried over from the web app

This client has no equivalent of `app/Actions`, `app/Http/Controllers`, `app/Livewire`,
`app/Policies`, `app/Queries`, `app/Observers`, `app/Enums`, `app/Rules`, `app/Jobs`,
`app/Notifications` or `app/Mail`, so there are no rule files for them. Do not introduce them
without a stated reason — writes go through `app/Services/PinkaryApi.php` and permissions are the
server's decision, surfaced as response codes.