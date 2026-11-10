<?php

namespace App\Services;

use App\Mail\ResetPasswordMail;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetService
{
    public const TOKEN_TTL_MINUTES = 60;

    public function sendResetLink(string $email): void
    {
        $user = User::firstWhere('email', User::normalizeEmail($email));

        if ($user === null) {
            return;
        }

        $token = Str::random(64);

        PasswordResetToken::upsert(
            [['email' => $user->email, 'token' => Hash::make($token), 'created_at' => Carbon::now()]],
            ['email'],
            ['token', 'created_at'],
        );

        // Always the address saved in the database, never the request field (see services/identity/CLAUDE.md).
        try {
            Mail::to($user->email)->queue(new ResetPasswordMail($user->name, $this->resetUrl($user->email, $token)));
        } catch (TransportExceptionInterface $e) {
            // The answer stays the same whether a mail went out or not, so nobody can tell which emails exist.
            report($e);
        }
    }

    public function reset(string $email, string $token, string $password): void
    {
        $email = User::normalizeEmail($email);

        DB::transaction(function () use ($email, $token, $password) {
            $entry = PasswordResetToken::lockForUpdate()->find($email);
            $user = User::firstWhere('email', $email);

            $matches = Hash::check($token, $entry?->token ?? AuthService::DUMMY_HASH);

            if (! $matches || $user === null || $this->isExpired($entry)) {
                throw ValidationException::withMessages(['token' => ['This password reset link is invalid or has expired.']]);
            }

            $user->update(['password' => $password]);
            $entry->delete();
        });
    }

    private function isExpired(PasswordResetToken $entry): bool
    {
        return $entry->created_at === null
            || $entry->created_at->lte(Carbon::now()->subMinutes(self::TOKEN_TTL_MINUTES));
    }

    private function resetUrl(string $email, string $token): string
    {
        return config('mail.gateway_url').'/reset-password?'.http_build_query(['token' => $token, 'email' => $email]);
    }
}
