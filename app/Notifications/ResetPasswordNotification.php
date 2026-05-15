<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPasswordNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Password reset notification.
 *
 * This is a MANDATORY notification - it ALWAYS sends via email only,
 * bypassing all notification preferences. Users must be able to
 * receive this email to reset their password.
 *
 * Extends Laravel's built-in ResetPassword notification and adds
 * queue support for asynchronous processing.
 *
 * Note: Cannot use HandlesNotificationDelivery trait because it must extend
 * Laravel's BaseResetPasswordNotification for proper functionality.
 */
class ResetPasswordNotification extends BaseResetPasswordNotification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        parent::__construct($token);
    }

    /**
     * Get the notification type enum value.
     *
     * Note: This notification type is not used for preference checking
     * since this is a mandatory notification. It's for tracking purposes only.
     */
    public function type(): NotificationType
    {
        // ResetPassword doesn't have a dedicated enum, use WelcomeNewUser as category
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
