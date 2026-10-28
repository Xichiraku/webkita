<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

class VerifyEmailMail extends Mailable
{
    public string $verifyUrl;

    public function __construct(public User $user, string $token)
    {
        $this->verifyUrl = URL::route('verification.verify', [
            'token' => $token,
            'email' => $user->email,
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verifikasi Email Anda - WebKita');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.verify-email');
    }
}
