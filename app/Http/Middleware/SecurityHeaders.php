<?php

namespace App\Http\Middleware;

use App\Support\Csp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for every response: strict Content-Security-Policy with a per-request nonce, HSTS on secure
 * requests, clickjacking and MIME-sniffing protection, and no browser caching of signed-in pages (shared devices).
 */
class SecurityHeaders
{
    private const PUBLIC_ROUTES = ['home', 'situation', 'about', 'accessibility', 'privacy', 'share', 'share.png', 'sitemap', 'robots', 'login'];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $nonce = app(Csp::class)->nonce();

        $connect = ["'self'"];
        if (config('surveillance.realtime') && config('broadcasting.default') === 'reverb') {
            $host = config('surveillance.reverb.host') ?: $request->getHost();
            $port = (int) config('surveillance.reverb.port');
            $ws = config('surveillance.reverb.scheme') === 'https' ? 'wss' : 'ws';
            $connect[] = "$ws://$host:$port";
            if ($host !== $request->getHost()) {
                $connect[] = "$ws://{$request->getHost()}:$port";
            }
        }
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-$nonce'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            'connect-src '.implode(' ', $connect),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure() && config('surveillance.hsts', true)) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $public = in_array($request->route()?->getName(), self::PUBLIC_ROUTES, true);
        if (! $public) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
