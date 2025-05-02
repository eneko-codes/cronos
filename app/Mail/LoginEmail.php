<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class LoginEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;

    public string $tokenString;

    public bool $remember;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, string $tokenString, bool $remember)
    {
        $this->user = $user;
        $this->tokenString = $tokenString;
        $this->remember = $remember;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Magic Login Link',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Build the magic link with the 'remember' parameter
        $url = URL::route('login.verify', [
            'token' => $this->tokenString,
            'remember' => $this->remember ? '1' : '0',
        ]);

        return new Content(
            markdown: 'emails.login-link',
            with: [
                'url' => $url,
                'user' => $this->user,
            ],
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
