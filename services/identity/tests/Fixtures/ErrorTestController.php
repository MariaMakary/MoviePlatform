<?php

namespace Tests\Fixtures;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ErrorTestController extends Controller
{
    public function store(Request $request)
    {
        return $this->validate($request, ['email' => ['required', 'email'], 'password' => ['required', 'min:8']]);
    }

    public function update()
    {
        $this->authorize('update-fixture');
    }
}
