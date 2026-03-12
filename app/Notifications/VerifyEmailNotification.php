<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmailNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Email verification notification.
 *
 * This is a MANDATORY notification - it ALWAYS sends via email only,
 * bypassing all notification preferences. Users must be able to
 * receive this email to verify their email address.
 *
 * Extends Laravel's built-in VerifyEmail notification and adds
 * queue support for asynchronous processing.
 *
 * Note: Cannot use HandlesNotificationDelivery trait because it must extend
 * Laravel's BaseVerifyEmailNotification for proper functionality.
 */
class VerifyEmailNotification extends BaseVerifyEmailNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the notification may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the notification can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Get the notification's delivery channels.
     *
     * Verification emails must always be sent via email, regardless of
     * user's email verification status (since that's what we're trying to verify!).
     *
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the notification type enum value.
     *
     * Note: This notification type is not used for preference checking
     * since this is a mandatory notification. It's for tracking purposes only.
     */
    public function type(): NotificationType
    {
        // VerifyEmail doesn't have a dedicated enum, use WelcomeNewUser as category
        return NotificationType::WelcomeNewUser;
    }

    /**
     * Determine if the notification should be sent.
     *
     * Mandatory notifications ALWAYS send - they bypass all preference settings.
     *
     * @param  object  $notifiable  The entity receiving the notification
     * @param  string  $channel  The notification channel being checked
     * @return bool Always returns true
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return true;
    }
}
