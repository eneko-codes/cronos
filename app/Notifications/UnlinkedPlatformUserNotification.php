<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DataTransferObjects\UnlinkedUser;
use App\Enums\NotificationType;
use App\Enums\Platform;
use App\Traits\HandlesNotificationDelivery;
use App\Traits\HasRateLimitedMiddleware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Collection;

/**
 * Notification sent to maintenance users when platform users cannot be
 * automatically linked to local users.
 *
 * This notification aggregates all unlinked users from a sync cycle into
 * a single email per platform, preventing notification spam during
 * multiple sync cycles throughout the day.
 *
 * Typical scenarios:
 * - Platform users have different emails than local users
 * - Platform users have different names than local users
 * - New employees added to external platform but not yet in Odoo
 *
 * This is a CONFIGURABLE notification - delivery channels and sending
 * are controlled by global and user notification preferences.
 *
 * Uses HasRateLimitedMiddleware for rate limiting per platform per maintenance user.
 */
class UnlinkedPlatformUserNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, HasRateLimitedMiddleware, Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * Set to 2 to allow retries for actual failures (e.g., email/Slack issues).
     * Rate-limited notifications are dropped and not retried.
     */
    public int $tries = 2;

    /**
     * Create a new notification instance.
     *
     * @param  Platform  $platform  The external platform with unlinked users
     * @param  Collection<int, UnlinkedUser>  $unlinkedUsers  Collection of unlinked users
     */
    public function __construct(
        public Platform $platform,
        public Collection $unlinkedUsers,
    ) {}

    /**
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::UnlinkedPlatformUser;
    }

    /**
     * Get the rate limiter name for this notification.
     *
     * @return string The rate limiter name configured in AppServiceProvider
     */
    protected function getRateLimiterName(): string
    {
        return 'unlinked-platform-user';
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  object  $notifiable  The user receiving the notification
     */
    public function toMail(object $notifiable): MailMessage
    {
        $platformName = $this->platform->label();
        $count = $this->unlinkedUsers->count();

        $mailMessage = (new MailMessage)
            ->subject("Unlinked Users: {$platformName} ({$count})")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$count} user(s) in {$platformName} could not be automatically linked to local users.");

        // Add table of unlinked users
        $mailMessage->line('**Unlinked Users:**');
        $mailMessage->line('');

        foreach ($this->unlinkedUsers as $unlinkedUser) {
            $mailMessage->line("**External ID:** {$unlinkedUser->externalId}");
            $mailMessage->line('**Name:** '.($unlinkedUser->externalName ?: '(not provided)'));
            $mailMessage->line('**Email:** '.($unlinkedUser->externalEmail ?: '(not provided)'));
            $mailMessage->line('');
        }

        $mailMessage
            ->line('These users may need to be manually linked, or their information in the external platform may need to be updated to match local users.')
            ->action('View Users', url('/users'));

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $platformName = $this->platform->label();
        $count = $this->unlinkedUsers->count();

        $messageLines = [
            "{$count} user(s) in {$platformName} could not be automatically linked to local users.",
            "Platform: {$platformName}",
            '',
            'Unlinked Users:',
        ];

        foreach ($this->unlinkedUsers as $unlinkedUser) {
            $messageLines[] = "  - External ID: {$unlinkedUser->externalId}";
            $messageLines[] = '    Name: '.($unlinkedUser->externalName ?: '(not provided)');
            $messageLines[] = '    Email: '.($unlinkedUser->externalEmail ?: '(not provided)');
            $messageLines[] = '';
        }

        return [
            'subject' => "Unlinked Users: {$platformName} ({$count})",
            'message' => implode("\n", $messageLines),
            'level' => 'warning',
        ];
    }

    /**
     * Get the Slack representation of the notification.
     *
     * @param  object  $notifiable  The user receiving the notification
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $platformName = $this->platform->label();
        $count = $this->unlinkedUsers->count();

        $slackMessage = (new SlackMessage)
            ->text("Unlinked Users: {$platformName} ({$count})")
            ->headerBlock("Unlinked Users: {$platformName} ({$count})")
            ->sectionBlock(function ($block) use ($notifiable): void {
                $block->text("Hello {$notifiable->name},");
            })
            ->sectionBlock(function ($block) use ($platformName, $count): void {
                $block->text("{$count} user(s) in {$platformName} could not be automatically linked to local users.");
            });

        // Add each unlinked user as a section
        foreach ($this->unlinkedUsers as $unlinkedUser) {
            $slackMessage->sectionBlock(function ($block) use ($unlinkedUser): void {
                $block->field("*External ID:*\n{$unlinkedUser->externalId}")->markdown();
                $block->field('*Name:*\n'.($unlinkedUser->externalName ?: '(not provided)'))->markdown();
                $block->field('*Email:*\n'.($unlinkedUser->externalEmail ?: '(not provided)'))->markdown();
            });
        }

        return $slackMessage;
    }
}
