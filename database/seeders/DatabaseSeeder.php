<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\Project;
use App\Models\Schedule;
use App\Models\ScheduleDetail;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserAttendance;
use App\Models\UserLeave;
use App\Models\UserSchedule;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// MUST BE RUN ON AN EMPTY DATABASE FOR SHOWCASE PURPOUSES.
// This is the main seeder that will be used to seed the database with synthetic fake data.
// It will create 5 users with specified roles (1 admin, 1 muted) and assign them to departments, schedules, projects, and tasks.
// It will also create software development projects and tasks, and assign them to users.
// It will also create leave types and assign them to users.
// It will also create schedules and assign them to users.
// It will also create time entries for users.


class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Hardcoded time frame for 2025 Q1
        $from = Carbon::now()->subMonth()->startOfDay();
        $to = Carbon::now()->endOfDay();
        
        // Create departments (will be assigned to users)
        $departments = [
            Department::factory()->create(['name' => 'Engineering']),
            Department::factory()->create(['name' => 'Design']),
            Department::factory()->create(['name' => 'Product'])
        ];
        
        // Create categories (will be used for leaves)
        $categories = [
            Category::factory()->create(['name' => 'Development']),
            Category::factory()->create(['name' => 'Design']),
            Category::factory()->create(['name' => 'Testing']),
            Category::factory()->create(['name' => 'Documentation']),
            Category::factory()->create(['name' => 'Management'])
        ];
        
        // Create leave types
        $leaveTypes = [
            LeaveType::factory()->create(['name' => 'Vacation']),
            LeaveType::factory()->create(['name' => 'Sick Leave']),
            LeaveType::factory()->create(['name' => 'Personal Leave']),
            LeaveType::factory()->create(['name' => 'Parental Leave']),
            LeaveType::factory()->create(['name' => 'Work From Home'])
        ];
        
        // Create schedules with details (4h and 8h workdays)
        $schedules = $this->createSchedules();
        
        // Create 5 users (1 admin, 1 muted)
        $users = $this->createUsers($departments);
        
        // Assign schedules to users
        $this->assignSchedulesToUsers($users, $schedules);
        
        // Create software development projects and tasks
        [$projects, $tasks] = $this->createProjectsAndTasks();
        
        // Assign projects to users
        $this->assignProjectsToUsers($users, $projects);
        
        // Assign categories to users
        $this->assignCategoriesToUsers($users, $categories);
        
        // Assign tasks to users
        $this->assignTasksToUsers($users, $tasks);
        
        // Create data for these users within the specified time frame
        $this->createTimeFrameData($users, $projects, $tasks, $leaveTypes, $from, $to);
        
        // Create additional leaves to ensure there's always some leave data
        $this->createGuaranteedLeaves($users, $leaveTypes, $from, $to);
    }
    
    /**
     * Create work schedules with time slots (4h and 8h workdays)
     */
    private function createSchedules(): array
    {
        // Create part-time (4h) and full-time (8h) schedules
        $partTimeSchedule = Schedule::factory()->create([
            'odoo_schedule_id' => 101,
            'description' => 'Part-Time, 4h/day',
            'average_hours_day' => 4,
        ]);
        
        $fullTimeSchedule = Schedule::factory()->create([
            'odoo_schedule_id' => 102,
            'description' => 'Full-Time, 8h/day',
            'average_hours_day' => 8,
        ]);
        
        // Create schedule details for part-time (4h) - Morning only
        for ($weekday = 0; $weekday <= 4; $weekday++) {
            // Morning slot (9am-1pm)
            ScheduleDetail::factory()->create([
                'odoo_schedule_id' => $partTimeSchedule->odoo_schedule_id,
                'odoo_detail_id' => 1000 + $weekday,
                'weekday' => $weekday,
                'day_period' => 'morning',
                'start' => '09:00:00',
                'end' => '13:00:00',
            ]);
        }
        
        // Create schedule details for full-time (8h) - Morning and afternoon
        for ($weekday = 0; $weekday <= 4; $weekday++) {
            // Morning slot (9am-1pm)
            ScheduleDetail::factory()->create([
                'odoo_schedule_id' => $fullTimeSchedule->odoo_schedule_id,
                'odoo_detail_id' => 2000 + (2 * $weekday),
                'weekday' => $weekday,
                'day_period' => 'morning',
                'start' => '09:00:00',
                'end' => '13:00:00',
            ]);
            
            // Afternoon slot (2pm-6pm)
            ScheduleDetail::factory()->create([
                'odoo_schedule_id' => $fullTimeSchedule->odoo_schedule_id,
                'odoo_detail_id' => 2001 + (2 * $weekday),
                'weekday' => $weekday,
                'day_period' => 'afternoon',
                'start' => '14:00:00',
                'end' => '18:00:00',
            ]);
        }
        
        return [$partTimeSchedule, $fullTimeSchedule];
    }
    
    /**
     * Create 5 users with specified roles (1 admin, 1 muted)
     */
    private function createUsers($departments)
    {
        $users = collect();
        
        // Create 5 users with pre-defined details
        $userDetails = [
            [
                'name' => 'John Developer',
                'email' => 'john@example.com',
                'department_id' => $departments[0]->odoo_department_id, // Engineering
                'is_admin' => true, // Admin user
                'muted_notifications' => false,
            ],
            [
                'name' => 'Jane Designer',
                'email' => 'jane@example.com',
                'department_id' => $departments[1]->odoo_department_id, // Design
                'is_admin' => false,
                'muted_notifications' => true, // Muted user
            ],
            [
                'name' => 'Sarah Engineer',
                'email' => 'sarah@example.com',
                'department_id' => $departments[0]->odoo_department_id, // Engineering
                'is_admin' => false,
                'muted_notifications' => false,
            ],
            [
                'name' => 'Michael Tester',
                'email' => 'michael@example.com',
                'department_id' => $departments[0]->odoo_department_id, // Engineering
                'is_admin' => false,
                'muted_notifications' => false,
            ],
            [
                'name' => 'Emily Manager',
                'email' => 'emily@example.com',
                'department_id' => $departments[2]->odoo_department_id, // Product
                'is_admin' => false,
                'muted_notifications' => false,
            ],
        ];
        
        foreach ($userDetails as $i => $details) {
            $user = User::factory()->create(array_merge($details, [
                'odoo_id' => 1001 + $i,
                'desktime_id' => 2001 + $i,
                'proofhub_id' => Str::uuid(),
                'systempin_id' => 3001 + $i,
            ]));
            
            $users->push($user);
        }
        
        return $users;
    }
    
    /**
     * Assign schedules to users (mix of 4h and 8h)
     */
    private function assignSchedulesToUsers($users, $schedules)
    {
        $partTimeSchedule = $schedules[0];
        $fullTimeSchedule = $schedules[1];
        
        // Assign 4h schedule to users 1 and 3
        foreach ([$users[1], $users[3]] as $user) {
            UserSchedule::factory()->current()->create([
                'user_id' => $user->id,
                'odoo_schedule_id' => $partTimeSchedule->odoo_schedule_id,
                'effective_from' => Carbon::parse('2024-12-01'), // From beginning of December 2024
            ]);
        }
        
        // Assign 8h schedule to users 0, 2, and 4
        foreach ([$users[0], $users[2], $users[4]] as $user) {
            UserSchedule::factory()->current()->create([
                'user_id' => $user->id,
                'odoo_schedule_id' => $fullTimeSchedule->odoo_schedule_id,
                'effective_from' => Carbon::parse('2024-12-01'), // From beginning of December 2024
            ]);
        }
    }
    
    /**
     * Create software development projects and tasks
     */
    private function createProjectsAndTasks()
    {
        $projects = collect();
        $tasks = collect();
        
        // Create 3 software development projects
        $projectData = [
            [
                'name' => 'API Development',
                'proofhub_project_id' => 10001,
                'tasks' => [
                    'Design REST API endpoints',
                    'Implement authentication middleware',
                    'Write API documentation',
                    'Create database migrations',
                    'Implement rate limiting'
                ]
            ],
            [
                'name' => 'Frontend Dashboard',
                'proofhub_project_id' => 10002,
                'tasks' => [
                    'Create UI mockups',
                    'Implement responsive layouts',
                    'Connect to backend API',
                    'Add data visualization components',
                    'Implement user settings panel'
                ]
            ],
            [
                'name' => 'Mobile App',
                'proofhub_project_id' => 10003,
                'tasks' => [
                    'Setup React Native environment',
                    'Implement authentication flow',
                    'Create offline data synchronization',
                    'Build profile management screens',
                    'Implement push notifications'
                ]
            ],
        ];
        
        foreach ($projectData as $data) {
            $project = Project::create([
                'name' => $data['name'],
                'proofhub_project_id' => $data['proofhub_project_id']
            ]);
            $projects->push($project);
            
            // Create tasks for this project
            foreach ($data['tasks'] as $i => $taskName) {
                $taskId = (int)($project->proofhub_project_id * 100 + $i + 1);
                $task = Task::create([
                    'proofhub_task_id' => $taskId,
                    'proofhub_project_id' => $project->proofhub_project_id,
                    'name' => $taskName,
                ]);
                $tasks->push($task);
            }
        }
        
        return [$projects, $tasks];
    }
    
    /**
     * Assign projects to users
     */
    private function assignProjectsToUsers($users, $projects)
    {
        // Assign all projects to admin user (user 0)
        foreach ($projects as $project) {
            $users[0]->projects()->attach($project->proofhub_project_id);
        }
        
        // User 1 (Jane) - Frontend Dashboard
        $users[1]->projects()->attach($projects[1]->proofhub_project_id);
        
        // User 2 (Sarah) - API Development and Mobile App
        $users[2]->projects()->attach([
            $projects[0]->proofhub_project_id,
            $projects[2]->proofhub_project_id
        ]);
        
        // User 3 (Michael) - API Development and Frontend Dashboard
        $users[3]->projects()->attach([
            $projects[0]->proofhub_project_id,
            $projects[1]->proofhub_project_id
        ]);
        
        // User 4 (Emily) - All projects (project manager)
        foreach ($projects as $project) {
            $users[4]->projects()->attach($project->proofhub_project_id);
        }
    }
    
    /**
     * Assign categories to users
     */
    private function assignCategoriesToUsers($users, $categories)
    {
        // Assign categories to users based on their role
        
        // John (Admin) - Gets all categories
        foreach ($categories as $category) {
            $users[0]->categories()->attach($category->odoo_category_id);
        }
        
        // Jane (Designer) - Design
        $users[1]->categories()->attach($categories[1]->odoo_category_id);
        
        // Sarah (Engineer) - Development, Testing, Documentation
        $users[2]->categories()->attach([
            $categories[0]->odoo_category_id,
            $categories[2]->odoo_category_id,
            $categories[3]->odoo_category_id
        ]);
        
        // Michael (Tester) - Testing, Documentation
        $users[3]->categories()->attach([
            $categories[2]->odoo_category_id,
            $categories[3]->odoo_category_id
        ]);
        
        // Emily (Manager) - Management, Development
        $users[4]->categories()->attach([
            $categories[0]->odoo_category_id,
            $categories[4]->odoo_category_id
        ]);
    }
    
    /**
     * Assign tasks to users
     */
    private function assignTasksToUsers($users, $tasks)
    {
        // Group tasks by project
        $apiTasks = $tasks->where('proofhub_project_id', 10001)->values(); // API Development
        $frontendTasks = $tasks->where('proofhub_project_id', 10002)->values(); // Frontend Dashboard
        $mobileTasks = $tasks->where('proofhub_project_id', 10003)->values(); // Mobile App
        
        // Only proceed if we have tasks for each project
        if ($apiTasks->isEmpty() || $frontendTasks->isEmpty() || $mobileTasks->isEmpty()) {
            return;
        }
        
        // John (Admin) - Gets a few tasks from each project
        $users[0]->tasks()->attach([
            $apiTasks->first()->proofhub_task_id, 
            $frontendTasks->first()->proofhub_task_id, 
            $mobileTasks->first()->proofhub_task_id
        ]);
        
        // Jane (Designer) - Gets frontend UI tasks
        $users[1]->tasks()->attach([
            $frontendTasks->first()->proofhub_task_id
        ]);
        if ($frontendTasks->count() >= 2) {
            $users[1]->tasks()->attach([$frontendTasks[1]->proofhub_task_id]);
        }
        if ($frontendTasks->count() >= 4) {
            $users[1]->tasks()->attach([$frontendTasks[3]->proofhub_task_id]);
        }
        
        // Sarah (Engineer) - Gets API and Mobile development tasks
        $sarah_tasks = [];
        if ($apiTasks->count() >= 2) $sarah_tasks[] = $apiTasks[1]->proofhub_task_id;
        if ($apiTasks->count() >= 4) $sarah_tasks[] = $apiTasks[3]->proofhub_task_id;
        if ($mobileTasks->count() >= 1) $sarah_tasks[] = $mobileTasks[0]->proofhub_task_id;
        if ($mobileTasks->count() >= 2) $sarah_tasks[] = $mobileTasks[1]->proofhub_task_id;
        if (!empty($sarah_tasks)) {
            $users[2]->tasks()->attach($sarah_tasks);
        }
        
        // Michael (Tester) - Gets testing related tasks
        $michael_tasks = [];
        if ($apiTasks->count() >= 1) $michael_tasks[] = $apiTasks[0]->proofhub_task_id;
        if ($apiTasks->count() >= 3) $michael_tasks[] = $apiTasks[2]->proofhub_task_id;
        if ($frontendTasks->count() >= 3) $michael_tasks[] = $frontendTasks[2]->proofhub_task_id;
        if ($frontendTasks->count() >= 4) $michael_tasks[] = $frontendTasks[3]->proofhub_task_id;
        if (!empty($michael_tasks)) {
            $users[3]->tasks()->attach($michael_tasks);
        }
        
        // Emily (Manager) - Gets management/planning tasks
        $emily_tasks = [];
        if ($apiTasks->count() >= 3) $emily_tasks[] = $apiTasks[2]->proofhub_task_id;
        if ($frontendTasks->count() >= 5) $emily_tasks[] = $frontendTasks[4]->proofhub_task_id;
        if ($mobileTasks->count() >= 5) $emily_tasks[] = $mobileTasks[4]->proofhub_task_id;
        if (!empty($emily_tasks)) {
            $users[4]->tasks()->attach($emily_tasks);
        }
    }
    
    /**
     * Create data for each user within the specified time frame
     */
    private function createTimeFrameData($users, $projects, $tasks, $leaveTypes, Carbon $from, Carbon $to)
    {
        echo "Creating data from {$from->toDateString()} to {$to->toDateString()}\n";
        
        // For each day in the specified time frame
        $currentDate = $from->copy()->startOfDay();
        
        // Force-create some Monday attendance records for testing
        $mondayCount = 0;
        
        while ($currentDate->lte($to)) {
            $isWeekend = $currentDate->isWeekend();
            $dayOfWeek = $currentDate->dayOfWeek;
            $isMonday = $dayOfWeek === 1;
            
            // Extra info for Mondays
            if ($isMonday) {
                $mondayCount++;
                echo "Processing MONDAY: {$currentDate->toDateString()} (day {$dayOfWeek})\n";
            }
            
            // Skip weekends for most data (except some random entries and ensure all Mondays)
            if ((!$isWeekend || $isMonday || rand(1, 100) <= 15)) {  // Always include Mondays, 15% chance of weekend data
                foreach ($users as $user) {
                    // Get user's schedule to determine workday hours
                    $userSchedule = $user->activeSchedule;
                    $isFullTime = false;
                    
                    if ($userSchedule) {
                        $schedule = Schedule::where('odoo_schedule_id', $userSchedule->odoo_schedule_id)->first();
                        $isFullTime = $schedule && $schedule->average_hours_day >= 8;
                    }
                    
                    // Skip if it's a weekend and user has 4h schedule (less likely to work)
                    // IMPORTANT: Never skip Mondays for testing
                    if ($isWeekend && !$isMonday && !$isFullTime && rand(1, 100) > 5) {
                        continue;
                    }
                    
                    // For 8-hour workday users: chance of having leave (increased to 15% for full-time)
                    // IMPORTANT: Never have leave on Mondays for testing
                    $hasLeave = false;
                    if (!$isMonday && $isFullTime && !$isWeekend && rand(1, 100) <= 15) {
                        $leaveType = $leaveTypes[array_rand($leaveTypes)];
                        $leaveDuration = rand(1, 3); // 1-3 days
                        
                        $leaveStart = $currentDate->copy();
                        $leaveEnd = $currentDate->copy()->addDays($leaveDuration - 1);
                        
                        UserLeave::create([
                            'odoo_leave_id' => 'OL' . rand(1000, 9999),
                            'user_id' => $user->id,
                            'type' => 'employee',
                            'status' => 'validate',
                            'start_date' => $leaveStart,
                            'end_date' => $leaveEnd,
                            'duration_days' => $leaveDuration,
                            'leave_type_id' => $leaveType->odoo_leave_type_id,
                        ]);
                        
                        $hasLeave = true;
                    }
                    
                    // If no leave, create attendance and time entries
                    if (!$hasLeave) {
                        // Create attendance record with random selection between office/remote
                        // 7-9 hours for full-time, 3.5-4.5 for part-time
                        $attendanceHours = $isFullTime ? rand(7 * 60, 9 * 60) / 60 : rand(210, 270) / 60;
                        $isRemote = rand(0, 1) === 1;
                        
                        if ($isMonday) {
                            echo "  Creating Monday attendance for user {$user->id} on {$currentDate->toDateString()}\n";
                        }
                        
                        $this->createAttendanceForDate($user, $currentDate, $attendanceHours, $isRemote);
                        
                        // Create time entries - 6-11 hours for full-time, 3-5 hours for part-time
                        $timeEntryHours = $isFullTime ? rand(6 * 60, 11 * 60) / 60 : rand(180, 300) / 60;
                        $this->createTimeEntriesForUser($user, $currentDate, $projects, $tasks, $timeEntryHours);
                    }
                }
            }
            
            $currentDate->addDay();
        }
        
        echo "Created data for {$mondayCount} Mondays\n";
    }
    
    /**
     * Create guaranteed leaves to ensure leave data exists
     */
    private function createGuaranteedLeaves($users, $leaveTypes, Carbon $from, Carbon $to)
    {
        // Create at least one scheduled leave for each user
        foreach ($users as $i => $user) {
            // Skip if user already has leaves
            if (UserLeave::where('user_id', $user->id)->exists()) {
                continue;
            }
            
            // Calculate leave date (different for each user to spread them out)
            $midpoint = $from->copy()->addDays(($to->diffInDays($from) / 2) + ($i * 3));
            
            // Skip weekends
            if ($midpoint->isWeekend()) {
                $midpoint->nextWeekday();
            }
            
            // Pick a random leave type
            $leaveType = $leaveTypes[array_rand($leaveTypes)];
            $leaveDuration = rand(1, 5); // 1-5 days
            
            // Create the leave
            UserLeave::create([
                'odoo_leave_id' => 'OL' . rand(10000, 99999),
                'user_id' => $user->id,
                'type' => 'employee',
                'status' => 'validate',
                'start_date' => $midpoint,
                'end_date' => $midpoint->copy()->addDays($leaveDuration - 1),
                'duration_days' => $leaveDuration,
                'leave_type_id' => $leaveType->odoo_leave_type_id,
            ]);
        }
    }
    
    /**
     * Create attendance record for a user on a specific date
     */
    private function createAttendanceForDate($user, $date, $hours, $isRemote)
    {
        // Always ensure date is in UTC and at start of day
        $utcDate = $date->copy()->startOfDay()->setTimezone('UTC');
        
        // Ensure isRemote is explicitly boolean
        $isRemote = (bool) $isRemote;
        
        if ($isRemote) {
            UserAttendance::factory()
                ->remote()
                ->create([
                    'user_id' => $user->id,
                    'date' => $utcDate,
                    'presence_seconds' => round($hours * 3600),
                    'is_remote' => true,
                ]);
        } else {
            $startHour = rand(8, 10);
            $endHour = (int)($startHour + $hours);
            
            $startTime = $date->copy()->setTime($startHour, rand(0, 30), 0);
            $endTime = $date->copy()->setTime($endHour, rand(0, 59), 0);
            
            UserAttendance::factory()
                ->inOffice()
                ->create([
                    'user_id' => $user->id,
                    'date' => $utcDate,
                    'start' => $startTime,
                    'end' => $endTime,
                    'presence_seconds' => round($hours * 3600),
                    'is_remote' => false,
                ]);
        }
    }
    
    /**
     * Create time entries for a user on a specific date
     */
    private function createTimeEntriesForUser($user, $date, $projects, $tasks, $totalHours)
    {
        // Get projects this user is assigned to
        $userProjects = $user->projects;
        
        if ($userProjects->isEmpty()) {
            return;
        }
        
        // Create 1-4 time entries for this day
        $numEntries = rand(1, 4);
        $remainingHours = $totalHours;
        $hoursPerEntry = $remainingHours / $numEntries;
        
        // Keep track of already used combinations
        $usedCombinations = [];
        
        foreach ($userProjects as $project) {
            // Find tasks for this project
            $projectTasks = $tasks->where('proofhub_project_id', $project->proofhub_project_id);
            
            if ($projectTasks->isEmpty() || $remainingHours <= 0) {
                continue;
            }
            
            // Randomly select 1-2 tasks per project
            $numTasks = min(rand(1, 2), $projectTasks->count());
            $selectedTasks = $projectTasks->random($numTasks);
            
            foreach ($selectedTasks as $task) {
                // Check if this combination has been used
                $key = $user->id . '_' . $project->proofhub_project_id . '_' . $task->proofhub_task_id . '_' . $date->format('Y-m-d');
                if (in_array($key, $usedCombinations) || $remainingHours <= 0) {
                    continue;
                }
                
                $usedCombinations[] = $key;
                
                // Calculate hours for this entry (between 0.5 and remaining hours, up to 4)
                $entryHours = min(rand(30, 240) / 60, $remainingHours, 4);
                $remainingHours -= $entryHours;
                
                // Generate a unique ID for the time entry using timestamp and random number
                $uniqueId = rand(100000, 999999);
                
                // Create development-related descriptions
                $descriptions = [
                    'Working on ' . $task->name,
                    'Implementing ' . strtolower($task->name),
                    'Finalizing ' . strtolower($task->name),
                    'Debugging issues with ' . strtolower($task->name),
                    'Testing ' . strtolower($task->name),
                    'Documentation for ' . strtolower($task->name)
                ];
                
                TimeEntry::create([
                    'proofhub_time_entry_id' => $uniqueId,
                    'user_id' => $user->id,
                    'proofhub_project_id' => $project->proofhub_project_id,
                    'proofhub_task_id' => $task->proofhub_task_id,
                    'status' => 'active',
                    'description' => $descriptions[array_rand($descriptions)],
                    'date' => $date,
                    'duration_seconds' => round($entryHours * 3600),
                    'proofhub_created_at' => $date->copy()->addHours(rand(1, 8)),
                ]);
            }
        }
        
        // If we still have hours left, add them to a random project without task
        if ($remainingHours > 0.5 && $userProjects->isNotEmpty()) {
            $project = $userProjects->random();
            
            // Generate a unique ID for the time entry using timestamp and random number
            $uniqueId = rand(100000, 999999);
            
            $genericDescriptions = [
                'Project planning',
                'Team meeting',
                'Code review',
                'Research and development',
                'Technical documentation',
                'Bug fixes'
            ];
            
            TimeEntry::create([
                'proofhub_time_entry_id' => $uniqueId,
                'user_id' => $user->id,
                'proofhub_project_id' => $project->proofhub_project_id,
                'proofhub_task_id' => null,
                'status' => 'active',
                'description' => $genericDescriptions[array_rand($genericDescriptions)],
                'date' => $date,
                'duration_seconds' => round($remainingHours * 3600),
                'proofhub_created_at' => $date->copy()->addHours(rand(1, 8)),
            ]);
        }
    }
} 