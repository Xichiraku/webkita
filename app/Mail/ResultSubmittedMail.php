<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResultSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $client, public Project $project, public ProjectMilestone $milestone, public ProjectUpdate $update) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Hasil milestone {$this->milestone->title} siap direview");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.result-submitted');
    }
}
