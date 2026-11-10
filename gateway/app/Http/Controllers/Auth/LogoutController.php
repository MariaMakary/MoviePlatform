<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthSession;
use Illuminate\Http\RedirectResponse;

class LogoutController extends Controller
{
    public function __invoke(AuthSession $auth): RedirectResponse
    {
        $auth->end();

        return redirect()->route('home')->with('success', 'You are logged out.');
    }
}
