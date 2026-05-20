<?php

namespace Maxiviper117\Access\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Maxiviper117\Access\Access;
use Maxiviper117\Access\Models\Assignment;

class DebugAccessCommand extends Command
{
    protected $signature = 'access:debug {user} {--scope=}';

    protected $description = 'Show roles and permissions for a user and optional scope.';

    public function handle(): int
    {
        $user = $this->findUser((string) $this->argument('user'));

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $scope = $this->scope();
        $context = app(Access::class)->for($user);
        $context = $scope ? $context->in($scope) : $context;
        $assignments = $this->assignments($user, $scope);

        $this->line('User: '.$this->label($user));
        $this->line('Scope: '.($scope ? class_basename($scope).' #'.$scope->getKey() : 'global'));
        $this->newLine();
        $this->line('Roles:');
        $assignments->pluck('role.name')->filter()->each(fn (string $role) => $this->line('- '.$role));
        $this->line($assignments->pluck('role.name')->filter()->isEmpty() ? '- none' : '');
        $this->newLine();
        $this->line('Permissions:');
        collect($context->permissions())->each(fn (string $permission) => $this->line('- '.$permission));

        return self::SUCCESS;
    }

    private function findUser(string $value): ?Model
    {
        $class = config('access.user_model');
        $query = $class::query();

        return filter_var($value, FILTER_VALIDATE_EMAIL)
            ? $query->where('email', $value)->first()
            : $query->find($value);
    }

    private function scope(): ?Model
    {
        $scope = $this->option('scope');

        if (! $scope) {
            return null;
        }

        [, $id] = array_pad(explode(':', $scope, 2), 2, null);
        $class = config('access.default_scope_model');

        if (! $class || ! $id) {
            return null;
        }

        return $class::query()->find($id);
    }

    private function assignments(Model $user, ?Model $scope)
    {
        $query = Assignment::query()
            ->where('actor_type', $user->getMorphClass())
            ->where('actor_id', $user->getKey())
            ->with('role');

        $scope
            ? $query->where('scope_type', $scope->getMorphClass())->where('scope_id', $scope->getKey())
            : $query->whereNull('scope_type')->whereNull('scope_id');

        return $query->get();
    }

    private function label(Model $user): string
    {
        return trim(($user->name ?? class_basename($user)).' <'.($user->email ?? $user->getKey()).'>');
    }
}
