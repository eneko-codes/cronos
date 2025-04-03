<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeEmail extends Notification implements ShouldQueue
{
  use Queueable;

  public User $user;
  public string $url;

  public function __construct(User $user)
  {
    $this->user = $user;
    $this->url = route('login');
  }

  /**
   * The channels this notification will be delivered on.
   */
  public function via($notifiable): array
  {
    // Don't send if notifications are muted
    if (!$notifiable->shouldReceiveNotifications()) {
      return [];
    }
    return ['mail'];
  }

  /**
   * Build the mail version of the notification.
   */
  public function toMail($notifiable): MailMessage
  {
    return (new MailMessage())
      ->from(config('mail.from.address'), config('mail.from.name'))
      ->subject('Welcome to ' . config('app.name'))
      ->greeting("Hello {$this->user->name},")
      ->line('Welcome to ' . config('app.name') . '!')
      ->line(
        "You can log in using your work email: {$this->user->email}. You'll receive a magic login link."
      )
      ->action('Go to Login Page', $this->url);
  }
}
