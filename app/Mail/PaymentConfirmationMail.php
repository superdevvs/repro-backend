<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class PaymentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public object $shoot;
    public object $payment;

    public function __construct(User $user, object $shoot, object $payment)
    {
        $this->user = $user;
        $this->shoot = $shoot;
        $this->payment = $payment;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thank You for Your Payment!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment_confirmation',
            with: [
                'user' => $this->user,
                'shoot' => $this->shoot,
                'payment' => $this->payment,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
