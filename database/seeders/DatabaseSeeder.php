<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NotificationType;
use App\Enums\Platform;
use App\Models\Category;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\ScheduleDetail;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserAttendance;
use App\Models\UserExternalIdentity;
use App\Models\UserLeave;
use App\Models\UserSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->createSettings();
        $departments = $this->createDepartments();
        $categories = $this->createCategories();
        $leaveTypes = $this->createLeaveTypes();
        [$schedules, $scheduleDetails] = $this->createSchedules();
        $users = $this->createUsers($departments);
        $this->createExternalIdentities($users);
        $this->attachCategories($users, $categories);
        $this->assignSchedules($users, $schedules);
        [$projects, $tasks] = $this->createProjectsAndTasks();
        $this->assignProjectsAndTasks($users, $projects, $tasks);
        $this->createTimeEntries($users, $projects, $tasks);
        $this->createAttendanceRecords($users);
        $this->createLeaveRecords($users, $leaveTypes);
        $this->createNotifications($users);
    }

    private function createSettings(): void
    {
        $settings = [
            'sync_frequency' => 'everyThirtyMinutes',
            'data_retention.global_period' => '365',
            'sync_window_days' => '30',
            'notification_channel' => 'mail',
            'notifications.retention_period' => '90',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * @return array<string, Department>
     */
    private function createDepartments(): array
    {
        $depts = [
            'engineering' => ['odoo_department_id' => 1, 'name' => 'Engineering'],
            'marketing' => ['odoo_department_id' => 2, 'name' => 'Marketing'],
            'sales' => ['odoo_department_id' => 3, 'name' => 'Sales'],
            'operations' => ['odoo_department_id' => 4, 'name' => 'Operations'],
        ];

        $result = [];
        foreach ($depts as $key => $data) {
            $result[$key] = Department::create([...$data, 'active' => true]);
        }

        return $result;
    }

    /**
     * @return array<string, Category>
     */
    private function createCategories(): array
    {
        $cats = [
            'fulltime' => ['odoo_category_id' => 1, 'name' => 'Full-Time'],
            'parttime' => ['odoo_category_id' => 2, 'name' => 'Part-Time'],
            'contractor' => ['odoo_category_id' => 3, 'name' => 'Contractor'],
        ];

        $result = [];
        foreach ($cats as $key => $data) {
            $result[$key] = Category::create([...$data, 'active' => true]);
        }

        return $result;
    }

    /**
     * @return array<string, LeaveType>
     */
    private function createLeaveTypes(): array
    {
        $types = [
            'annual' => ['odoo_leave_type_id' => 1, 'name' => 'Annual Leave', 'is_unpaid' => false, 'requires_allocation' => true],
            'sick' => ['odoo_leave_type_id' => 2, 'name' => 'Sick Leave', 'is_unpaid' => false, 'requires_allocation' => false],
            'personal' => ['odoo_leave_type_id' => 3, 'name' => 'Personal Day', 'is_unpaid' => false, 'requires_allocation' => true],
            'unpaid' => ['odoo_leave_type_id' => 4, 'name' => 'Unpaid Leave', 'is_unpaid' => true, 'requires_allocation' => false],
            'parental' => ['odoo_leave_type_id' => 5, 'name' => 'Parental Leave', 'is_unpaid' => false, 'requires_allocation' => false],
            'compensatory' => ['odoo_leave_type_id' => 6, 'name' => 'Compensatory Leave', 'is_unpaid' => false, 'requires_allocation' => true],
        ];

        $result = [];
        foreach ($types as $key => $data) {
            $result[$key] = LeaveType::create([
                ...$data,
                'request_unit' => 'day',
                'active' => true,
                'validation_type' => 'hr',
                'limit' => false,
            ]);
        }

        return $result;
    }

    /**
     * @return array{0: array<string, Schedule>, 1: array<int, list<ScheduleDetail>>}
     */
    private function createSchedules(): array
    {
        $schedules = [];
        $allDetails = [];

        // Standard 40h
        $schedules['standard'] = Schedule::create([
            'odoo_schedule_id' => 1,
            'description' => 'Standard 40 hours/week',
            'average_hours_day' => 8.0,
            'two_weeks_calendar' => false,
            'flexible_hours' => false,
            'active' => true,
        ]);

        // Part-Time 20h
        $schedules['parttime'] = Schedule::create([
            'odoo_schedule_id' => 2,
            'description' => 'Part-Time 20 hours/week',
            'average_hours_day' => 4.0,
            'two_weeks_calendar' => false,
            'flexible_hours' => false,
            'active' => true,
        ]);

        // Flexible 37.5h
        $schedules['flexible'] = Schedule::create([
            'odoo_schedule_id' => 3,
            'description' => 'Flexible 37.5 hours/week',
            'average_hours_day' => 7.5,
            'two_weeks_calendar' => false,
            'flexible_hours' => true,
            'active' => true,
        ]);

        $detailId = 1;

        // Standard: Mon-Fri, morning (09:00-13:00) + afternoon (14:00-18:00)
        foreach (range(0, 4) as $weekday) {
            foreach ([['morning', '09:00:00', '13:00:00'], ['afternoon', '14:00:00', '18:00:00']] as [$period, $start, $end]) {
                $allDetails[1][] = ScheduleDetail::create([
                    'odoo_schedule_id' => 1,
                    'odoo_detail_id' => $detailId++,
                    'weekday' => $weekday,
                    'day_period' => $period,
                    'week_type' => 0,
                    'start' => $start,
                    'end' => $end,
                    'active' => true,
                ]);
            }
        }

        // Part-Time: Mon-Fri, morning only (09:00-13:00)
        foreach (range(0, 4) as $weekday) {
            $allDetails[2][] = ScheduleDetail::create([
                'odoo_schedule_id' => 2,
                'odoo_detail_id' => $detailId++,
                'weekday' => $weekday,
                'day_period' => 'morning',
                'week_type' => 0,
                'start' => '09:00:00',
                'end' => '13:00:00',
                'active' => true,
            ]);
        }

        // Flexible: Mon-Fri, morning (08:30-12:30) + afternoon (13:30-17:00)
        foreach (range(0, 4) as $weekday) {
            foreach ([['morning', '08:30:00', '12:30:00'], ['afternoon', '13:30:00', '17:00:00']] as [$period, $start, $end]) {
                $allDetails[3][] = ScheduleDetail::create([
                    'odoo_schedule_id' => 3,
                    'odoo_detail_id' => $detailId++,
                    'weekday' => $weekday,
                    'day_period' => $period,
                    'week_type' => 0,
                    'start' => $start,
                    'end' => $end,
                    'active' => true,
                ]);
            }
        }

        return [$schedules, $allDetails];
    }

    /**
     * @param  array<string, Department>  $departments
     * @return list<User>
     */
    private function createUsers(array $departments): array
    {
        $users = [];

        // Use withoutEvents to prevent observer side effects (welcome emails, data cascades)
        User::withoutEvents(function () use (&$users, $departments): void {
            // User 1: Admin
            $users[] = User::factory()->admin()->create([
                'name' => 'Ana Garcia',
                'email' => 'admin@cronos.demo',
                'department_id' => $departments['engineering']->odoo_department_id,
                'job_title' => 'Engineering Manager',
            ]);

            // User 2: Maintenance
            $users[] = User::factory()->maintenance()->create([
                'name' => 'Carlos Lopez',
                'email' => 'maintenance@cronos.demo',
                'department_id' => $departments['operations']->odoo_department_id,
                'job_title' => 'DevOps Engineer',
            ]);

            // Users 3-8: Engineering
            $engineeringUsers = [
                ['name' => 'Maria Rodriguez', 'job_title' => 'Senior Backend Developer'],
                ['name' => 'David Martinez', 'job_title' => 'Frontend Developer'],
                ['name' => 'Laura Fernandez', 'job_title' => 'Full Stack Developer'],
                ['name' => 'Pablo Sanchez', 'job_title' => 'Junior Developer'],
                ['name' => 'Sofia Moreno', 'job_title' => 'QA Engineer'],
                ['name' => 'Javier Ruiz', 'job_title' => 'Tech Lead'],
            ];
            foreach ($engineeringUsers as $data) {
                $users[] = User::factory()->create([
                    ...$data,
                    'department_id' => $departments['engineering']->odoo_department_id,
                ]);
            }

            // Users 9-12: Marketing
            $marketingUsers = [
                ['name' => 'Elena Torres', 'job_title' => 'Marketing Director'],
                ['name' => 'Miguel Navarro', 'job_title' => 'Content Strategist'],
                ['name' => 'Carmen Diaz', 'job_title' => 'SEO Specialist'],
                ['name' => 'Alejandro Vega', 'job_title' => 'Social Media Manager'],
            ];
            foreach ($marketingUsers as $data) {
                $users[] = User::factory()->create([
                    ...$data,
                    'department_id' => $departments['marketing']->odoo_department_id,
                ]);
            }

            // Users 13-15: Sales
            $salesUsers = [
                ['name' => 'Isabel Romero', 'job_title' => 'Sales Manager'],
                ['name' => 'Roberto Herrera', 'job_title' => 'Account Executive'],
                ['name' => 'Patricia Molina', 'job_title' => 'Sales Representative'],
            ];
            foreach ($salesUsers as $data) {
                $users[] = User::factory()->create([
                    ...$data,
                    'department_id' => $departments['sales']->odoo_department_id,
                ]);
            }

            // User 16: Operations
            $users[] = User::factory()->create([
                'name' => 'Fernando Castro',
                'job_title' => 'Operations Coordinator',
                'department_id' => $departments['operations']->odoo_department_id,
            ]);

            // User 17: Archived (inactive) - set at creation to avoid observer side effects
            $users[] = User::factory()->inactive()->create([
                'name' => 'Alberto Gil',
                'job_title' => 'Former Intern',
                'department_id' => $departments['engineering']->odoo_department_id,
            ]);

            // User 18: Do-not-track - set at creation to avoid observer side effects
            $users[] = User::factory()->doNotTrack()->create([
                'name' => 'Lucia Ortega',
                'job_title' => 'Privacy-Conscious Developer',
                'department_id' => $departments['engineering']->odoo_department_id,
            ]);
        });

        return $users;
    }

    /**
     * @param  list<User>  $users
     */
    private function createExternalIdentities(array $users): void
    {
        $platforms = Platform::cases();
        $externalId = 1000;

        foreach ($users as $user) {
            // Skip do_not_track and inactive users for external identities
            if ($user->do_not_track || ! $user->is_active) {
                continue;
            }

            foreach ($platforms as $platform) {
                UserExternalIdentity::create([
                    'user_id' => $user->id,
                    'platform' => $platform,
                    'external_id' => (string) $externalId++,
                    'external_email' => $user->email,
                    'is_manual_link' => false,
                ]);
            }
        }
    }

    /**
     * @param  list<User>  $users
     * @param  array<string, Category>  $categories
     */
    private function attachCategories(array $users, array $categories): void
    {
        foreach ($users as $index => $user) {
            if ($index < 14) {
                $user->categories()->attach($categories['fulltime']->odoo_category_id);
            } elseif ($index < 16) {
                $user->categories()->attach($categories['parttime']->odoo_category_id);
            } else {
                $user->categories()->attach($categories['contractor']->odoo_category_id);
            }
        }
    }

    /**
     * @param  list<User>  $users
     * @param  array<string, Schedule>  $schedules
     */
    private function assignSchedules(array $users, array $schedules): void
    {
        foreach ($users as $index => $user) {
            // Skip inactive and do_not_track
            if (! $user->is_active || $user->do_not_track) {
                continue;
            }

            $scheduleKey = match (true) {
                $index < 2 => 'flexible',     // Admin & Maintenance
                $index < 8 => 'standard',     // Engineering
                $index < 12 => 'flexible',    // Marketing
                $index < 15 => 'standard',    // Sales
                default => 'parttime',        // Operations users
            };

            UserSchedule::create([
                'user_id' => $user->id,
                'odoo_schedule_id' => $schedules[$scheduleKey]->odoo_schedule_id,
                'effective_from' => now()->subMonths(6),
                'effective_until' => null,
            ]);
        }
    }

    /**
     * @return array{0: list<Project>, 1: list<Task>}
     */
    private function createProjectsAndTasks(): array
    {
        $projectsData = [
            ['proofhub_project_id' => 101, 'title' => 'Website Redesign', 'tasks' => [
                'Design new landing page', 'Implement responsive layouts', 'Migrate content to new CMS', 'Performance optimization', 'Accessibility audit',
            ]],
            ['proofhub_project_id' => 102, 'title' => 'Mobile App v2', 'tasks' => [
                'User authentication flow', 'Push notification system', 'Offline mode support', 'App store submission',
            ]],
            ['proofhub_project_id' => 103, 'title' => 'API Gateway', 'tasks' => [
                'Rate limiting implementation', 'OAuth2 integration', 'API documentation', 'Load testing',
            ]],
            ['proofhub_project_id' => 104, 'title' => 'Client Portal', 'tasks' => [
                'Dashboard widgets', 'Report generation', 'User role management', 'Billing integration', 'Email notifications',
            ]],
            ['proofhub_project_id' => 105, 'title' => 'Data Pipeline', 'tasks' => [
                'ETL process design', 'Data validation rules', 'Monitoring alerts',
            ]],
            ['proofhub_project_id' => 106, 'title' => 'Internal Tools', 'tasks' => [
                'Employee directory', 'Meeting room booking', 'IT asset tracker',
            ]],
        ];

        $projects = [];
        $tasks = [];
        $taskId = 1001;

        foreach ($projectsData as $pData) {
            $project = Project::create([
                'proofhub_project_id' => $pData['proofhub_project_id'],
                'title' => $pData['title'],
                'status' => ['name' => 'Active', 'color' => '#38bdf8'],
                'description' => "Project: {$pData['title']}",
            ]);
            $projects[] = $project;

            foreach ($pData['tasks'] as $taskTitle) {
                $tasks[] = Task::create([
                    'proofhub_task_id' => $taskId++,
                    'proofhub_project_id' => $project->proofhub_project_id,
                    'title' => $taskTitle,
                    'status' => 'active',
                    'due_date' => now()->addDays(rand(5, 45)),
                ]);
            }
        }

        return [$projects, $tasks];
    }

    /**
     * @param  list<User>  $users
     * @param  list<Project>  $projects
     * @param  list<Task>  $tasks
     */
    private function assignProjectsAndTasks(array $users, array $projects, array $tasks): void
    {
        // Only assign active, trackable users
        $trackableUsers = array_filter($users, fn (User $u) => $u->is_active && ! $u->do_not_track);

        foreach ($projects as $project) {
            // Assign 4-8 users per project
            $assignedUsers = collect($trackableUsers)->random(min(count($trackableUsers), rand(4, 8)));
            foreach ($assignedUsers as $user) {
                $project->users()->syncWithoutDetaching([$user->id]);
            }
        }

        foreach ($tasks as $task) {
            // Assign 1-3 users per task
            $assignedUsers = collect($trackableUsers)->random(min(count($trackableUsers), rand(1, 3)));
            foreach ($assignedUsers as $user) {
                $task->users()->syncWithoutDetaching([$user->id]);
            }
        }
    }

    /**
     * @param  list<User>  $users
     * @param  list<Project>  $projects
     * @param  list<Task>  $tasks
     */
    private function createTimeEntries(array $users, array $projects, array $tasks): void
    {
        $trackableUsers = array_values(array_filter($users, fn (User $u) => $u->is_active && ! $u->do_not_track));
        $timeEntryId = 5001;
        $descriptions = [
            'Code review and refactoring', 'Feature implementation', 'Bug investigation',
            'Documentation update', 'Team standup and planning', 'Testing and QA',
            'Database optimization', 'API endpoint development', 'UI/UX improvements',
            'Deployment and monitoring', 'Client meeting preparation', 'Sprint retrospective',
        ];

        foreach ($trackableUsers as $user) {
            // Generate time entries for the past 30 weekdays
            $date = now()->copy();
            $weekdaysProcessed = 0;

            while ($weekdaysProcessed < 30) {
                if ($date->isWeekday()) {
                    $entriesCount = rand(2, 4);
                    $totalSeconds = 0;
                    $targetSeconds = rand(24000, 28800); // 6.7h to 8h

                    for ($i = 0; $i < $entriesCount; $i++) {
                        $project = $projects[array_rand($projects)];
                        $projectTasks = array_filter($tasks, fn (Task $t) => $t->proofhub_project_id === $project->proofhub_project_id);
                        $task = ! empty($projectTasks) ? $projectTasks[array_rand($projectTasks)] : null;

                        $duration = ($i === $entriesCount - 1)
                            ? max(1800, $targetSeconds - $totalSeconds)
                            : rand(1800, (int) ($targetSeconds / $entriesCount) + 1800);
                        $totalSeconds += $duration;

                        TimeEntry::create([
                            'proofhub_time_entry_id' => $timeEntryId++,
                            'user_id' => $user->id,
                            'proofhub_project_id' => $project->proofhub_project_id,
                            'proofhub_task_id' => $task?->proofhub_task_id,
                            'status' => 'approved',
                            'description' => $descriptions[array_rand($descriptions)],
                            'date' => $date->toDateString(),
                            'duration_seconds' => $duration,
                            'billable' => rand(0, 100) < 70,
                        ]);
                    }

                    $weekdaysProcessed++;
                }
                $date->subDay();
            }
        }
    }

    /**
     * @param  list<User>  $users
     */
    private function createAttendanceRecords(array $users): void
    {
        $trackableUsers = array_values(array_filter($users, fn (User $u) => $u->is_active && ! $u->do_not_track));

        foreach ($trackableUsers as $user) {
            $date = now()->copy();
            $weekdaysProcessed = 0;

            while ($weekdaysProcessed < 30) {
                if ($date->isWeekday()) {
                    $isToday = $date->isToday();

                    // Morning segment
                    $morningClockIn = $date->copy()->setTime(8, rand(45, 59), rand(0, 59));
                    $morningClockOut = $isToday ? null : $date->copy()->setTime(13, rand(0, 15), rand(0, 59));
                    $morningDuration = $isToday ? 0 : $morningClockIn->diffInSeconds($morningClockOut);

                    UserAttendance::create([
                        'user_id' => $user->id,
                        'date' => $date->toDateString(),
                        'clock_in' => $morningClockIn,
                        'clock_out' => $morningClockOut,
                        'duration_seconds' => $morningDuration,
                        'is_remote' => rand(0, 100) < 60,
                    ]);

                    // Afternoon segment (skip for today if still "working")
                    if (! $isToday) {
                        $afternoonClockIn = $date->copy()->setTime(13, rand(45, 59), rand(0, 59));
                        $afternoonClockOut = $date->copy()->setTime(17, rand(30, 59), rand(0, 59));
                        $afternoonDuration = $afternoonClockIn->diffInSeconds($afternoonClockOut);

                        UserAttendance::create([
                            'user_id' => $user->id,
                            'date' => $date->toDateString(),
                            'clock_in' => $afternoonClockIn,
                            'clock_out' => $afternoonClockOut,
                            'duration_seconds' => $afternoonDuration,
                            'is_remote' => rand(0, 100) < 60,
                        ]);
                    }

                    $weekdaysProcessed++;
                }
                $date->subDay();
            }
        }
    }

    /**
     * @param  list<User>  $users
     * @param  array<string, LeaveType>  $leaveTypes
     */
    private function createLeaveRecords(array $users, array $leaveTypes): void
    {
        $trackableUsers = array_values(array_filter($users, fn (User $u) => $u->is_active && ! $u->do_not_track));
        $leaveId = 1;

        // 4 past validated leaves
        for ($i = 0; $i < 4; $i++) {
            $user = $trackableUsers[$i % count($trackableUsers)];
            $startDate = now()->subDays(rand(10, 25));
            $endDate = $startDate->copy()->addDays(rand(1, 3));

            UserLeave::create([
                'odoo_leave_id' => $leaveId++,
                'type' => 'employee',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'validate',
                'duration_days' => $startDate->diffInWeekdays($endDate) ?: 1,
                'user_id' => $user->id,
                'leave_type_id' => $leaveTypes[array_rand($leaveTypes)]->odoo_leave_type_id,
            ]);
        }

        // 1 current half-day (today, afternoon)
        $user = $trackableUsers[4 % count($trackableUsers)];
        UserLeave::create([
            'odoo_leave_id' => $leaveId++,
            'type' => 'employee',
            'start_date' => now()->startOfDay(),
            'end_date' => now()->endOfDay(),
            'status' => 'validate',
            'duration_days' => 0.5,
            'user_id' => $user->id,
            'leave_type_id' => $leaveTypes['personal']->odoo_leave_type_id,
            'request_hour_from' => 13.0,
            'request_hour_to' => 17.0,
        ]);

        // 3 upcoming validated leaves
        for ($i = 0; $i < 3; $i++) {
            $user = $trackableUsers[($i + 5) % count($trackableUsers)];
            $startDate = now()->addDays(rand(3, 14));
            $endDate = $startDate->copy()->addDays(rand(1, 5));

            UserLeave::create([
                'odoo_leave_id' => $leaveId++,
                'type' => 'employee',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'validate',
                'duration_days' => max(1, $startDate->diffInWeekdays($endDate)),
                'user_id' => $user->id,
                'leave_type_id' => $leaveTypes['annual']->odoo_leave_type_id,
            ]);
        }

        // 1 refused leave
        $user = $trackableUsers[8 % count($trackableUsers)];
        $startDate = now()->addDays(rand(5, 20));
        UserLeave::create([
            'odoo_leave_id' => $leaveId++,
            'type' => 'employee',
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addDays(2),
            'status' => 'refuse',
            'duration_days' => 2,
            'user_id' => $user->id,
            'leave_type_id' => $leaveTypes['unpaid']->odoo_leave_type_id,
        ]);
    }

    /**
     * @param  list<User>  $users
     */
    private function createNotifications(array $users): void
    {
        $admin = $users[0]; // Ana Garcia (admin)

        $notifications = [
            [
                'type' => NotificationType::ScheduleChange->value,
                'data' => [
                    'subject' => 'Your Work Schedule Has Been Updated',
                    'message' => 'Your assigned work schedule has changed from "Standard 40 hours/week" to "Flexible 37.5 hours/week".',
                    'level' => 'info',
                ],
                'read_at' => now()->subDays(3),
            ],
            [
                'type' => NotificationType::AdminPromotion->value,
                'data' => [
                    'subject' => 'User Promoted to Admin',
                    'message' => 'Ana Garcia has been promoted to administrator role.',
                    'level' => 'info',
                ],
                'read_at' => now()->subDays(5),
            ],
            [
                'type' => NotificationType::LeaveStatusChange->value,
                'data' => [
                    'subject' => 'Leave Request Update',
                    'message' => 'Your Annual Leave request for March 20-22 has been approved.',
                    'level' => 'success',
                ],
                'read_at' => now()->subDay(),
            ],
            [
                'type' => NotificationType::UserArchivedAdmin->value,
                'data' => [
                    'subject' => 'User Archived',
                    'message' => 'Alberto Gil\'s account has been archived and their data has been removed.',
                    'level' => 'warning',
                ],
                'read_at' => null,
            ],
            [
                'type' => NotificationType::ApiDown->value,
                'data' => [
                    'subject' => 'Service Outage Detected',
                    'message' => 'DeskTime API is currently experiencing issues. Data synchronization has been paused.',
                    'level' => 'error',
                ],
                'read_at' => null,
            ],
            [
                'type' => NotificationType::UnlinkedPlatformUser->value,
                'data' => [
                    'subject' => 'User Matching Issue',
                    'message' => 'A ProofHub user could not be matched to any local user. Please check the External Identities settings.',
                    'level' => 'warning',
                ],
                'read_at' => null,
            ],
            [
                'type' => NotificationType::LeaveReminder->value,
                'data' => [
                    'subject' => 'Upcoming Leave Reminder',
                    'message' => 'Your Annual Leave starts in 3 days (March 25-27).',
                    'level' => 'info',
                ],
                'read_at' => null,
            ],
            [
                'type' => NotificationType::UserDoNotTrackAdmin->value,
                'data' => [
                    'subject' => 'User Tracking Disabled',
                    'message' => 'Lucia Ortega has enabled Do Not Track. Their data has been removed from all tracking systems.',
                    'level' => 'warning',
                ],
                'read_at' => now()->subDays(2),
            ],
        ];

        foreach ($notifications as $index => $notification) {
            DB::table('notifications')->insert([
                'id' => Str::uuid()->toString(),
                'type' => 'App\\Notifications\\'.Str::studly($notification['type']).'Notification',
                'notifiable_type' => User::class,
                'notifiable_id' => $admin->id,
                'data' => json_encode($notification['data']),
                'read_at' => $notification['read_at'],
                'created_at' => now()->subHours($index * 6 + rand(1, 5)),
                'updated_at' => now()->subHours($index * 6 + rand(1, 5)),
            ]);
        }
    }
}
