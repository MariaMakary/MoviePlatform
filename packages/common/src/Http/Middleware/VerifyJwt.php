<?php

namespace MoviePlatform\Common\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifyJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('jwt')->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Auth::shouldUse('jwt');

        return $next($request);
    }
}
