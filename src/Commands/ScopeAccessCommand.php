<?php

namespace Maxiviper117\Access\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ScopeAccessCommand extends Command
{
    protected $signature = 'access:scope
        {--name= : Scope name to use}
        {--singular= : Override singular form}
        {--plural= : Override plural form}
        {--frontend= : Invitation UI stack to generate: blade, react, vue, or svelte}
        {--notifications : Include invitation creation methods and email notification scaffold}
        {--force : Overwrite existing published files}
        {--migrate : Run migrations after scaffolding}
        {--no-concern : Skip patching the User model with the HasXxx concern}';

    protected $description = 'Scaffold renamed team-style scope support for Laravel Access.';

    public function handle(Filesystem $files): int
    {
        $names = $this->resolveNames();

        if (! $this->option('name') && ! $this->confirmNames($names)) {
            $this->warn('Scope scaffolding cancelled.');

            return self::FAILURE;
        }

        $published = [];

        foreach ($this->migrationFiles($names) as $path => $contents) {
            $this->writeFile($files, $path, $contents, $published);
        }

        foreach ($this->appFiles($names) as $path => $contents) {
            $this->writeFile($files, $path, $contents, $published);
        }

        $this->patchConfig($files, $names, $published);
        $this->patchPermissionEnum($files, $names, $published);
        $this->patchAppServiceProvider($files, $names, $published);
        $this->patchBootstrapMiddleware($files, $names, $published);

        if (! $this->option('no-concern')) {
            $this->patchUserModel($files, $names, $published);
        }

        if ($this->option('migrate')) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->info('Laravel Access scope scaffolding complete.');

        foreach ($published as $line) {
            $this->line(" - {$line}");
        }

        $this->newLine();
        $this->line('Next steps: review the generated routes/views for your starter kit, then run php artisan migrate if you did not pass --migrate.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string|bool>
     */
    private function resolveNames(): array
    {
        $nameOption = $this->option('name');
        $nameInput = is_string($nameOption) && $nameOption !== ''
            ? $nameOption
            : (is_string($askResult = $this->ask('What should the group be called?', 'team')) ? $askResult : 'team');
        $base = Str::of($nameInput)
            ->trim()
            ->lower()
            ->snake()
            ->toString();

        $singularOption = $this->option('singular');
        $singularInput = is_string($singularOption) && $singularOption !== '' ? $singularOption : $base;
        $singular = Str::of($singularInput)->trim()->lower()->snake()->toString();

        $pluralOption = $this->option('plural');
        $pluralInput = is_string($pluralOption) && $pluralOption !== '' ? $pluralOption : Str::plural($singular);
        $plural = Str::of($pluralInput)->trim()->lower()->snake()->toString();
        $frontend = $this->resolveFrontend();
        $interactive = ! $this->option('name');
        $notifications = (bool) $this->option('notifications');

        if ($interactive) {
            $notifications = $this->confirm('Generate invitation email notification helpers?', true);
        }

        return [
            'singular' => $singular,
            'plural' => $plural,
            'studly' => Str::studly($singular),
            'studlyPlural' => Str::studly($plural),
            'camel' => Str::camel($singular),
            'table' => $plural,
            'membersTable' => "{$singular}_members",
            'invitationsTable' => "{$singular}_invitations",
            'currentColumn' => "current_{$singular}_id",
            'currentRouteKey' => "current_{$singular}",
            'frontend' => $frontend,
            'inertiaDirectory' => 'auth',
            'invitationErrorPage' => Str::studly($singular).'InvitationError',
            'invitedRegisterPage' => Str::studly($singular).'InvitedRegister',
            'notifications' => $notifications,
        ];
    }

    private function resolveFrontend(): string
    {
        $frontend = $this->option('frontend');

        if ($frontend === null && ! $this->option('name')) {
            $frontend = $this->choice(
                'Which invitation UI should be generated?',
                ['blade', 'react', 'vue', 'svelte'],
                'blade'
            );
        }

        $frontendInput = is_string($frontend) && $frontend !== '' ? $frontend : 'blade';
        $frontend = Str::of($frontendInput)->trim()->lower()->toString();

        if (! in_array($frontend, ['blade', 'react', 'vue', 'svelte'], true)) {
            $this->warn("Unsupported frontend [{$frontend}], falling back to blade.");

            return 'blade';
        }

        return $frontend;
    }

    /**
     * @param  array<string, string|bool>  $names
     */
    private function confirmNames(array $names): bool
    {
        $this->line("Singular: {$names['singular']}  |  Plural: {$names['plural']}  |  Table: {$names['table']}");

        return $this->confirm('Confirm?', true);
    }

    /**
     * @param  array<string, string|bool>  $names
     * @return array<string, string>
     */
    private function migrationFiles(array $names): array
    {
        $stamp = now()->format('Y_m_d_His');

        return [
            database_path("migrations/{$stamp}_create_{$names['table']}_table.php") => $this->scopeMigration($names),
            database_path('migrations/'.now()->addSecond()->format('Y_m_d_His')."_create_{$names['membersTable']}_table.php") => $this->membersMigration($names),
            database_path('migrations/'.now()->addSeconds(2)->format('Y_m_d_His')."_create_{$names['invitationsTable']}_table.php") => $this->invitationsMigration($names),
            database_path('migrations/'.now()->addSeconds(3)->format('Y_m_d_His')."_add_{$names['currentColumn']}_to_users_table.php") => $this->currentScopeMigration($names),
        ];
    }

    /**
     * @param  array<string, string|bool>  $names
     * @return array<string, string>
     */
    private function appFiles(array $names): array
    {
        $studly = $names['studly'];
        $studlyPlural = $names['studlyPlural'];

        $files = [
            app_path("Models/{$studly}.php") => $this->scopeModel($names),
            app_path('Models/Membership.php') => $this->membershipModel($names),
            app_path("Models/{$studly}Invitation.php") => $this->invitationModel($names),
            app_path("Concerns/Has{$studlyPlural}.php") => $this->concern($names),
            app_path("Http/Middleware/Ensure{$studly}Membership.php") => $this->middleware($names),
            app_path("Enums/{$studly}Role.php") => $this->roleEnum($names),
            app_path("Http/Controllers/Auth/{$studly}InvitationController.php") => $this->invitationController($names),
            base_path("routes/{$names['singular']}-invitations.php") => $this->routes($names),
        ];

        if ($names['notifications']) {
            $files[app_path("Notifications/{$studly}InvitationNotification.php")] = $this->invitationNotification($names);
        }

        return $files + $this->invitationUiFiles($names);
    }

    /**
     * @param  array<string, string|bool>  $names
     * @return array<string, string>
     */
    private function invitationUiFiles(array $names): array
    {
        $directory = $names['inertiaDirectory'];
        $errorPage = $names['invitationErrorPage'];
        $registerPage = $names['invitedRegisterPage'];

        return match ($names['frontend']) {
            'react' => [
                resource_path("js/Pages/{$directory}/{$errorPage}.tsx") => $this->reactErrorPage($names),
                resource_path("js/Pages/{$directory}/{$registerPage}.tsx") => $this->reactRegisterPage($names),
            ],
            'vue' => [
                resource_path("js/Pages/{$directory}/{$errorPage}.vue") => $this->vueErrorPage($names),
                resource_path("js/Pages/{$directory}/{$registerPage}.vue") => $this->vueRegisterPage($names),
            ],
            'svelte' => [
                resource_path("js/Pages/{$directory}/{$errorPage}.svelte") => $this->svelteErrorPage($names),
                resource_path("js/Pages/{$directory}/{$registerPage}.svelte") => $this->svelteRegisterPage($names),
            ],
            default => [
                resource_path("views/auth/{$names['singular']}-invitation-error.blade.php") => $this->errorView($names),
                resource_path("views/auth/{$names['singular']}-invited-register.blade.php") => $this->registerView($names),
            ],
        };
    }

    /**
     * @param  list<string>  $published
     */
    private function writeFile(Filesystem $files, string $path, string $contents, array &$published): void
    {
        if ($files->exists($path) && ! $this->option('force')) {
            $published[] = "Skipped existing {$this->relativePath($path)}";

            return;
        }

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $contents);
        $published[] = "Published {$this->relativePath($path)}";
    }

    /**
     * @param  array<string, string|bool>  $names
     * @param  list<string>  $published
     */
    private function patchConfig(Filesystem $files, array $names, array &$published): void
    {
        $path = config_path('access.php');

        if (! $files->exists($path)) {
            $this->call('vendor:publish', ['--tag' => 'access-config', '--force' => false]);
        }

        if (! $files->exists($path)) {
            return;
        }

        $contents = $files->get($path);
        $model = "\\App\\Models\\{$names['studly']}::class";

        $contents = preg_replace(
            "/    'default_scope_model' => .*?,\\R/",
            "    'default_scope_model' => {$model},\n",
            $contents,
            1
        ) ?? $contents;

        $teams = <<<PHP
    'teams' => [
        'model' => {$model},
        'singular' => '{$names['singular']}',
        'plural' => '{$names['plural']}',
    ],

    'invitations' => [
        'require_existing_user' => false,
        'expiry_days' => 7,
        'redirect_after_accept' => 'dashboard',
    ],

PHP;

        if (str_contains($contents, "'teams' => [")) {
            $contents = preg_replace("/    'invitations' => \\[.*?\\],\\R\\R/s", '', $contents, 1) ?? $contents;
            $contents = preg_replace("/    'teams' => \\[.*?\\],\\R\\R/s", $teams, $contents, 1) ?? $contents;
        } else {
            $contents = str_replace("    'cache' => [", $teams."    'cache' => [", $contents);
        }

        $files->put($path, $contents);
        $published[] = 'Updated config/access.php';
    }

    /**
     * @param  array<string, string|bool>  $names
     * @param  list<string>  $published
     */
    private function patchPermissionEnum(Filesystem $files, array $names, array &$published): void
    {
        $path = app_path('Enums/Permission.php');

        if (! $files->exists($path)) {
            $published[] = 'Skipped app/Enums/Permission.php scope permission cases because the file does not exist';

            return;
        }

        $contents = $files->get($path);
        $cases = $this->permissionEnumCases($names);

        if (str_contains($contents, $cases[0])) {
            return;
        }

        $insert = "\n";

        foreach ($cases as $case) {
            $insert .= "    {$case}\n";
        }

        $patched = preg_replace('/\\n}\\s*$/', $insert."}\n", $contents, 1);

        if ($patched === null || $patched === $contents) {
            return;
        }

        $files->put($path, $patched);
        $published[] = 'Updated app/Enums/Permission.php';
    }

    /**
     * @param  array<string, string|bool>  $names
     * @param  list<string>  $published
     */
    private function patchAppServiceProvider(Filesystem $files, array $names, array &$published): void
    {
        $path = app_path('Providers/AppServiceProvider.php');

        if (! $files->exists($path)) {
            return;
        }

        $contents = $files->get($path);

        if (! str_contains($contents, 'URL::defaults')) {
            $contents = preg_replace('/use Illuminate\\\Support\\\ServiceProvider;\\R/', "use Illuminate\\Support\\Facades\\URL;\nuse Illuminate\\Support\\ServiceProvider;\n", $contents, 1) ?? $contents;
            $needle = "    public function boot(): void\n    {\n";
            $insert = "    public function boot(): void\n    {\n        if (auth()->check() && auth()->user()->{$names['camel']}) {\n            URL::defaults(['{$names['currentRouteKey']}' => auth()->user()->{$names['camel']}->getRouteKey()]);\n        }\n";
            $contents = str_replace($needle, $insert, $contents);
            $files->put($path, $contents);
            $published[] = 'Updated app/Providers/AppServiceProvider.php';
        }
    }

    /**
     * @param  array<string, string|bool>  $names
     * @param  list<string>  $published
     */
    private function patchBootstrapMiddleware(Filesystem $files, array $names, array &$published): void
    {
        $path = base_path('bootstrap/app.php');

        if (! $files->exists($path)) {
            return;
        }

        $contents = $files->get($path);
        $alias = $names['singular'];
        $class = "\\App\\Http\\Middleware\\Ensure{$names['studly']}Membership::class";
        $changed = false;

        if (! str_contains($contents, "'{$alias}' => {$class}")) {
            $patched = preg_replace(
                '/->withMiddleware\\(function \\(Middleware \\$middleware\\): void \\{\\R/',
                "->withMiddleware(function (Middleware \$middleware): void {\n        \$middleware->alias([\n            '{$alias}' => {$class},\n        ]);\n",
                $contents,
                1
            );

            if ($patched !== null && $patched !== $contents) {
                $contents = $patched;
                $changed = true;
            }
        }

        $routeFile = "routes/{$names['singular']}-invitations.php";

        if (! str_contains($contents, $routeFile)) {
            $patched = preg_replace(
                "/(->withRouting\\([\\s\\S]*?health: ['\"]\\/up['\"],\\R)(\\s*\\))/",
                "$1        then: function (): void {\n            \\Illuminate\\Support\\Facades\\Route::middleware('web')\n                ->group(base_path('{$routeFile}'));\n        },\n$2",
                $contents,
                1
            );

            if ($patched !== null && $patched !== $contents) {
                $contents = $patched;
                $changed = true;
            }
        }

        if (! $changed) {
            return;
        }

        $files->put($path, $contents);
        $published[] = 'Updated bootstrap/app.php';
    }

    /**
     * @param  array<string, string|bool>  $names
     * @param  list<string>  $published
     */
    private function patchUserModel(Filesystem $files, array $names, array &$published): void
    {
        $path = app_path('Models/User.php');

        if (! $files->exists($path)) {
            return;
        }

        $contents = $files->get($path);
        $trait = "Has{$names['studlyPlural']}";

        if (str_contains($contents, "use App\\Concerns\\{$trait};")) {
            return;
        }

        $import = "use App\\Concerns\\{$trait};";
        if (preg_match('/namespace [^;]+;/', $contents, $matches)) {
            $contents = str_replace($matches[0], $matches[0]."\n\n".$import, $contents);
        } else {
            $contents = "<?php\n\n".$import."\n".ltrim(substr($contents, 5));
        }

        if (preg_match('/class User\s+(extends\s+[^{]+)?\s*\{/', $contents, $matches)) {
            $contents = str_replace($matches[0], $matches[0]."\n    use {$trait};", $contents);
        }

        $files->put($path, $contents);
        $published[] = 'Updated app/Models/User.php';
    }

    private function relativePath(string $path): string
    {
        return Str::of($path)->replace(base_path(DIRECTORY_SEPARATOR), '')->replace('\\', '/')->toString();
    }

    /**
     * @param  array<string, string|bool>  $n
     */
    private function scopeMigration(array $n): string
    {
        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$n['table']}', function (Blueprint \$table): void {
            \$table->id();
            \$table->string('name');
            \$table->string('slug')->unique();
            \$table->timestamps();
            \$table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$n['table']}');
    }
};

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function membersMigration(array $n): string
    {
        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$n['membersTable']}', function (Blueprint \$table): void {
            \$table->id();
            \$table->foreignId('{$n['singular']}_id')->constrained('{$n['table']}')->cascadeOnDelete();
            \$table->foreignId('user_id')->constrained()->cascadeOnDelete();
            \$table->string('role');
            \$table->timestamps();
            \$table->unique(['{$n['singular']}_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$n['membersTable']}');
    }
};

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function invitationsMigration(array $n): string
    {
        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$n['invitationsTable']}', function (Blueprint \$table): void {
            \$table->id();
            \$table->foreignId('{$n['singular']}_id')->constrained('{$n['table']}')->cascadeOnDelete();
            \$table->string('email');
            \$table->string('role');
            \$table->string('code', 64)->unique();
            \$table->timestamp('expires_at')->nullable();
            \$table->timestamp('accepted_at')->nullable();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$n['invitationsTable']}');
    }
};

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function currentScopeMigration(array $n): string
    {
        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint \$table): void {
            \$table->foreignId('{$n['currentColumn']}')->nullable()->after('id')->constrained('{$n['table']}')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint \$table): void {
            \$table->dropConstrainedForeignId('{$n['currentColumn']}');
        });
    }
};

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function scopeModel(array $n): string
    {
        return <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Maxiviper117\Access\Contracts\AccessScope;

class {$n['studly']} extends Model implements AccessScope
{
    use SoftDeletes;

    protected \$fillable = ['name', 'slug'];

    /**
     * Get the route key name used for route model binding.
     *
     * @return string The column name used for binding (slug).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get all users that belong to this {$n['singular']}.
     *
     * @return BelongsToMany<User, \$this, pivot: Membership>
     */
    public function users(): BelongsToMany
    {
        return \$this->belongsToMany(User::class, '{$n['membersTable']}')
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get all pending invitations for this {$n['singular']}.
     *
     * @return HasMany<{$n['studly']}Invitation>
     */
    public function invitations(): HasMany
    {
        return \$this->hasMany({$n['studly']}Invitation::class);
    }
}

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function membershipModel(array $n): string
    {
        return <<<PHP
<?php

namespace App\Models;

use App\Enums\\{$n['studly']}Role;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Membership extends Pivot
{
    protected \$table = '{$n['membersTable']}';

    protected \$fillable = ['{$n['singular']}_id', 'user_id', 'role'];

    protected \$casts = [
        'role' => {$n['studly']}Role::class,
    ];
}

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function invitationModel(array $n): string
    {
        return <<<PHP
<?php

namespace App\Models;

use App\Enums\\{$n['studly']}Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class {$n['studly']}Invitation extends Model
{
    protected \$table = '{$n['invitationsTable']}';

    protected \$fillable = ['{$n['singular']}_id', 'email', 'role', 'code', 'expires_at', 'accepted_at'];

    protected \$casts = [
        'role' => {$n['studly']}Role::class,
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * Boot the model to auto-generate invitation code and expiry date on creation.
     */
    protected static function booted(): void
    {
        static::creating(function (self \$invitation): void {
            \$invitation->code ??= Str::random(64);
            \$invitation->expires_at ??= now()->addDays(config('access.invitations.expiry_days', 7));
        });
    }

    /**
     * Get the {$n['singular']} this invitation belongs to.
     *
     * @return BelongsTo<{$n['studly']}, \$this>
     */
    public function {$n['camel']}(): BelongsTo
    {
        return \$this->belongsTo({$n['studly']}::class);
    }

    /**
     * Determine if the invitation has expired.
     *
     * @return bool True if the expiry date is in the past.
     */
    public function isExpired(): bool
    {
        return \$this->expires_at !== null && \$this->expires_at->isPast();
    }

    /**
     * Determine if the invitation has already been accepted.
     *
     * @return bool True if the accepted_at timestamp is set.
     */
    public function isAccepted(): bool
    {
        return \$this->accepted_at !== null;
    }
}

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function concern(array $n): string
    {
        return <<<PHP
<?php

namespace App\Concerns;

use App\Enums\\{$n['studly']}Role;
use App\Models\Membership;
use App\Models\\{$n['studly']};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait Has{$n['studlyPlural']}
{
    /**
     * Get all {$n['plural']} the user belongs to.
     *
     * @return BelongsToMany<{$n['studly']}, \$this, pivot: Membership>
     */
    public function {$n['plural']}(): BelongsToMany
    {
        return \$this->belongsToMany({$n['studly']}::class, '{$n['membersTable']}')
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the user's currently active {$n['singular']}.
     *
     * @return BelongsTo<{$n['studly']}, \$this>
     */
    public function {$n['camel']}(): BelongsTo
    {
        return \$this->belongsTo({$n['studly']}::class, '{$n['currentColumn']}');
    }

    /**
     * Determine if the user belongs to the given {$n['singular']}.
     *
     * @param  {$n['studly']}  \$scope  The {$n['singular']} to check membership against.
     * @return bool True if the user is a member of the {$n['singular']}.
     */
    public function belongsTo{$n['studly']}({$n['studly']} \$scope): bool
    {
        return \$this->{$n['plural']}()->whereKey(\$scope->getKey())->exists();
    }

    /**
     * Switch the user's current {$n['singular']} context to the given {$n['singular']}.
     *
     * @param  {$n['studly']}  \$scope  The {$n['singular']} to switch to.
     * @return bool True if the switch was successful, false if the user is not a member.
     */
    public function switch{$n['studly']}({$n['studly']} \$scope): bool
    {
        if (! \$this->belongsTo{$n['studly']}(\$scope)) {
            return false;
        }

        return \$this->forceFill(['{$n['currentColumn']}' => \$scope->getKey()])->save();
    }

    /**
     * Determine if the given {$n['singular']} is the user's currently active {$n['singular']}.
     *
     * @param  {$n['studly']}  \$scope  The {$n['singular']} to check.
     * @return bool True if the {$n['singular']} is currently active.
     */
    public function isCurrent{$n['studly']}({$n['studly']} \$scope): bool
    {
        return (int) \$this->{$n['currentColumn']} === (int) \$scope->getKey();
    }

    /**
     * Get the user's role within the given {$n['singular']}.
     *
     * @param  {$n['studly']}  \$scope  The {$n['singular']} to get the role for.
     * @return {$n['studly']}Role|null The user's role, or null if not a member.
     */
    public function {$n['camel']}Role({$n['studly']} \$scope): ?{$n['studly']}Role
    {
        \$membership = \$this->{$n['plural']}()->whereKey(\$scope->getKey())->first()?->pivot;

        return \$membership?->role;
    }
}

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function middleware(array $n): string
    {
        return <<<PHP
<?php

namespace App\Http\Middleware;

use App\Enums\\{$n['studly']}Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Ensure{$n['studly']}Membership
{
    /**
     * Handle an incoming request by verifying the user is a member of the route's {$n['singular']}.
     *
     * @param  Request  \$request  The incoming HTTP request.
     * @param  Closure  \$next  The next middleware handler.
     * @param  string|null  \$minimumRole  Optional minimum role level required (e.g., 'Admin').
     * @return Response The response, or a 403 abort if membership check fails.
     */
    public function handle(Request \$request, Closure \$next, ?string \$minimumRole = null): Response
    {
        \$user = \$request->user();
        \$scope = \$request->route('{$n['currentRouteKey']}') ?: \$request->route('{$n['singular']}');
        
        // Verify the user is authenticated, a scope is bound, and they are a member
        // of that scope. Manually changing the URL to a {$n['singular']} the user does
        // not belong to results in a 403 here — before any controller logic runs.
        abort_if(! \$user || ! \$scope || ! \$user->belongsTo{$n['studly']}(\$scope), 403);

        if (\$minimumRole !== null) {
            // Fetch the user's app-level membership role (e.g. Owner, Admin, Member)
            // from the pivot table and compare its level() against the minimum required.
            // This is distinct from the Laravel Access scoped role assignment —
            // it checks the app-owned memberships table, not the access_role_user table.
            \$role = \$user->{$n['camel']}Role(\$scope);
            abort_if(! \$role || \$role->level() < {$n['studly']}Role::from(\$minimumRole)->level(), 403);
        }

        // When the route includes a current-scope parameter (e.g. {current_company}),
        // automatically switch the user's active scope to the route-bound {$n['singular']}
        // if it isn't already their current one. This keeps the user's session in sync
        // with the {$n['singular']} they are actively viewing without requiring an explicit
        // switchCompany() call in every controller.
        //
        // Safety: the `belongsTo{$n['studly']}` check above (line ~853) already ensures
        // only actual members reach this point. Manually typing a URL for a {$n['singular']}
        // the user does not belong to will result in a 403 before this switch runs.
        if (\$request->route('{$n['currentRouteKey']}') && ! \$user->isCurrent{$n['studly']}(\$scope)) {
            \$user->switch{$n['studly']}(\$scope);
        }

        return \$next(\$request);
    }
}

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function roleEnum(array $n): string
    {
        return <<<PHP
<?php

namespace App\Enums;

enum {$n['studly']}Role: string
{
    case Owner = 'Owner';
    case Admin = 'Admin';
    case Member = 'Member';

    /**
     * Get the numeric hierarchy level of the role for comparison.
     *
     * @return int Higher values indicate greater permissions (Owner=3, Admin=2, Member=1).
     */
    public function level(): int
    {
        return match (\$this) {
            self::Owner => 3,
            self::Admin => 2,
            self::Member => 1,
        };
    }
}

PHP;
    }

    /**
     * @param  array<string, string|bool>  $n
     * @return array<int, string>
     */
    private function permissionEnumCases(array $n): array
    {
        return [
            "case {$n['studly']}MembersView = '{$n['singular']}.members.view';",
            "case {$n['studly']}MembersInvite = '{$n['singular']}.members.invite';",
            "case {$n['studly']}MembersManage = '{$n['singular']}.members.manage';",
            "case {$n['studly']}SettingsManage = '{$n['singular']}.settings.manage';",
        ];
    }

    /** @param  array<string, string|bool>  $n */
    private function invitationController(array $n): string
    {
        $inertiaImports = $n['frontend'] !== 'blade'
            ? "use Inertia\\Inertia;\nuse Inertia\\Response as InertiaResponse;\n"
            : '';
        $notificationImport = $n['notifications']
            ? "use App\\Notifications\\{$n['studly']}InvitationNotification;\n"
            : '';
        $notificationFacadeImport = $n['notifications']
            ? "use Illuminate\\Support\\Facades\\Notification;\n"
            : '';
        $responseType = $n['frontend'] !== 'blade'
            ? 'View|InertiaResponse|RedirectResponse|SymfonyResponse'
            : 'View|RedirectResponse|SymfonyResponse';
        $renderType = $n['frontend'] !== 'blade' ? 'InertiaResponse' : 'View';
        $invitationMethods = $n['notifications'] ? $this->invitationNotificationControllerMethods($n) : '';

        return <<<PHP
<?php

namespace App\Http\Controllers\Auth;

{$notificationImport}use App\Models\\{$n['studly']};
use App\Models\\{$n['studly']}Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
{$notificationFacadeImport}use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
{$inertiaImports}

class {$n['studly']}InvitationController extends Controller
{
    /**
     * Show the invitation acceptance page or redirect to registration if needed.
     *
     * @param  {$n['studly']}Invitation  \$invitation  The invitation being accessed.
     * @return {$responseType}
     */
    public function show({$n['studly']}Invitation \$invitation): {$responseType}
    {
        if (\$response = \$this->invalidInvitationResponse(\$invitation)) {
            return \$response;
        }

        \$user = User::where('email', \$invitation->email)->first();

        if (! \$user && ! config('access.invitations.require_existing_user', false)) {
            return redirect()->route('{$n['singular']}.invitations.register', \$invitation);
        }

        return \$this->renderInvitationError(\$user ? null : 'This invitation can only be accepted by an existing user.', \$invitation);
    }

    /**
     * Show the registration form for an invited user who does not yet have an account.
     *
     * @param  {$n['studly']}Invitation  \$invitation  The invitation being accessed.
     * @return {$responseType}
     */
    public function registerForm({$n['studly']}Invitation \$invitation): {$responseType}
    {
        if (\$response = \$this->invalidInvitationResponse(\$invitation)) {
            return \$response;
        }

        return \$this->renderRegisterForm(\$invitation);
    }

    /**
     * Render the invitation error page with an optional message.
     *
     * @param  string|null  \$message  The error message to display.
     * @param  {$n['studly']}Invitation  \$invitation  The invitation associated with the error.
     * @return {$renderType}
     */
    private function renderInvitationError(?string \$message, {$n['studly']}Invitation \$invitation): {$renderType}
    {
PHP
            .$this->renderInvitationErrorBody($n).
            <<<PHP
    }

    /**
     * Render the invited user registration form.
     *
     * @param  {$n['studly']}Invitation  \$invitation  The invitation to register for.
     * @return {$renderType}
     */
    private function renderRegisterForm({$n['studly']}Invitation \$invitation): {$renderType}
    {
PHP
            .$this->renderRegisterFormBody($n).
            <<<PHP
    }

    /**
     * Extract shared invitation data to pass to the frontend views.
     *
     * @param  {$n['studly']}Invitation  \$invitation  The invitation to extract data from.
     * @return array<string, string> The invitation code and email.
     */
    private function invitationProps({$n['studly']}Invitation \$invitation): array
    {
        return [
            'code' => \$invitation->code,
            'email' => \$invitation->email,
            '{$n['camel']}Name' => \$invitation->{$n['camel']}->name,
        ];
    }

{$invitationMethods}
PHP
            .<<<'PHP'
    /**
     * Accept a valid invitation for an existing authenticated user.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  TeamInvitation  $invitation  The invitation being accepted.
     * @return RedirectResponse
     */
    public function accept(Request $request, 
PHP
            ."{$n['studly']}Invitation \$invitation): RedirectResponse\n".
             <<<'PHP'
    {
        // @phpstan-ignore encapsedStringPart.nonString
        if ($response = $this->invalidInvitationResponse($invitation)) {
            return $response;
        }

        abort_if(! $request->user() || $request->user()->email !== $invitation->email, 403);

        // @phpstan-ignore encapsedStringPart.nonString
        $this->acceptInvitation($invitation, $request->user());

        return redirect()->route(config('access.invitations.redirect_after_accept', 'dashboard'));
    }

PHP
            .<<<'PHP'
    /**
     * Register a new user and accept the invitation in one step.
     *
     * @param  Request  $request  The incoming HTTP request with registration data.
     * @param  TeamInvitation  $invitation  The invitation being accepted.
     * @return RedirectResponse
     */
    public function register(Request $request, 
PHP
            ."{$n['studly']}Invitation \$invitation): RedirectResponse\n".
            <<<'PHP'
    {
        // @phpstan-ignore encapsedStringPart.nonString
        if ($response = $this->invalidInvitationResponse($invitation)) {
            return $response;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $invitation->email,
            'password' => Hash::make($validated['password']),
        ]);

        // @phpstan-ignore encapsedStringPart.nonString
        $this->acceptInvitation($invitation, $user);
        Auth::login($user);

        return redirect()->route(config('access.invitations.redirect_after_accept', 'dashboard'));
    }

PHP
            .<<<'PHP'
    /**
     * Attach the user to the scope and mark the invitation as accepted.
     *
     * @param  TeamInvitation  $invitation  The invitation to process.
     * @param  User  $user  The user to attach to the scope.
     */
    private function acceptInvitation(
PHP
            ."{$n['studly']}Invitation \$invitation, User \$user): void\n".
            <<<PHP
    {
        \$invitation->{$n['camel']}->users()->syncWithoutDetaching([
            \$user->getKey() => ['role' => \$invitation->role->value],
        ]);

        \$user->switch{$n['studly']}(\$invitation->{$n['camel']});
        \$invitation->forceFill(['accepted_at' => now()])->save();
    }

    /**
     * Check if the invitation is invalid (accepted or expired) and return an error response if so.
     *
     * @param  {$n['studly']}Invitation  \$invitation  The invitation to validate.
     * @return SymfonyResponse|null Error response if invalid, null if valid.
     */
    private function invalidInvitationResponse({$n['studly']}Invitation \$invitation): ?SymfonyResponse
    {
        if (\$invitation->isAccepted() || \$invitation->isExpired()) {
PHP
            .$this->invalidInvitationResponseBody($n).
            <<<'PHP'
        }

        return null;
    }
}

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function renderInvitationErrorBody(array $n): string
    {
        if ($n['frontend'] !== 'blade') {
            $component = "{$n['inertiaDirectory']}/{$n['invitationErrorPage']}";

            return <<<PHP
        return Inertia::render('{$component}', [
            'message' => \$message ?? 'This {$n['singular']} invitation cannot be accepted.',
            'invitation' => \$this->invitationProps(\$invitation),
        ]);

PHP;
        }

        return <<<PHP
        return view('auth.{$n['singular']}-invitation-error', [
            'message' => \$message ?? 'This {$n['singular']} invitation cannot be accepted.',
            'invitation' => \$invitation,
        ]);

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function invalidInvitationResponseBody(array $n): string
    {
        if ($n['frontend'] !== 'blade') {
            return <<<'PHP'
            // @phpstan-ignore encapsedStringPart.nonString
            return $this->renderInvitationError(
                $invitation->isAccepted()
                    ? 'This invitation has already been accepted.'
                    : 'This invitation has expired.',
                $invitation
            )->toResponse(request())->setStatusCode(410);

PHP;
        }

        return <<<PHP
            return response()->view('auth.{$n['singular']}-invitation-error', [
                'message' => \$invitation->isAccepted()
                    ? 'This invitation has already been accepted.'
                    : 'This invitation has expired.',
                'invitation' => \$invitation,
            ], 410);

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function renderRegisterFormBody(array $n): string
    {
        if ($n['frontend'] !== 'blade') {
            $component = "{$n['inertiaDirectory']}/{$n['invitedRegisterPage']}";

            return <<<PHP
        return Inertia::render('{$component}', [
            'invitation' => \$this->invitationProps(\$invitation),
        ]);

PHP;
        }

        return <<<PHP
        return view('auth.{$n['singular']}-invited-register', ['invitation' => \$invitation]);

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function invitationNotificationControllerMethods(array $n): string
    {
        return <<<PHP
    /**
     * Create an invitation and send it to the invited email address.
     *
     * @param  Request  \$request  The incoming invite request.
     * @param  {$n['studly']}  \${$n['camel']}  The {$n['singular']} receiving a new member.
     * @return RedirectResponse
     */
    public function store(Request \$request, {$n['studly']} \${$n['camel']}): RedirectResponse
    {
        \$validated = \$request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'string'],
        ]);

        \$invitation = \${$n['camel']}->invitations()->create([
            'email' => \$validated['email'],
            'role' => \$validated['role'],
        ]);

        \$this->sendInvitation(\$invitation);

        return back()->with('status', '{$n['studly']} invitation sent.');
    }

    /**
     * Send the generic invitation notification.
     *
     * @param  {$n['studly']}Invitation  \$invitation  The invitation to send.
     */
    private function sendInvitation({$n['studly']}Invitation \$invitation): void
    {
        Notification::route('mail', \$invitation->email)
            ->notify(new {$n['studly']}InvitationNotification(\$invitation));
    }

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function routes(array $n): string
    {
        $storeRoute = $n['notifications']
            ? "Route::post('{{$n['singular']}:slug}/invitations', [{$n['studly']}InvitationController::class, 'store'])->middleware(['auth', '{$n['singular']}:Admin'])->name('{$n['singular']}.invitations.store');\n"
            : '';

        return <<<PHP
<?php

use App\Http\Controllers\Auth\\{$n['studly']}InvitationController;
use Illuminate\Support\Facades\Route;

{$storeRoute}Route::get('invitations/{invitation:code}', [{$n['studly']}InvitationController::class, 'show'])->name('{$n['singular']}.invitations.show');
Route::post('invitations/{invitation:code}/accept', [{$n['studly']}InvitationController::class, 'accept'])->middleware('auth')->name('{$n['singular']}.invitations.accept');
Route::get('invitations/{invitation:code}/register', [{$n['studly']}InvitationController::class, 'registerForm'])->name('{$n['singular']}.invitations.register');
Route::post('invitations/{invitation:code}/register', [{$n['studly']}InvitationController::class, 'register'])->name('{$n['singular']}.invitations.register.store');

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function invitationNotification(array $n): string
    {
        return <<<PHP
<?php

namespace App\Notifications;

use App\Models\\{$n['studly']}Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class {$n['studly']}InvitationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly {$n['studly']}Invitation \$invitation)
    {
        //
    }

    /**
     * Get the notification channels.
     *
     * @return array<int, string>
     */
    public function via(object \$notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object \$notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been invited to '.\$this->invitation->{$n['camel']}->name)
            ->line('You have been invited to join '.\$this->invitation->{$n['camel']}->name.'.')
            ->action('Accept invitation', route('{$n['singular']}.invitations.show', \$this->invitation))
            ->line('This invitation expires on '.\$this->invitation->expires_at?->toFormattedDateString().'.');
    }
}

PHP;
    }

    /** @param  array<string, string|bool>  $n */
    private function errorView(array $n): string
    {
        return <<<BLADE
<x-guest-layout>
    <div class="mx-auto flex min-h-screen w-full max-w-md items-center px-6 py-12">
        <div class="w-full rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
            <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-red-600">
                <span class="text-xl font-semibold">!</span>
            </div>

            <h1 class="text-2xl font-semibold text-gray-950">{$n['studly']} invitation</h1>
            <p class="mt-3 text-sm leading-6 text-gray-600">
                {{ \$message ?? 'This {$n['singular']} invitation cannot be accepted.' }}
            </p>

            <div class="mt-6 rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700">
                {{ \$invitation->email }}
            </div>
        </div>
    </div>
</x-guest-layout>

BLADE;
    }

    /** @param  array<string, string|bool>  $n */
    private function registerView(array $n): string
    {
        return <<<BLADE
<x-guest-layout>
    <div class="mx-auto flex min-h-screen w-full max-w-md items-center px-6 py-12">
        <div class="w-full rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
            <div class="mb-8">
                <p class="text-sm font-medium text-gray-500">Invitation for {{ \$invitation->email }}</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-950">Create your account</h1>
                <p class="mt-3 text-sm leading-6 text-gray-600">Join {{ \$invitation->{$n['camel']}->name }}.</p>
            </div>

            <form method="POST" action="{{ route('{$n['singular']}.invitations.register.store', \$invitation) }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input id="email" type="email" value="{{ \$invitation->email }}" disabled class="mt-2 block w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-gray-500 shadow-sm">
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-950 shadow-sm focus:border-gray-950 focus:outline-none focus:ring-1 focus:ring-gray-950">
                    @error('name')<p class="mt-2 text-sm text-red-600">{{ \$message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-950 shadow-sm focus:border-gray-950 focus:outline-none focus:ring-1 focus:ring-gray-950">
                    @error('password')<p class="mt-2 text-sm text-red-600">{{ \$message }}</p>@enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-950 shadow-sm focus:border-gray-950 focus:outline-none focus:ring-1 focus:ring-gray-950">
                </div>

                <button type="submit" class="w-full rounded-md bg-gray-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-950 focus:ring-offset-2">Create account</button>
            </form>
        </div>
    </div>
</x-guest-layout>

BLADE;
    }

    /** @param  array<string, string|bool>  $n */
    private function reactErrorPage(array $n): string
    {
        return <<<TSX
type Invitation = {
    code: string
    email: string
    {$n['camel']}Name: string
}

type Props = {
    message: string
    invitation: Invitation
}

export default function {$n['studly']}InvitationError({ message, invitation }: Props) {
    return (
        <main className="flex min-h-screen items-center justify-center bg-gray-50 px-6 py-12">
            <section className="w-full max-w-md rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-xl font-semibold text-red-600">
                    !
                </div>

                <h1 className="text-2xl font-semibold text-gray-950">{$n['studly']} invitation</h1>
                <p className="mt-3 text-sm leading-6 text-gray-600">{message}</p>

                <div className="mt-6 rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700">
                    <p className="font-medium">{invitation.{$n['camel']}Name}</p>
                    <p className="mt-1 text-gray-500">{invitation.email}</p>
                </div>
            </section>
        </main>
    )
}

TSX;
    }

    /** @param  array<string, string|bool>  $n */
    private function reactRegisterPage(array $n): string
    {
        return <<<TSX
import { Form } from '@inertiajs/react'

type Invitation = {
    code: string
    email: string
    {$n['camel']}Name: string
}

type Props = {
    invitation: Invitation
}

export default function {$n['studly']}InvitedRegister({ invitation }: Props) {
    return (
        <main className="flex min-h-screen items-center justify-center bg-gray-50 px-6 py-12">
            <section className="w-full max-w-md rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
                <div className="mb-8">
                    <p className="text-sm font-medium text-gray-500">Invitation for {invitation.email}</p>
                    <h1 className="mt-2 text-2xl font-semibold text-gray-950">Create your account</h1>
                    <p className="mt-3 text-sm leading-6 text-gray-600">Join {invitation.{$n['camel']}Name}.</p>
                </div>

            <Form action={`/invitations/\${invitation.code}/register`} method="post">
                {({ errors, processing }) => (
                    <div className="space-y-5">
                        <div>
                            <label htmlFor="email" className="block text-sm font-medium text-gray-700">Email</label>
                            <input id="email" type="email" value={invitation.email} disabled className="mt-2 block w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-gray-500 shadow-sm" />
                        </div>

                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700">Name</label>
                            <input id="name" name="name" type="text" autoFocus required className="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-950 shadow-sm focus:border-gray-950 focus:outline-none focus:ring-1 focus:ring-gray-950" />
                            {errors.name && <p className="mt-2 text-sm text-red-600">{errors.name}</p>}
                        </div>

                        <div>
                            <label htmlFor="password" className="block text-sm font-medium text-gray-700">Password</label>
                            <input id="password" name="password" type="password" autoComplete="new-password" required className="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-950 shadow-sm focus:border-gray-950 focus:outline-none focus:ring-1 focus:ring-gray-950" />
                            {errors.password && <p className="mt-2 text-sm text-red-600">{errors.password}</p>}
                        </div>

                        <div>
                            <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700">Confirm password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" required className="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-950 shadow-sm focus:border-gray-950 focus:outline-none focus:ring-1 focus:ring-gray-950" />
                        </div>

                        <button type="submit" disabled={processing} className="w-full rounded-md bg-gray-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-70">
                            {processing ? 'Creating...' : 'Create account'}
                        </button>
                    </div>
                )}
            </Form>
            </section>
        </main>
    )
}

TSX;
    }

    /** @param  array<string, string|bool>  $n */
    private function vueErrorPage(array $n): string
    {
        return <<<VUE
<script setup lang="ts">
defineProps<{
    message: string
    invitation: {
        code: string
        email: string
        {$n['camel']}Name: string
    }
}>()
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-gray-50 px-6 py-12">
        <section class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
            <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-xl font-semibold text-red-600">
                !
            </div>

            <h1 class="text-2xl font-semibold text-gray-950">{$n['studly']} invitation</h1>
            <p class="mt-3 text-sm leading-6 text-gray-600">{{ message }}</p>

            <div class="mt-6 rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700">
                <p class="font-medium">{{ invitation.{$n['camel']}Name }}</p>
                <p class="mt-1 text-gray-500">{{ invitation.email }}</p>
            </div>
        </section>
    </main>
</template>

VUE;
    }

    /** @param  array<string, string|bool>  $n */
    private function vueRegisterPage(array $n): string
    {
        return <<<VUE
<script setup lang="ts">
import { Form } from '@inertiajs/vue3'

defineProps<{
    invitation: {
        code: string
        email: string
        {$n['camel']}Name: string
    }
}>()
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-gray-50 px-6 py-12">
        <section class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
            <div class="mb-8">
                <p class="text-sm font-medium text-gray-500">Invitation for {{ invitation.email }}</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-950">Create your account</h1>
                <p class="mt-3 text-sm leading-6 text-gray-600">Join {{ invitation.{$n['camel']}Name }}.</p>
            </div>

        <Form :action="`/invitations/\${invitation.code}/register`" method="post" v-slot="{ errors, processing }">
            <div class="space-y-5">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input id="email" type="email" :value="invitation.email" disabled class="mt-2 block w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-gray-500 shadow-sm">
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input id="name" name="name" type="text" required autofocus class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-950 shadow-sm focus:border-gray-950 focus:outline-none focus:ring-1 focus:ring-gray-950">
                    <p v-if="errors.name" class="mt-2 text-sm text-red-600">{{ errors.name }}</p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-950 shadow-sm focus:border-gray-950 focus:outline-none focus:ring-1 focus:ring-gray-950">
                    <p v-if="errors.password" class="mt-2 text-sm text-red-600">{{ errors.password }}</p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-950 shadow-sm focus:border-gray-950 focus:outline-none focus:ring-1 focus:ring-gray-950">
                </div>

                <button type="submit" :disabled="processing" class="w-full rounded-md bg-gray-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-70">
                    {{ processing ? 'Creating...' : 'Create account' }}
                </button>
            </div>
        </Form>
        </section>
    </main>
</template>

VUE;
    }

    /** @param  array<string, string|bool>  $n */
    private function svelteErrorPage(array $n): string
    {
        return <<<SVELTE
<script lang="ts">
let { message, invitation }: {
    message: string
    invitation: {
        code: string
        email: string
        {$n['camel']}Name: string
    }
} = \$props()
</script>

<main class="flex min-h-screen items-center justify-center bg-gray-50 px-6 py-12">
    <section class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
        <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-full bg-red-50 text-xl font-semibold text-red-600">
            !
        </div>

        <h1 class="text-2xl font-semibold text-gray-950">{$n['studly']} invitation</h1>
        <p class="mt-3 text-sm leading-6 text-gray-600">{message}</p>

        <div class="mt-6 rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700">
            <p class="font-medium">{invitation.{$n['camel']}Name}</p>
            <p class="mt-1 text-gray-500">{invitation.email}</p>
        </div>
    </section>
</main>

SVELTE;
    }

    /** @param  array<string, string|bool>  $n */
    private function svelteRegisterPage(array $n): string
    {
        return <<<SVELTE
<script lang="ts">
import { Form } from '@inertiajs/svelte'

let { invitation }: {
    invitation: {
        code: string
        email: string
        {$n['camel']}Name: string
    }
} = \$props()
</script>

<main class="flex min-h-screen items-center justify-center bg-gray-50 px-6 py-12">
    <section class="w-full max-w-md rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
        <div class="mb-8">
            <p class="text-sm font-medium text-gray-500">Invitation for {invitation.email}</p>
            <h1 class="mt-2 text-2xl font-semibold text-gray-950">Create your account</h1>
            <p class="mt-3 text-sm leading-6 text-gray-600">Join {invitation.{$n['camel']}Name}.</p>
        </div>

    <Form action={`/invitations/\${invitation.code}/register`} method="post">
        {#snippet children({ errors, processing })}
            <div class="space-y-5">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input id="email" type="email" value={invitation.email} disabled class="mt-2 block w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-gray-500 shadow-sm">
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input id="name" name="name" type="text" required autofocus class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-950 shadow-sm focus:border-gray-950 focus:outline-none focus:ring-1 focus:ring-gray-950">
                    {#if errors.name}<p class="mt-2 text-sm text-red-600">{errors.name}</p>{/if}
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-950 shadow-sm focus:border-gray-950 focus:outline-none focus:ring-1 focus:ring-gray-950">
                    {#if errors.password}<p class="mt-2 text-sm text-red-600">{errors.password}</p>{/if}
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-950 shadow-sm focus:border-gray-950 focus:outline-none focus:ring-1 focus:ring-gray-950">
                </div>

                <button type="submit" disabled={processing} class="w-full rounded-md bg-gray-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-70">
                    {processing ? 'Creating...' : 'Create account'}
                </button>
            </div>
        {/snippet}
    </Form>
    </section>
</main>

SVELTE;
    }
}
