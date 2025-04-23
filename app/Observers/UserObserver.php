<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\AdminPromotionEmail;
use Illuminate\Support\Facades\Notification;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        //
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Check if the user was just promoted to admin
        if ($user->wasChanged('is_admin') && $user->is_admin) {
            // Fetch all other admin users (excluding the promoted user)
            $adminUsers = User::where('is_admin', true)
                ->where('id', '!=', $user->id)
                ->get();

            // Create the notification instance
            $notification = new AdminPromotionEmail($user);

            // Send notification to all other admins who can receive it
            foreach ($adminUsers as $admin) {
                if ($admin->canReceiveNotification($notification)) {
                    // Use notifyNow as it's an important immediate event
                    $admin->notifyNow($notification);
                }
            }
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
