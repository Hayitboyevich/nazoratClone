<?php

namespace App\Http\Middleware;

use App\Exceptions\UnauthenticatedException;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;


class Authenticate extends Middleware
{
    protected $user;
    protected $roleId;
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login');
    }

    protected function unauthenticated($request, array $guards)
    {
        throw new UnauthenticatedException('Unauthenticated.', 401);
    }


}
