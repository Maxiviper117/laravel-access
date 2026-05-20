<?php

use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config()->set('cache.default', 'array');
    config()->set('access.cache.enabled', true);
    config()->set('access.cache.key', 'access.permissions');
    Cache::flush();
});

it('clears access cache successfully and outputs success message', function () {
    // Assert cache version starts at null (not set yet)
    expect(Cache::get('access.permissions:version'))->toBeNull();

    // Call access:clear
    $this->artisan('access:clear')
        ->expectsOutput('Access cache cleared.')
        ->assertSuccessful();

    // Assert cache version is now incremented/initialized
    expect(Cache::get('access.permissions:version'))->toBe(2);
});
