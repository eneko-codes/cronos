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
        Log::info(class_basename(static::class).' Started', ['job' => class_basename(static::class)]);
        // Step 1: Fetch and process DeskTime users
        $validUsers = $this->getValidDesktimeUsers();
        // Step 2: Update users with DeskTime IDs
        $this->updateUserDesktimeIds($validUsers);
        // Step 3: Clear DeskTime IDs for users no longer in DeskTime
        $this->clearObsoleteDesktimeIds($validUsers);
        Log::info(class_basename(static::class).' Finished', ['job' => class_basename(static::class), 'processed_count' => $validUsers->count()]);
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
    private function updateUserDesktimeIds(Collection $validUsers): void
    {
        $validUsers->each(function (DesktimeEmployeeDTO $user): void {
            $email = strtolower(trim($user->email));
            $updated = User::where('email', $email)->update([
                'desktime_id' => $user->id,
            ]);
            if (! $updated) {
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
    private function clearObsoleteDesktimeIds(Collection $validUsers): void
    {
        $emails = $validUsers->map(fn (DesktimeEmployeeDTO $user) => strtolower(trim($user->email)));
        User::whereNotIn('email', $emails)
            ->whereNotNull('desktime_id')
            ->update(['desktime_id' => null]);
    }
}
