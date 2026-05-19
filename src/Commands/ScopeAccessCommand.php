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
     * @return array<string, string>
     */
    private function resolveNames(): array
    {
        $base = Str::of($this->option('name') ?: $this->ask('What should the group be called?', 'team'))
            ->trim()
            ->lower()
            ->snake()
            ->toString();

        $singular = Str::of($this->option('singular') ?: $base)->trim()->lower()->snake()->toString();
        $plural = Str::of($this->option('plural') ?: Str::plural($singular))->trim()->lower()->snake()->toString();
        $frontend = $this->resolveFrontend();

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
            'inertiaDirectory' => 'Auth',
            'invitationErrorPage' => Str::studly($singular).'InvitationError',
            'invitedRegisterPage' => Str::studly($singular).'InvitedRegister',
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

        $frontend = Str::of($frontend ?: 'blade')->trim()->lower()->toString();

        if (! in_array($frontend, ['blade', 'react', 'vue', 'svelte'], true)) {
            $this->warn("Unsupported frontend [{$frontend}], falling back to blade.");

            return 'blade';
        }

        return $frontend;
    }

    /**
     * @param  array<string, string>  $names
     */
    private function confirmNames(array $names): bool
    {
        $this->line("Singular: {$names['singular']}  |  Plural: {$names['plural']}  |  Table: {$names['table']}");

        return $this->confirm('Confirm?', true);
    }

    /**
     * @param  array<string, string>  $names
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
     * @param  array<string, string>  $names
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
            app_path("Enums/{$studly}Permission.php") => $this->permissionEnum($names),
            app_path("Http/Controllers/Auth/{$studly}InvitationController.php") => $this->invitationController($names),
            base_path("routes/{$names['singular']}-invitations.php") => $this->routes($names),
        ];

        return $files + $this->invitationUiFiles($names);
    }

    /**
     * @param  array<string, string>  $names
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
     * @param  array<string, string>  $names
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
        $permissionEnum = "\\App\\Enums\\{$names['studly']}Permission::class";
        $contents = preg_replace(
            "/    'permission_enum' => null,\\R/",
            "    'permission_enum' => {$permissionEnum},\n",
            $contents,
            1
        ) ?? $contents;

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
     * @param  array<string, string>  $names
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
     * @param  array<string, string>  $names
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
     * @param  array<string, string>  $names
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

        $contents = preg_replace('/namespace App\\\Models;\\R\\R/', "namespace App\\Models;\n\nuse App\\Concerns\\{$trait};\n", $contents, 1) ?? $contents;
        $contents = preg_replace('/use ([^;]+);\\R\\{/', "use $1;\n    use {$trait};\n{", $contents, 1) ?? $contents;
        $files->put($path, $contents);
        $published[] = 'Updated app/Models/User.php';
    }

    private function relativePath(string $path): string
    {
        return Str::of($path)->replace(base_path(DIRECTORY_SEPARATOR), '')->replace('\\', '/')->toString();
    }

    /**
     * @param  array<string, string>  $n
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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function users(): BelongsToMany
    {
        return \$this->belongsToMany(User::class, '{$n['membersTable']}')
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return \$this->hasMany({$n['studly']}Invitation::class);
    }
}

PHP;
    }

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

    protected static function booted(): void
    {
        static::creating(function (self \$invitation): void {
            \$invitation->code ??= Str::random(64);
            \$invitation->expires_at ??= now()->addDays(config('access.invitations.expiry_days', 7));
        });
    }

    public function {$n['camel']}(): BelongsTo
    {
        return \$this->belongsTo({$n['studly']}::class);
    }

    public function isExpired(): bool
    {
        return \$this->expires_at !== null && \$this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return \$this->accepted_at !== null;
    }
}

PHP;
    }

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
    public function {$n['plural']}(): BelongsToMany
    {
        return \$this->belongsToMany({$n['studly']}::class, '{$n['membersTable']}')
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function {$n['camel']}(): BelongsTo
    {
        return \$this->belongsTo({$n['studly']}::class, '{$n['currentColumn']}');
    }

    public function belongsTo{$n['studly']}({$n['studly']} \$scope): bool
    {
        return \$this->{$n['plural']}()->whereKey(\$scope->getKey())->exists();
    }

    public function switch{$n['studly']}({$n['studly']} \$scope): bool
    {
        if (! \$this->belongsTo{$n['studly']}(\$scope)) {
            return false;
        }

        return \$this->forceFill(['{$n['currentColumn']}' => \$scope->getKey()])->save();
    }

    public function isCurrent{$n['studly']}({$n['studly']} \$scope): bool
    {
        return (int) \$this->{$n['currentColumn']} === (int) \$scope->getKey();
    }

    public function {$n['camel']}Role({$n['studly']} \$scope): ?{$n['studly']}Role
    {
        \$membership = \$this->{$n['plural']}()->whereKey(\$scope->getKey())->first()?->pivot;

        return \$membership?->role;
    }
}

PHP;
    }

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
    public function handle(Request \$request, Closure \$next, ?string \$minimumRole = null): Response
    {
        \$user = \$request->user();
        \$scope = \$request->route('{$n['currentRouteKey']}') ?: \$request->route('{$n['singular']}');

        abort_if(! \$user || ! \$scope || ! \$user->belongsTo{$n['studly']}(\$scope), 403);

        if (\$minimumRole !== null) {
            \$role = \$user->{$n['camel']}Role(\$scope);
            abort_if(! \$role || \$role->level() < {$n['studly']}Role::from(\$minimumRole)->level(), 403);
        }

        if (\$request->route('{$n['currentRouteKey']}') && ! \$user->isCurrent{$n['studly']}(\$scope)) {
            \$user->switch{$n['studly']}(\$scope);
        }

        return \$next(\$request);
    }
}

PHP;
    }

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

    private function permissionEnum(array $n): string
    {
        return <<<PHP
<?php

namespace App\Enums;

enum {$n['studly']}Permission: string
{
    case MembersView = '{$n['singular']}.members.view';
    case MembersInvite = '{$n['singular']}.members.invite';
    case MembersManage = '{$n['singular']}.members.manage';
    case SettingsManage = '{$n['singular']}.settings.manage';
}

PHP;
    }

    private function invitationController(array $n): string
    {
        $inertiaImports = $n['frontend'] !== 'blade'
            ? "use Inertia\\Inertia;\nuse Inertia\\Response as InertiaResponse;\n"
            : '';
        $responseType = $n['frontend'] !== 'blade'
            ? 'View|InertiaResponse|RedirectResponse|SymfonyResponse'
            : 'View|RedirectResponse|SymfonyResponse';
        $renderType = $n['frontend'] !== 'blade' ? 'InertiaResponse' : 'View';

        return <<<PHP
<?php

namespace App\Http\Controllers\Auth;

use App\Models\\{$n['studly']}Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
{$inertiaImports}

class {$n['studly']}InvitationController extends Controller
{
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

    public function registerForm({$n['studly']}Invitation \$invitation): {$responseType}
    {
        if (\$response = \$this->invalidInvitationResponse(\$invitation)) {
            return \$response;
        }

        return \$this->renderRegisterForm(\$invitation);
    }

    private function renderInvitationError(?string \$message, {$n['studly']}Invitation \$invitation): {$renderType}
    {
PHP
            .$this->renderInvitationErrorBody($n).
            <<<PHP
    }

    private function renderRegisterForm({$n['studly']}Invitation \$invitation): {$renderType}
    {
PHP
            .$this->renderRegisterFormBody($n).
            <<<PHP
    }

    private function invitationProps({$n['studly']}Invitation \$invitation): array
    {
        return [
            'code' => \$invitation->code,
            'email' => \$invitation->email,
        ];
    }

PHP
            .<<<'PHP'
    public function accept(Request $request, 
PHP
            ."{$n['studly']}Invitation \$invitation): RedirectResponse\n".
            <<<'PHP'
    {
        if ($response = $this->invalidInvitationResponse($invitation)) {
            return $response;
        }

        abort_if(! $request->user() || $request->user()->email !== $invitation->email, 403);

        $this->acceptInvitation($invitation, $request->user());

        return redirect()->route(config('access.invitations.redirect_after_accept', 'dashboard'));
    }

PHP
            .<<<'PHP'
    public function register(Request $request, 
PHP
            ."{$n['studly']}Invitation \$invitation): RedirectResponse\n".
            <<<'PHP'
    {
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

        $this->acceptInvitation($invitation, $user);
        Auth::login($user);

        return redirect()->route(config('access.invitations.redirect_after_accept', 'dashboard'));
    }

PHP
            .<<<'PHP'
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

    private function invalidInvitationResponseBody(array $n): string
    {
        if ($n['frontend'] !== 'blade') {
            return <<<'PHP'
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

    private function routes(array $n): string
    {
        return <<<PHP
<?php

use App\Http\Controllers\Auth\\{$n['studly']}InvitationController;
use Illuminate\Support\Facades\Route;

Route::get('invitations/{invitation:code}', [{$n['studly']}InvitationController::class, 'show'])->name('{$n['singular']}.invitations.show');
Route::post('invitations/{invitation:code}/accept', [{$n['studly']}InvitationController::class, 'accept'])->middleware('auth')->name('{$n['singular']}.invitations.accept');
Route::get('invitations/{invitation:code}/register', [{$n['studly']}InvitationController::class, 'registerForm'])->name('{$n['singular']}.invitations.register');
Route::post('invitations/{invitation:code}/register', [{$n['studly']}InvitationController::class, 'register'])->name('{$n['singular']}.invitations.register.store');

PHP;
    }

    private function errorView(array $n): string
    {
        return <<<BLADE
<x-guest-layout>
    <div>
        {{ \$message ?? 'This {$n['singular']} invitation cannot be accepted.' }}
    </div>
</x-guest-layout>

BLADE;
    }

    private function registerView(array $n): string
    {
        return <<<BLADE
<x-guest-layout>
    <form method="POST" action="{{ route('{$n['singular']}.invitations.register.store', \$invitation) }}">
        @csrf

        <input type="email" name="email" value="{{ \$invitation->email }}" disabled>
        <input type="text" name="name" value="{{ old('name') }}" required autofocus>
        <input type="password" name="password" required autocomplete="new-password">
        <input type="password" name="password_confirmation" required autocomplete="new-password">

        <button type="submit">Create account</button>
    </form>
</x-guest-layout>

BLADE;
    }

    private function reactErrorPage(array $n): string
    {
        return <<<TSX
type Invitation = {
    code: string
    email: string
}

type Props = {
    message: string
    invitation: Invitation
}

export default function {$n['studly']}InvitationError({ message, invitation }: Props) {
    return (
        <main>
            <h1>{$n['studly']} invitation</h1>
            <p>{message}</p>
            <p>{invitation.email}</p>
        </main>
    )
}

TSX;
    }

    private function reactRegisterPage(array $n): string
    {
        return <<<TSX
import { Form } from '@inertiajs/react'

type Invitation = {
    code: string
    email: string
}

type Props = {
    invitation: Invitation
}

export default function {$n['studly']}InvitedRegister({ invitation }: Props) {
    return (
        <main>
            <h1>Create your account</h1>

            <Form action={`/invitations/\${invitation.code}/register`} method="post">
                {({ errors, processing }) => (
                    <>
                        <label htmlFor="email">Email</label>
                        <input id="email" type="email" value={invitation.email} disabled />

                        <label htmlFor="name">Name</label>
                        <input id="name" name="name" type="text" autoFocus required />
                        {errors.name && <p>{errors.name}</p>}

                        <label htmlFor="password">Password</label>
                        <input id="password" name="password" type="password" autoComplete="new-password" required />
                        {errors.password && <p>{errors.password}</p>}

                        <label htmlFor="password_confirmation">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" required />

                        <button type="submit" disabled={processing}>
                            {processing ? 'Creating...' : 'Create account'}
                        </button>
                    </>
                )}
            </Form>
        </main>
    )
}

TSX;
    }

    private function vueErrorPage(array $n): string
    {
        return <<<VUE
<script setup lang="ts">
defineProps<{
    message: string
    invitation: {
        code: string
        email: string
    }
}>()
</script>

<template>
    <main>
        <h1>{$n['studly']} invitation</h1>
        <p>{{ message }}</p>
        <p>{{ invitation.email }}</p>
    </main>
</template>

VUE;
    }

    private function vueRegisterPage(array $n): string
    {
        return <<<'VUE'
<script setup lang="ts">
import { Form } from '@inertiajs/vue3'

defineProps<{
    invitation: {
        code: string
        email: string
    }
}>()
</script>

<template>
    <main>
        <h1>Create your account</h1>

        <Form :action="`/invitations/${invitation.code}/register`" method="post" v-slot="{ errors, processing }">
            <label for="email">Email</label>
            <input id="email" type="email" :value="invitation.email" disabled>

            <label for="name">Name</label>
            <input id="name" name="name" type="text" required autofocus>
            <p v-if="errors.name">{{ errors.name }}</p>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
            <p v-if="errors.password">{{ errors.password }}</p>

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

            <button type="submit" :disabled="processing">
                {{ processing ? 'Creating...' : 'Create account' }}
            </button>
        </Form>
    </main>
</template>

VUE;
    }

    private function svelteErrorPage(array $n): string
    {
        return <<<SVELTE
<script lang="ts">
let { message, invitation }: {
    message: string
    invitation: {
        code: string
        email: string
    }
} = \$props()
</script>

<main>
    <h1>{$n['studly']} invitation</h1>
    <p>{message}</p>
    <p>{invitation.email}</p>
</main>

SVELTE;
    }

    private function svelteRegisterPage(array $n): string
    {
        return <<<'SVELTE'
<script lang="ts">
import { Form } from '@inertiajs/svelte'

let { invitation }: {
    invitation: {
        code: string
        email: string
    }
} = $props()
</script>

<main>
    <h1>Create your account</h1>

    <Form action={`/invitations/${invitation.code}/register`} method="post">
        {#snippet children({ errors, processing })}
            <label for="email">Email</label>
            <input id="email" type="email" value={invitation.email} disabled>

            <label for="name">Name</label>
            <input id="name" name="name" type="text" required autofocus>
            {#if errors.name}<p>{errors.name}</p>{/if}

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
            {#if errors.password}<p>{errors.password}</p>{/if}

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

            <button type="submit" disabled={processing}>
                {processing ? 'Creating...' : 'Create account'}
            </button>
        {/snippet}
    </Form>
</main>

SVELTE;
    }
}
