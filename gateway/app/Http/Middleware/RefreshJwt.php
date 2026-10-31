<?php

namespace App\Http\Middleware;

use App\Exceptions\ServiceRequestException;
use App\Exceptions\ServiceUnavailable;
use App\Services\AuthSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshJwt
{
    public function __construct(private AuthSession $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->auth->check() && $this->auth->expiresSoon()) {
            try {
                $this->auth->refresh();
            } catch (ServiceUnavailable) {
            } catch (ServiceRequestException $e) {
                $this->auth->end();

                return redirect()->route('login')->with('error', $e->getStatusCode() === 403
                    ? 'Your account is blocked.'
                    : 'Your session has ended. Please log in again.');
            }
        }

        return $next($request);
    }
}
