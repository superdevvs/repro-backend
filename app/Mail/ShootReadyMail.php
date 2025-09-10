<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class ShootReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public object $shoot;

    public function __construct(User $user, object $shoot)
    {
        $this->user = $user;
        $this->shoot = $shoot;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Photo Shoot Images Are Ready for Download!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shoot_ready',
            with: [
                'user' => $this->user,
                'shoot' => $this->shoot,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
