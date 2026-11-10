<?php

namespace App\Mail;

use App\Services\PasswordResetService;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Symfony\Component\Mime\Email;

class ResetPasswordMail extends Mailable
{
    public function __construct(public string $name, public string $url) {}

    public function build(): static
    {
        return $this->withSymfonyMessage(fn (Email $message) => $message->text($this->plainText()));
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your KAIA password');
    }

    public function content(): Content
    {
        return new Content(htmlString: sprintf(
            '<p>Hi %s,</p><p>Click the link below to choose a new password. It works once, for %d minutes.</p>'
            .'<p><a href="%s">Reset my password</a></p><p>If you did not ask for this, you can ignore this email.</p>',
            e($this->name),
            PasswordResetService::TOKEN_TTL_MINUTES,
            e($this->url),
        ));
    }

    public function plainText(): string
    {
        return sprintf(
            "Hi %s,\n\nOpen this link to choose a new password. It works once, for %d minutes:\n%s\n\nIf you did not ask for this, you can ignore this email.\n",
            $this->name,
            PasswordResetService::TOKEN_TTL_MINUTES,
            $this->url,
        );
    }
}
