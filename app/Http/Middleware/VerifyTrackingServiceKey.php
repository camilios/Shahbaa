<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTrackingServiceKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('tracking.service_secret', '');
        $provided = (string) $request->header('X-Tracking-Service-Key', '');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Tracking service authorization failed.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
