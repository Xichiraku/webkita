<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeveloperAssignedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $developer, public Project $project) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Anda ditugaskan ke proyek {$this->project->project_title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.developer-assigned');
    }
}
