<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPasswordNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Custom password reset notification that implements ShouldQueue
 * to make password reset emails asynchronous.
 *
 * This extends Laravel's built-in ResetPassword notification
 * and adds queue support natively.
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
}
