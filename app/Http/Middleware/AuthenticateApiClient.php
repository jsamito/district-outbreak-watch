<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;

/** Bearer-token authentication for facility systems that submit notifications. */
class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $client = $token ? ApiClient::findByToken($token) : null;
        if (! $client) {
            return response()->json(['error' => 'unauthenticated', 'message' => 'A valid API token is required.'], 401);
        }
        $client->forceFill(['last_seen_at' => now()])->save();
        $request->attributes->set('api_client', $client);

        return $next($request);
    }
}
