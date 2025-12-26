<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeBuyerMail extends Mailable
{
    use Queueable, SerializesModels;

    public $userName;
    public $appUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($userName, $appUrl = null)
    {
        $this->userName = $userName;
        $this->appUrl = $appUrl ?? config('app.url', 'https://colala.hmstech.xyz');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Colala Mall - Your Shopping Journey Begins!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-buyer',
            with: [
                'userName' => $this->userName,
                'appUrl' => $this->appUrl,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

