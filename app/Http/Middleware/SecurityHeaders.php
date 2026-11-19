<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Write the configured security headers into the response and drop the framework banner.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('security_headers.enabled', true)) {
            return $response;
        }

        /** @var array<string, string> $headers */
        $headers = config('security_headers.headers', []);

        foreach ($headers as $name => $value) {
            if (is_string($value) && $value !== '') {
                $response->headers->set($name, $value);
            }
        }

        $response->headers->remove('X-Powered-By');

        // PHP adds its own banner while sending the response, so the ini level copy is removed too.
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
