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

/**
 * Mailable class responsible for sending the magic login link email.
 * This email contains a signed, time-limited URL for the user to click and log in.
 */
class LoginEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The user instance for whom the email is being sent.
     */
    public User $user;

    /**
     * The pre-generated signed magic login URL.
     */
    public string $url;

    /**
     * Create a new message instance.
     *
     * @param  User  $user  The user receiving the email.
     * @param  string  $url  The pre-generated signed magic login URL.
     * @return void
     */
    public function __construct(User $user, string $url)
    {
        $this->user = $user;
        $this->url = $url;
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
     *
     * Uses the pre-generated signed URL provided to the constructor.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.login-link',
            with: [
                'url' => $this->url,
                'user' => $this->user,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment> An empty array as there are no attachments.
     */
    public function attachments(): array
    {
        return [];
    }
}
