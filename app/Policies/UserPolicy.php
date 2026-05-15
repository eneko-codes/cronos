<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isMaintenance();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->isMaintenance() || $user->id === $model->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->isMaintenance() || $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the authenticated user can promote another user to admin.
     */
    public function promoteToAdmin(User $authUser, User $userToPromote): bool
    {
        // Only admins can promote other users that are not already admin
        return $authUser->isAdmin() && ! $userToPromote->isAdmin();
    }

    /**
     * Determine whether the authenticated user can set another user as do not track.
     */
    public function disableTracking(User $authUser, User $userToNotTrack): bool
    {
        // Only admins can add other users to not track that are not already "do not track"
        return $authUser->isAdmin() && ! $userToNotTrack->do_not_track;
    }

    /**
     * Determine whether the authenticated user can enable tracking for another user.
     */
    public function enableTracking(User $authUser, User $userToTrack): bool
    {
        // Only admins can enable tracking for users that are currently "do not track"
        return $authUser->isAdmin() && $userToTrack->do_not_track;
    }

    /**
     * Determine whether the authenticated user can demote another user from admin.
     */
    public function demoteAdmin(User $authUser, User $userToDemote): bool
    {
        // Only admins can demote other admins, and they can't demote themselves
        return $authUser->isAdmin() &&
          $userToDemote->isAdmin() &&
          $authUser->id !== $userToDemote->id;
    }

    /**
     * Determine whether the authenticated user can promote another user to maintenance role.
     */
    public function promoteToMaintenance(User $authUser, User $userToPromote): bool
    {
        // Only admins can promote users to maintenance role
        // Cannot promote users who are already Maintenance
        return $authUser->isAdmin() && ! $userToPromote->isMaintenance();
    }

    /**
     * Determine whether the authenticated user can demote another user from maintenance role.
     */
    public function demoteFromMaintenance(User $authUser, User $userToDemote): bool
    {
        // Only admins can demote users from maintenance role
        return $authUser->isAdmin() && $userToDemote->isMaintenance();
    }

    /**
     * Determine whether the authenticated user can mute notifications for another user.
     */
    public function muteNotifications(User $authUser, User $userToMute): bool
    {
        // Only admins can mute notifications for users that are not already muted
        return $authUser->isAdmin() && ! $userToMute->muted_notifications;
    }

    /**
     * Determine whether the authenticated user can unmute notifications for another user.
     */
    public function unmuteNotifications(User $authUser, User $userToUnmute): bool
    {
        // Only admins can unmute notifications for users that are currently muted
        return $authUser->isAdmin() && $userToUnmute->muted_notifications;
    }

    /**
     * Determine whether the authenticated user can archive another user.
     */
    public function archiveUser(User $authUser, User $userToArchive): bool
    {
        // Only admins can archive users, and they can't archive themselves
        // Can only archive users that are currently active
        return $authUser->isAdmin() &&
          $authUser->id !== $userToArchive->id &&
          $userToArchive->is_active;
    }

    /**
     * Determine whether the authenticated user can reactivate an archived user.
     */
    public function reactivateUser(User $authUser, User $userToReactivate): bool
    {
        // Only admins can reactivate users
        // Can only reactivate users that are currently archived
        return $authUser->isAdmin() && ! $userToReactivate->is_active;
    }
}
