<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** In production every request must use HTTPS: plain HTTP is redirected before anything else is processed. */
class RedirectToHttps
{
    public function handle(Request $request, Closure $next)
    {
        if (config('surveillance.force_https') && ! $request->isSecure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
