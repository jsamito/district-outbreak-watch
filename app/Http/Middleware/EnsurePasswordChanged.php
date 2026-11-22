<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** A person who signed in with a temporary password must choose their own before using anything else. */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->must_change_password && ! $request->routeIs('password.*', 'logout')) {
            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
