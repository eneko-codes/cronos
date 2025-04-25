<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Models\User;
use App\Observers\UserObserver;
use App\Models\LeaveType;
use App\Observers\LeaveTypeObserver;
use App\Models\Department;
use App\Observers\DepartmentObserver;
use App\Models\Schedule;
use App\Observers\ScheduleObserver;
use App\Models\Project;
use App\Observers\ProjectObserver;
use App\Models\Task;
use App\Observers\TaskObserver;
use App\Models\Category;
use App\Observers\CategoryObserver;

/**
 * Event Service Provider
 *
 * Handles all event-listener mappings for the application.
 * For authentication, we use custom magic link login with manual logging
 * in LoginController and Livewire components.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [];

    /**
     * The model observers for your application.
     *
     * @var array
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
    }
}
