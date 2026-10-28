<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ResetOtpMail extends Mailable
{
    public function __construct(public User $user, public string $otp, public int $ttlMinutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Kode OTP Reset Password - WebKita');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reset-otp');
    }
}
