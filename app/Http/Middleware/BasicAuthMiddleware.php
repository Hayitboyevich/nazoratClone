<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $users = collect(config('authbasic.users'));
        $valid = $users->contains(function ($user) use ($request) {
            return $user['username'] === $request->getUser()
                && $user['password'] === $request->getPassword();
        });

        if ($valid) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'You shall not pass!',
        ], 401,[
            'WWW-Authenticate' => 'Basic',
        ]);
    }
}
