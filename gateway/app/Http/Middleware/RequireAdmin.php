<?php

namespace App\Http\Middleware;

use App\Services\AuthSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdmin
{
    public function __construct(private AuthSession $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->auth->isAdmin(), 403, 'This page is for admins only.');

        return $next($request);
    }
}
