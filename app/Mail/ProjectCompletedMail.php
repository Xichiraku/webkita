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

class ProjectCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param  string  $url  tautan workspace sesuai penerima (klien atau developer) */
    public function __construct(public User $recipient, public Project $project, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Proyek {$this->project->project_title} selesai");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.project-completed');
    }
}
