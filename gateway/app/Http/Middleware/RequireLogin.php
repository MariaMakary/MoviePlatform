<?php

namespace App\Http\Middleware;

use App\Services\AuthSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireLogin
{
    public function __construct(private AuthSession $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->auth->check()) {
            return redirect()->guest(route('login'))->with('info', 'Log in to continue.');
        }

        return $next($request);
    }
}
