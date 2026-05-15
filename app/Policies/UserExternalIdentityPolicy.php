<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\UserExternalIdentity;

/**
 * Policy for managing UserExternalIdentity resources.
 *
 * External identities are primarily used for sync purposes.
 * Users can view their own identities.
 * Admins can manage all identities.
 */
class UserExternalIdentityPolicy
{
    /**
     * Determine whether the user can view any models.
     *
     * Standard Laravel policy method - only takes the authenticated user.
     * For checking access to a specific user's identities, use viewAnyForUser().
     *
     * @param  User  $user  The authenticated user
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view any models for a target user.
     *
     * Custom method for checking if a user can view another user's identities.
     * Used when viewing identities for a specific user (e.g., admin/maintenance viewing another user).
     *
     * @param  User  $user  The authenticated user
     * @param  User  $targetUser  The user whose identities are being viewed
     */
    public function viewAnyForUser(User $user, User $targetUser): bool
    {
        return $user->id === $targetUser->id || $user->isAdmin() || $user->isMaintenance();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UserExternalIdentity $identity): bool
    {
        return $user->id === $identity->user_id || $user->isAdmin() || $user->isMaintenance();
    }

    /**
     * Determine whether the user can create models.
     *
     * Admins and maintenance users can manually create identities.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isMaintenance();
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Admins and maintenance users can delete identities.
     */
    public function delete(User $user, UserExternalIdentity $identity): bool
    {
        return $user->isAdmin() || $user->isMaintenance();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, UserExternalIdentity $identity): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, UserExternalIdentity $identity): bool
    {
        return $user->isAdmin();
    }
}
