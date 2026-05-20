<?php

namespace Maxiviper117\Access\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Maxiviper117\Access\Access;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasPermission
{
    public function handle(Request $request, Closure $next, string $permission, ?string $scopeParameter = null): Response
    {
        $user = $request->user();
        $scope = $scopeParameter ? $request->route($scopeParameter) : null;

        abort_unless($user && $scope instanceof Model && app(Access::class)->for($user)->in($scope)->can($permission), 403);

        $response = $next($request);

        if (! $response instanceof Response) {
            abort(500);
        }

        return $response;
    }
}
