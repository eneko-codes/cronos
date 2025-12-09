<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Enums\Platform;
use App\Traits\HasConfigurableChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Notification sent to maintenance users when a platform user cannot be
 * automatically linked to a local user.
 *
 * This helps identify users in external platforms that need manual linking
 * or investigation to understand why automatic matching failed.
 *
 * Typical scenarios:
 * - Platform user has a different email than any local user
 * - Platform user has a different name than any local user
 * - New employee added to external platform but not yet in Odoo
 *
 * Uses Laravel's native RateLimited middleware to throttle notifications
 * per platform per external user ID per maintenance user (24 hours).
 */
class UnlinkedPlatformUserNotification extends Notification implements ShouldQueue
{
    use HasConfigurableChannels, Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  Platform  $platform  The external platform with the unlinked user
     * @param  string  $externalId  The external user ID that couldn't be linked
     * @param  string|null  $externalName  The name from the platform
     * @param  string|null  $externalEmail  The email from the platform
     */
    public function __construct(
        public Platform $platform,
        public string $externalId,
        public ?string $externalName,
        public ?string $externalEmail,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return array<int, string> Array of channel names
     */
    public function via(object $notifiable): array
    {
        return $this->getChannels();
    }

    /**
     * Get the middleware the notification job should pass through.
     *
     * Uses Laravel's native RateLimited middleware to throttle notifications
     * per platform per external user ID per maintenance user. The rate limiter
     * is configured in AppServiceProvider and extracts the key from the notification job.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @param  string  $channel  The notification channel
     * @return array<int, object>
     */
    public function middleware(object $notifiable, string $channel): array
    {
        return [
            (new RateLimited('unlinked-platform-user'))
                ->releaseAfter(86400), // Release after 24 hours (86400 seconds)
        ];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  object  $notifiable  The user receiving the notification
     */
    public function toMail(object $notifiable): MailMessage
    {
        $platformName = $this->platform->label();

        return (new MailMessage)
            ->subject("Unlinked User: {$platformName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A user in {$platformName} could not be automatically linked to a local user.")
            ->line("**Platform:** {$platformName}")
            ->line("**External ID:** {$this->externalId}")
            ->line('**Name:** '.($this->externalName ?: '(not provided)'))
            ->line('**Email:** '.($this->externalEmail ?: '(not provided)'))
            ->line('This user may need to be manually linked, or their information in the external platform may need to be updated to match a local user.')
            ->action('View Users', url('/users'));
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

        return [
            'subject' => "Unlinked User: {$platformName}",
            'message' => implode("\n", [
                "A user in {$platformName} could not be automatically linked to a local user.",
                "Platform: {$platformName}",
                "External ID: {$this->externalId}",
                'Name: '.($this->externalName ?: '(not provided)'),
                'Email: '.($this->externalEmail ?: '(not provided)'),
            ]),
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

        return (new SlackMessage)
            ->text("Unlinked User: {$platformName}")
            ->headerBlock("Unlinked User: {$platformName}")
            ->sectionBlock(function ($block) use ($notifiable): void {
                $block->text("Hello {$notifiable->name},");
            })
            ->sectionBlock(function ($block) use ($platformName): void {
                $block->text("A user in {$platformName} could not be automatically linked to a local user.");
            })
            ->sectionBlock(function ($block) use ($platformName): void {
                $block->field("*Platform:*\n{$platformName}")->markdown();
                $block->field("*External ID:*\n{$this->externalId}")->markdown();
            })
            ->sectionBlock(function ($block): void {
                $block->field('*Name:*\n'.($this->externalName ?: '(not provided)'))->markdown();
                $block->field('*Email:*\n'.($this->externalEmail ?: '(not provided)'))->markdown();
            });
    }

    /**
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::UnlinkedPlatformUser;
    }
}
