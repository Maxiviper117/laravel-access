<?php

namespace Maxiviper117\Access\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Maxiviper117\Access\Access;
use Maxiviper117\Access\Models\Assignment;

class DebugAccessCommand extends Command
{
    protected $signature = 'access:debug {user} {--scope=}';

    protected $description = 'Show roles and permissions for a user and optional scope.';

    public function handle(): int
    {
        $user = $this->findUser($this->argument('user'));

        if (! $user instanceof Model) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $scope = $this->scope();
        $context = app(Access::class)->for($user);
        $context = $scope instanceof Model ? $context->in($scope) : $context;
        $assignments = $this->assignments($user, $scope);

        $this->line('User: '.$this->label($user));
        $scopeLabel = $scope instanceof Model ? class_basename($scope).' #'.(is_scalar($scope->getKey()) || is_null($scope->getKey()) ? (string) $scope->getKey() : '') : 'global';
        $this->line('Scope: '.$scopeLabel);
        $this->newLine();
        $this->line('Roles:');
        $roles = $assignments->pluck('role.name')->filter()->map(fn ($role): string => is_string($role) ? $role : '');
        $roles->each(fn (string $role) => $this->line('- '.$role));
        $this->line($assignments->pluck('role.name')->filter()->isEmpty() ? '- none' : '');
        $this->newLine();
        $this->line('Permissions:');
        collect($context->permissions())->each(fn (string $permission) => $this->line('- '.$permission));

        return self::SUCCESS;
    }

    /** @param mixed $value */
    private function findUser(mixed $value): ?Model
    {
        if (is_array($value) || is_object($value)) {
            return null;
        }

        if (! is_scalar($value) && ! is_null($value)) {
            return null;
        }

        $value = (string) $value;
        $class = config('access.user_model');

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            return null;
        }

        $query = $class::query();

        return filter_var($value, FILTER_VALIDATE_EMAIL)
            ? $query->where('email', $value)->first()
            : $query->find($value);
    }

    private function scope(): ?Model
    {
        $scope = $this->option('scope');

        if (! is_string($scope) || $scope === '') {
            return null;
        }

        [, $id] = array_pad(explode(':', $scope, 2), 2, null);
        $class = config('access.default_scope_model');

        if (! is_string($class) || ! is_a($class, Model::class, true) || ! $id) {
            return null;
        }

        return $class::query()->find($id);
    }

    /** @return Collection<int, Assignment> */
    private function assignments(Model $user, ?Model $scope): Collection
    {
        $query = Assignment::query()
            ->where('actor_type', $user->getMorphClass())
            ->where('actor_id', $user->getKey())
            ->with('role');

        $scope instanceof Model
            ? $query->where('scope_type', $scope->getMorphClass())->where('scope_id', $scope->getKey())
            : $query->whereNull('scope_type')->whereNull('scope_id');

        return $query->get();
    }

    private function label(Model $user): string
    {
        $name = $user->getAttribute('name');
        $email = $user->getAttribute('email');
        $key = $user->getKey();

        return trim((is_string($name) ? $name : class_basename($user)).' <'.(is_string($email) ? $email : (is_scalar($key) || is_null($key) ? (string) $key : '')).'>');
    }
}
