<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Desktime;

use App\Clients\DesktimeApiClient;
use App\DataTransferObjects\Desktime\DesktimeEmployeeDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
        $stats = [
            'received' => 0,
            'skipped' => 0,
            'updated' => 0,
            'created' => 0,
            'deleted' => 0,
        ];
        // Step 1: Fetch and process DeskTime users
        $validUsers = $this->getValidDesktimeUsers();
        $stats['received'] = $validUsers->count();
        // Step 2: Update users with DeskTime IDs
        $this->updateUserDesktimeIds($validUsers, $stats);
        // Step 3: Clear DeskTime IDs for users no longer in DeskTime
        $stats['deleted'] = $this->clearObsoleteDesktimeIds($validUsers);
        Log::info(class_basename(static::class).' Sync stats', $stats);
    }

    /**
     * Retrieves and processes valid users from DeskTime.
     *
     * @return Collection|DesktimeEmployeeDTO[] Collection of valid DeskTime DesktimeEmployeeDTOs.
     */
    private function getValidDesktimeUsers(): Collection
    {
        return $this->desktime->getAllEmployees(null, 'month')
            ->unique('id')
            ->filter(fn (DesktimeEmployeeDTO $user) => ! empty($user->email) && ! empty($user->id));
    }

    /**
     * Updates local users with their DeskTime IDs.
     *
     * @param  Collection|DesktimeEmployeeDTO[]  $validUsers  Collection of valid DeskTime DesktimeEmployeeDTOs.
     */
    private function updateUserDesktimeIds(Collection $validUsers, array &$stats): void
    {
        $validUsers->each(function (DesktimeEmployeeDTO $user) use (&$stats): void {
            $email = strtolower(trim($user->email));
            $updated = User::where('email', $email)->update([
                'desktime_id' => $user->id ? (int) $user->id : null,
            ]);
            if ($updated) {
                $stats['updated']++;
            } else {
                $created = User::where('email', $email)->exists() ? 0 : 1;
                $stats['created'] += $created;
                $stats['skipped'] += (1 - $created);
                Log::warning(class_basename(static::class).' Skipping: user not found', [
                    'job' => class_basename(static::class),
                    'entity' => 'user',
                    'entity_id' => $user->id,
                    'email' => $email,
                ]);
            }
        });
    }

    /**
     * Clears DeskTime IDs for users no longer present in DeskTime.
     *
     * @param  Collection|DesktimeEmployeeDTO[]  $validUsers  Collection of valid DeskTime DesktimeEmployeeDTOs.
     */
    private function clearObsoleteDesktimeIds(Collection $validUsers): int
    {
        $emails = $validUsers->map(fn (DesktimeEmployeeDTO $user) => strtolower(trim($user->email)));
        $deleted = User::whereNotIn('email', $emails)
            ->whereNotNull('desktime_id')
            ->update(['desktime_id' => null]);

        return $deleted;
    }
}
