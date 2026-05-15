<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Category;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use App\Observers\CategoryObserver;
use App\Observers\DepartmentObserver;
use App\Observers\LeaveTypeObserver;
use App\Observers\ProjectObserver;
use App\Observers\ScheduleObserver;
use App\Observers\TaskObserver;
use App\Observers\UserObserver;
use App\Services\SyncStatusService;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;

/**
 * Event Service Provider
 *
 * Handles all event-listener mappings for the application.
 * For authentication, we use Laravel's native password-based authentication
 * with manual logging in LoginController and Livewire components.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     */
    protected $listen = [];

    /**
     * The model observers for your application.
     */
    protected $observers = [
        User::class => [UserObserver::class],
        LeaveType::class => [LeaveTypeObserver::class],
        Department::class => [DepartmentObserver::class],
        Schedule::class => [ScheduleObserver::class],
        Project::class => [ProjectObserver::class],
        Task::class => [TaskObserver::class],
        Category::class => [CategoryObserver::class],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();

        $this->registerSyncJobEventListeners();
    }

    /**
     * Register event listeners for sync jobs to track per-platform sync status.
     *
     * Uses Laravel's native Queue events to track when sync jobs start, complete,
     * or fail, storing the status in cache via SyncStatusService.
     */
    private function registerSyncJobEventListeners(): void
    {
        $syncStatusService = $this->app->make(SyncStatusService::class);

        // Track when a sync job starts processing
        Queue::before(function (JobProcessing $event) use ($syncStatusService): void {
            $jobClass = $this->getJobClassFromPayload($event->job->payload());

            if ($jobClass === null || ! $this->isSyncJob($jobClass)) {
                return;
            }

            $platform = SyncStatusService::getPlatformFromJob($jobClass);

            if ($platform !== null) {
                $syncStatusService->recordInProgress(
                    $platform,
                    SyncStatusService::getShortJobName($jobClass)
                );
            }
        });

        // Track when a sync job completes successfully
        Queue::after(function (JobProcessed $event) use ($syncStatusService): void {
            $jobClass = $this->getJobClassFromPayload($event->job->payload());

            if ($jobClass === null || ! $this->isSyncJob($jobClass)) {
                return;
            }

            $platform = SyncStatusService::getPlatformFromJob($jobClass);

            if ($platform !== null) {
                $syncStatusService->recordSuccess(
                    $platform,
                    SyncStatusService::getShortJobName($jobClass)
                );
            }
        });

        // Track when a sync job fails
        Queue::failing(function (JobFailed $event) use ($syncStatusService): void {
            $jobClass = $this->getJobClassFromPayload($event->job->payload());

            if ($jobClass === null || ! $this->isSyncJob($jobClass)) {
                return;
            }

            $platform = SyncStatusService::getPlatformFromJob($jobClass);

            if ($platform !== null) {
                $syncStatusService->recordFailure(
                    $platform,
                    SyncStatusService::getShortJobName($jobClass),
                    $event->exception->getMessage()
                );
            }
        });
    }

    /**
     * Get the job class name from the queue payload.
     *
     * Uses the displayName which contains the full class name,
     * avoiding the need to unserialize encrypted jobs.
     *
     * @param  array<string, mixed>  $payload  The job payload
     * @return string|null The job class name or null if not found
     */
    private function getJobClassFromPayload(array $payload): ?string
    {
        return $payload['displayName'] ?? null;
    }

    /**
     * Check if a job class is a sync job (in the App\Jobs\Sync namespace).
     *
     * @param  string  $jobClass  The job class name
     * @return bool True if it's a sync job
     */
    private function isSyncJob(string $jobClass): bool
    {
        return str_starts_with($jobClass, 'App\\Jobs\\Sync\\');
    }
}
