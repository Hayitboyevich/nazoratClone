<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ogoh fuqaro tizimidan keladigan so'rovlar: Authorization: Bearer <OGOHFUQARO_TOKEN>
 */
class OgohFuqaroAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string)config('services.ogohfuqaro.token');

        if ($token === '' || !hash_equals($token, (string)$request->bearerToken())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

//        $ips = array_filter(config('services.ogohfuqaro.ips', []));
//        if ($ips && !in_array($request->ip(), $ips, true)) {
//            return response()->json([
//                'success' => false,
//                'message' => 'Forbidden IP: ' . $request->ip(),
//            ], 403);
//        }

        return $next($request);
    }
}
