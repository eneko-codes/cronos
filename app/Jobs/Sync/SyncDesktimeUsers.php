<?php

declare(strict_types=1);

namespace App\Jobs\Sync;

use App\Clients\DesktimeApiClient;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Job to synchronize DeskTime user information with the local database.
 *
 * Updates local users with their DeskTime IDs and clears DeskTime IDs for users no longer present in DeskTime.
 */
class SyncDesktimeUsers extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    /**
     * Constructs a new SyncDesktimeUsers job instance.
     *
     * @param  DesktimeApiClient  $desktime  The DeskTime API client.
     */
    public function __construct(DesktimeApiClient $desktime)
    {
        // Assign to parent's protected $desktime
        $this->desktime = $desktime;
    }

    /**
     * Main entry point for the job.
     *
     * Fetches users from DeskTime, updates local users with DeskTime IDs, and clears DeskTime IDs for users no longer present in DeskTime.
     */
    protected function execute(): void
    {
        // Step 1: Fetch and process DeskTime users
        $validUsers = $this->getValidDesktimeUsers();

        // Step 2: Update users with DeskTime IDs
        $this->updateUserDesktimeIds($validUsers);

        // Step 3: Clear DeskTime IDs for users no longer in DeskTime
        $this->clearObsoleteDesktimeIds($validUsers->pluck('email'));
    }

    /**
     * Retrieves and processes valid users from DeskTime.
     *
     * @return Collection Collection of valid DeskTime users with emails and IDs.
     */
    private function getValidDesktimeUsers(): Collection
    {
        $employeesData = $this->desktime->getAllEmployees(null, 'month');

        // Merge users from all dates of the response JSON
        $desktimeUsers = $employeesData
            ->reduce(function ($allUsers, $dateUsers) {
                return $allUsers->merge($dateUsers);
            }, collect())
            ->unique('id'); // Remove duplicates

        // Filter & map valid users
        return $desktimeUsers
            ->filter(fn ($user) => ! empty($user['email']) && ! empty($user['id']))
            ->map(
                fn ($user) => [
                    'email' => strtolower(trim($user['email'])),
                    'desktime_id' => $user['id'],
                ]
            );
    }

    /**
     * Updates local users with their DeskTime IDs.
     *
     * @param  Collection  $validUsers  Collection of valid DeskTime users.
     */
    private function updateUserDesktimeIds(Collection $validUsers): void
    {
        $validUsers->each(function ($desktimeUser): void {
            User::where('email', $desktimeUser['email'])->update([
                'desktime_id' => $desktimeUser['desktime_id'],
            ]);
        });
    }

    /**
     * Clears DeskTime IDs for users no longer present in DeskTime.
     *
     * @param  Collection  $currentDesktimeEmails  Emails of current DeskTime users.
     */
    private function clearObsoleteDesktimeIds(
        Collection $currentDesktimeEmails
    ): void {
        User::whereNotIn('email', $currentDesktimeEmails)
            ->whereNotNull('desktime_id')
            ->update(['desktime_id' => null]);
    }
}
