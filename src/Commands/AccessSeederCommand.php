<?php

namespace Maxiviper117\Access\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class AccessSeederCommand extends Command
{
    protected $signature = 'access:seeder
        {--name= : Scope name to use, such as company, workspace, tenant, or team}
        {--singular= : Override singular form}
        {--plural= : Override plural form}
        {--class= : Seeder class name}
        {--force : Overwrite an existing seeder}';

    protected $description = 'Generate an editable starter seeder for Laravel Access assignments.';

    public function handle(Filesystem $files): int
    {
        $names = $this->resolveNames();
        $class = $this->resolveClassName($names);
        $path = database_path("seeders/{$class}.php");

        if ($files->exists($path) && ! $this->option('force')) {
            $this->warn("Seeder already exists: {$this->relativePath($path)}");
            $this->line('Use --force to overwrite it.');

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $this->seeder($class, $names));

        $this->info("Generated {$this->relativePath($path)}.");
        $this->line("Next steps: review the demo user, {$names['singular']}, and role names, then run php artisan access:sync before db:seed.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function resolveNames(): array
    {
        $nameOption = $this->option('name');
        $nameInput = is_string($nameOption) && $nameOption !== ''
            ? $nameOption
            : (is_string($config = config('access.teams.singular')) ? $config : 'team');
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

        return [
            'singular' => $singular,
            'plural' => $plural,
            'studly' => Str::studly($singular),
            'camel' => Str::camel($singular),
            'slug' => Str::slug(str_replace('_', ' ', $singular)).'-demo',
        ];
    }

    /**
     * @param  array<string, string>  $names
     */
    private function resolveClassName(array $names): string
    {
        $classOption = $this->option('class');

        if (is_string($classOption) && $classOption !== '') {
            return Str::of($classOption)
                ->trim()
                ->replaceEnd('.php', '')
                ->studly()
                ->toString();
        }

        return "{$names['studly']}AccessSeeder";
    }

    private function relativePath(string $path): string
    {
        return Str::of($path)->replace(base_path(DIRECTORY_SEPARATOR), '')->replace('\\', '/')->toString();
    }

    /**
     * @param  array<string, string>  $n
     */
    private function seeder(string $class, array $n): string
    {
        return <<<PHP
<?php

namespace Database\Seeders;

use App\Enums\\{$n['studly']}Role;
use App\Models\\{$n['studly']};
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class {$class} extends Seeder
{
    public function run(): void
    {
        // Replace these demo records with the users and {$n['plural']} your app should start with.
        \$owner = User::query()->firstOrCreate(
            ['email' => 'owner@example.com'],
            [
                'name' => 'Owner',
                'password' => Hash::make('password'),
            ],
        );

        \${$n['camel']} = {$n['studly']}::query()->firstOrCreate(
            ['slug' => '{$n['slug']}'],
            ['name' => '{$n['studly']} Demo'],
        );

        // This writes your app-owned {$n['singular']} membership state.
        \${$n['camel']}->users()->syncWithoutDetaching([
            \$owner->getKey() => ['role' => {$n['studly']}Role::Owner->value],
        ]);

        // This separately assigns the scoped Laravel Access role and selects the user's current {$n['singular']}.
        \$owner->in(\${$n['camel']})->assignRole({$n['studly']}Role::Owner);
        \$owner->switch{$n['studly']}(\${$n['camel']});
    }
}

PHP;
    }
}
