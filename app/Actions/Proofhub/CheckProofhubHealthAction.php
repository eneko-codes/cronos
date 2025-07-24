<?php

declare(strict_types=1);

namespace App\Actions\Proofhub;

use App\Actions\GetNotificationPreferencesAction;
use App\Clients\ProofhubApiClient;
use App\Enums\RoleType;
use App\Models\User;
use App\Notifications\ApiDownWarning;

/**
 * Action to check the health of the ProofHub API and send notifications on failure.
 */
class CheckProofhubHealthAction
{
    /**
     * Executes the health check for the ProofHub API.
     *
     * @param  ProofhubApiClient  $client  The ProofHub API client to check.
     */
    public function __invoke(ProofhubApiClient $client): array
    {
        $health = $client->ping();
        if (! $health['success']) {
            $admins = User::where('user_type', RoleType::Admin)->get();
            $apiDownNotification = new ApiDownWarning('ProofHub', $health['message'] ?? 'API health check failed');
            $getPreferences = resolve(GetNotificationPreferencesAction::class);
            foreach ($admins as $admin) {
                if ($getPreferences->execute($admin)['eligibility'][$apiDownNotification->type()->value] ?? false) {
                    $admin->notify($apiDownNotification);
                }
            }
        }

        return $health;
    }
}
