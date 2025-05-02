<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\DesktimeApiCalls;
use Illuminate\Support\Collection;

/**
 * Class SyncDesktimeUsers
 *
 * Synchronizes DeskTime user info into the local database,
 * updating users with their DeskTime IDs and clearing
 * DeskTime IDs for users no longer in DeskTime.
 */
class SyncDesktimeUsers extends BaseSyncJob
{
    /**
     * The priority of the job in the queue.
     * Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    /**
     * SyncDesktimeUsers constructor.
     *
     * @param  DesktimeApiCalls  $desktime  An instance of the DesktimeApiCalls service.
     */
    public function __construct(DesktimeApiCalls $desktime)
    {
        // Assign to parent's protected $desktime
        $this->desktime = $desktime;
    }

    /**
     * Executes the synchronization process.
     *
     * This method performs the following operations:
     * 1. Fetches employees from DeskTime API
     * 2. Extracts valid users with their DeskTime IDs and emails
     * 3. Updates local users with DeskTime IDs
     * 4. Clears DeskTime IDs for users no longer in DeskTime
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
     * @return Collection Collection of valid DeskTime users with emails and IDs
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
     * @param  Collection  $validUsers  Collection of valid DeskTime users
     */
    private function updateUserDesktimeIds(Collection $validUsers): void
    {
        $validUsers->each(function ($desktimeUser) {
            User::where('email', $desktimeUser['email'])->update([
                'desktime_id' => $desktimeUser['desktime_id'],
            ]);
        });
    }

    /**
     * Clears DeskTime IDs for users no longer in DeskTime.
     *
     * @param  Collection  $currentDesktimeEmails  Emails of current DeskTime users
     */
    private function clearObsoleteDesktimeIds(
        Collection $currentDesktimeEmails
    ): void {
        User::whereNotIn('email', $currentDesktimeEmails)
            ->whereNotNull('desktime_id')
            ->update(['desktime_id' => null]);
    }
}
