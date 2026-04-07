<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredApiKey = (string) config('signature.api_key', '');
        $providedApiKey = (string) $request->header('X-API-KEY', '');

        if ($configuredApiKey === '') {
            return new JsonResponse([
                'message' => 'The signature API key is not configured.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (! hash_equals($configuredApiKey, $providedApiKey)) {
            return new JsonResponse([
                'message' => 'Invalid API key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
