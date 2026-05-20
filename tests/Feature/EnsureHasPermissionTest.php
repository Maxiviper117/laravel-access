<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Maxiviper117\Access\Middleware\EnsureHasPermission;
use Maxiviper117\Access\Tests\Fixtures\Company;
use Maxiviper117\Access\Tests\Fixtures\Permission;
use Maxiviper117\Access\Tests\Fixtures\User;

beforeEach(function () {
    Route::middleware([
        SubstituteBindings::class,
        EnsureHasPermission::class.':users.invite,company',
    ])->get('/companies/{company}/invite', function (Company $company) {
        return 'success';
    });

    Route::middleware([
        SubstituteBindings::class,
        EnsureHasPermission::class.':users.invite',
    ])->get('/companies-no-scope/invite', function () {
        return 'success';
    });
});

it('allows request when user has permission in scope', function () {
    $user = User::query()->create(['email' => 'test@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->givePermission(Permission::UsersInvite);

    $this->actingAs($user)
        ->get("/companies/{$company->id}/invite")
        ->assertOk()
        ->assertSee('success');
});

it('aborts 403 when user is not authenticated', function () {
    $company = Company::query()->create(['name' => 'Acme']);

    $this->get("/companies/{$company->id}/invite")
        ->assertStatus(403);
});

it('aborts 403 when user does not have permission in scope', function () {
    $user = User::query()->create(['email' => 'test@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    // No permission given
    $this->actingAs($user)
        ->get("/companies/{$company->id}/invite")
        ->assertStatus(403);
});

it('aborts 403 when scope parameter is null or missing', function () {
    $user = User::query()->create(['email' => 'test@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->givePermission(Permission::UsersInvite);

    // This route does not specify a scope parameter, which causes middleware to fail check
    $this->actingAs($user)
        ->get('/companies-no-scope/invite')
        ->assertStatus(403);
});
