<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class PasswordResetController extends Controller
{
    public const LINK_SENT = 'If this email exists, we sent you a link.';

    public function __construct(private PasswordResetService $passwords) {}

    public function forgot(Request $request): JsonResponse
    {
        $data = $this->validate($request, ForgotPasswordRequest::rules());

        $this->passwords->sendResetLink($data['email']);

        return (new JsonResource(['message' => self::LINK_SENT]))->response();
    }

    public function reset(Request $request): Response
    {
        $data = $this->validate($request, ResetPasswordRequest::rules());

        $this->passwords->reset($data['email'], $data['token'], $data['password']);

        return response('', 204);
    }
}
