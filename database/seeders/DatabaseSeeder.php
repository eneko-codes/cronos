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
// It will create users with different work schedules (4h and 8h) and assign them to departments, projects, and tasks.
// It generates realistic attendance data and time entries based on each user's schedule.

class DatabaseSeeder extends Seeder
{
    private $departments;
    private $categories;
    private $leaveTypes;
    private $projects;
    private $tasks;
    private $schedules;
    
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "\n\n";
        echo "╔═══════════════════════════════════════════════════════════╗\n";
        echo "║            CRONOS DEMO DATABASE SEEDER STARTED            ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n\n";

        // Reset auto increment values for clean sequence
        echo "📊 Cleaning database and resetting auto-increment values...\n";
        $this->resetAutoIncrement();
        echo "✅ Database cleaned successfully!\n\n";
        
        // Hardcoded time frame
        $from = Carbon::now()->subMonth()->startOfDay();
        $to = Carbon::now()->endOfDay();
        echo "📅 Generating data for time period: {$from->format('Y-m-d')} to {$to->format('Y-m-d')}\n\n";
        
        // Create core data that will be used by all users
        echo "🏢 Creating core data (departments, categories, leave types)...\n";
        $this->createCoreData();
        echo "✅ Core data created successfully!\n\n";
        
        // Create users and assign core data
        echo "👥 Creating users with predefined roles...\n";
        $users = $this->createUsers();
        echo "✅ Created " . count($users) . " users successfully!\n\n";
        
        // Create and assign 8-hour work schedule data (full-time)
        echo "⏰ Creating full-time (8h) schedules and data for users...\n";
        $fullTimeUsers = $this->createFullTimeData($users, 0, 2, 4);
        echo "✅ Full-time schedules assigned to " . count($fullTimeUsers) . " users!\n\n";
        
        // Create and assign 4-hour work schedule data (part-time)
        echo "⏰ Creating part-time (4h) schedules and data for users...\n";
        $partTimeUsers = $this->createPartTimeData($users, 1, 3);
        echo "✅ Part-time schedules assigned to " . count($partTimeUsers) . " users!\n\n";
        
        // Create additional leaves to ensure there's always some leave data
        echo "🏖️ Creating guaranteed leave records for all users...\n";
        $this->createGuaranteedLeaves($users, $from, $to);
        echo "✅ Leave records created successfully!\n\n";
        
        echo "╔═══════════════════════════════════════════════════════════╗\n";
        echo "║            CRONOS DEMO DATABASE SEEDER COMPLETED          ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n\n";
        echo "🎉 Seeding completed successfully! The database now contains demo data for testing.\n";
        echo "🔍 Login with any user: john@example.com, jane@example.com, etc. (password: password)\n";
        echo "👑 Admin account: john@example.com (password: password)\n\n";
    }
    
    /**
     * Reset auto increment values for clean sequence
     */
    private function resetAutoIncrement(): void
    {
        // For SQLite, we need to use different commands than MySQL
        DB::statement('PRAGMA foreign_keys = OFF;');
        
        $tables = [
            'users',
            'departments',
            'categories',
            'category_user',
            'leave_types',
            'projects',
            'project_user',
            'schedules',
            'schedule_details',
            'tasks',
            'task_user',
            'time_entries',
            'user_attendances',
            'user_leaves',
            'user_schedules'
        ];
        
        foreach ($tables as $table) {
            // For SQLite, we need to delete all data instead of using TRUNCATE
            DB::table($table)->delete();
            
            // SQLite auto-increment is handled with "delete from sqlite_sequence"
            DB::statement("DELETE FROM sqlite_sequence WHERE name = '{$table}';");
        }
        
        DB::statement('PRAGMA foreign_keys = ON;');
    }
    
    /**
     * Create core data that is shared across users (departments, categories, leave types, projects, tasks, schedules)
     */
    private function createCoreData(): void
    {
        // Create departments
        echo "  📊 Creating departments...\n";
        $this->departments = [
            Department::factory()->create(['name' => 'Engineering']),
            Department::factory()->create(['name' => 'Design']),
            Department::factory()->create(['name' => 'Product'])
        ];
        echo "    ✓ Created " . count($this->departments) . " departments\n";
        
        // Create categories
        echo "  🏷️ Creating categories...\n";
        $this->categories = [
            Category::factory()->create(['name' => 'Development']),
            Category::factory()->create(['name' => 'Design']),
            Category::factory()->create(['name' => 'Testing']),
            Category::factory()->create(['name' => 'Documentation']),
            Category::factory()->create(['name' => 'Management'])
        ];
        echo "    ✓ Created " . count($this->categories) . " categories\n";
        
        // Create leave types
        echo "  🏖️ Creating leave types...\n";
        $this->leaveTypes = [
            LeaveType::factory()->create(['name' => 'Vacation']),
            LeaveType::factory()->create(['name' => 'Sick Leave']),
            LeaveType::factory()->create(['name' => 'Personal Leave']),
            LeaveType::factory()->create(['name' => 'Parental Leave']),
            LeaveType::factory()->create(['name' => 'Bereavement Leave'])
        ];
        echo "    ✓ Created " . count($this->leaveTypes) . " leave types\n";
        
        // Create work schedules
        echo "  ⏰ Creating schedule templates...\n";
        $this->createSchedules();
        echo "    ✓ Created 2 schedule templates (4h and 8h workdays)\n";
        
        // Create projects and tasks
        echo "  📋 Creating projects and tasks...\n";
        $this->createProjectsAndTasks();
        echo "    ✓ Created " . $this->projects->count() . " projects with " . $this->tasks->count() . " tasks\n";
    }
    
    /**
     * Create work schedules (4h and 8h workdays)
     */
    private function createSchedules(): void
    {
        // Create part-time (4h) schedule
        $partTimeSchedule = Schedule::factory()->create([
            'odoo_schedule_id' => 101,
            'description' => 'Part-Time, 4h/day',
            'average_hours_day' => 4,
        ]);
        
        // Create full-time (8h) schedule
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
        
        $this->schedules = [
            'part_time' => $partTimeSchedule,
            'full_time' => $fullTimeSchedule
        ];
    }
    
    /**
     * Create projects and tasks for users to work on
     */
    private function createProjectsAndTasks(): void
    {
        $projects = collect();
        $tasks = collect();
        
        // Create software development projects
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
            $project = Project::factory()->create([
                'name' => $data['name'],
                'proofhub_project_id' => $data['proofhub_project_id']
            ]);
            $projects->push($project);
            
            // Create tasks for this project
            foreach ($data['tasks'] as $i => $taskName) {
                $taskId = (int)($project->proofhub_project_id * 100 + $i + 1);
                $task = Task::factory()->create([
                    'proofhub_task_id' => $taskId,
                    'proofhub_project_id' => $project->proofhub_project_id,
                    'name' => $taskName,
                ]);
                $tasks->push($task);
            }
        }
        
        $this->projects = $projects;
        $this->tasks = $tasks;
    }
    
    /**
     * Method 1: Create users with predefined roles and details
     */
    private function createUsers(): array
    {
        $users = [];
        
        // Create 5 users with pre-defined details
        $userDetails = [
            [
                'name' => 'John Developer',
                'email' => 'john@example.com',
                'department_id' => $this->departments[0]->odoo_department_id, // Engineering
                'is_admin' => true, // Admin user
                'muted_notifications' => false,
            ],
            [
                'name' => 'Jane Designer',
                'email' => 'jane@example.com',
                'department_id' => $this->departments[1]->odoo_department_id, // Design
                'is_admin' => false,
                'muted_notifications' => true, // Muted user
            ],
            [
                'name' => 'Sarah Engineer',
                'email' => 'sarah@example.com',
                'department_id' => $this->departments[0]->odoo_department_id, // Engineering
                'is_admin' => false,
                'muted_notifications' => false,
            ],
            [
                'name' => 'Michael Tester',
                'email' => 'michael@example.com',
                'department_id' => $this->departments[0]->odoo_department_id, // Engineering
                'is_admin' => false,
                'muted_notifications' => false,
            ],
            [
                'name' => 'Emily Manager',
                'email' => 'emily@example.com',
                'department_id' => $this->departments[2]->odoo_department_id, // Product
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
            
            $users[] = $user;
        }
        
        return $users;
    }
    
    /**
     * Method 2: Create 8-hour workday schedule (full-time) with all associated data
     */
    private function createFullTimeData(array $users, int ...$userIndices)
    {
        $fullTimeUsers = [];
        $fullTimeSchedule = $this->schedules['full_time'];
        
        // Assign users to full-time schedule (8h)
        foreach ($userIndices as $index) {
            if (isset($users[$index])) {
                $user = $users[$index];
                $fullTimeUsers[] = $user;
                
                // Assign 8h schedule
                UserSchedule::factory()->current()->create([
                    'user_id' => $user->id,
                    'odoo_schedule_id' => $fullTimeSchedule->odoo_schedule_id,
                    'effective_from' => Carbon::parse('2024-12-01'),
                ]);
                
                // Assign projects based on user role
                $this->assignProjectsToUser($user);
                
                // Assign categories based on user role
                $this->assignCategoriesToUser($user);
                
                // Assign tasks based on user role
                $this->assignTasksToUser($user);
            }
        }
        
        // Create time data (attendance, time entries) for these users
        $this->createTimeData($fullTimeUsers, $fullTimeSchedule->average_hours_day);
        
        return $fullTimeUsers;
    }
    
    /**
     * Method 3: Create 4-hour workday schedule (part-time) with all associated data
     */
    private function createPartTimeData(array $users, int ...$userIndices)
    {
        $partTimeUsers = [];
        $partTimeSchedule = $this->schedules['part_time'];
        
        // Assign users to part-time schedule (4h)
        foreach ($userIndices as $index) {
            if (isset($users[$index])) {
                $user = $users[$index];
                $partTimeUsers[] = $user;
                
                // Assign 4h schedule
                UserSchedule::factory()->current()->create([
                    'user_id' => $user->id,
                    'odoo_schedule_id' => $partTimeSchedule->odoo_schedule_id,
                    'effective_from' => Carbon::parse('2024-12-01'),
                ]);
                
                // Assign projects based on user role
                $this->assignProjectsToUser($user);
                
                // Assign categories based on user role
                $this->assignCategoriesToUser($user);
                
                // Assign tasks based on user role
                $this->assignTasksToUser($user);
            }
        }
        
        // Create time data (attendance, time entries) for these users
        $this->createTimeData($partTimeUsers, $partTimeSchedule->average_hours_day);
        
        return $partTimeUsers;
    }
    
    /**
     * Assign projects based on user role
     */
    private function assignProjectsToUser(User $user): void
    {
        if ($user->name === 'John Developer' || $user->name === 'Emily Manager') {
            // Admin and Manager get all projects
            foreach ($this->projects as $project) {
                $user->projects()->attach($project->proofhub_project_id);
            }
        } elseif ($user->name === 'Jane Designer') {
            // Designer gets Frontend
            $user->projects()->attach($this->projects[1]->proofhub_project_id);
        } elseif ($user->name === 'Sarah Engineer') {
            // Engineer gets API and Mobile
            $user->projects()->attach([
                $this->projects[0]->proofhub_project_id,
                $this->projects[2]->proofhub_project_id
            ]);
        } elseif ($user->name === 'Michael Tester') {
            // Tester gets API and Frontend
            $user->projects()->attach([
                $this->projects[0]->proofhub_project_id,
                $this->projects[1]->proofhub_project_id
            ]);
        }
    }
    
    /**
     * Assign categories based on user role
     */
    private function assignCategoriesToUser(User $user): void
    {
        if ($user->name === 'John Developer') {
            // Admin gets all categories
            foreach ($this->categories as $category) {
                $user->categories()->attach($category->odoo_category_id);
            }
        } elseif ($user->name === 'Jane Designer') {
            // Designer gets Design
            $user->categories()->attach($this->categories[1]->odoo_category_id);
        } elseif ($user->name === 'Sarah Engineer') {
            // Engineer gets Development, Testing, Documentation
            $user->categories()->attach([
                $this->categories[0]->odoo_category_id,
                $this->categories[2]->odoo_category_id,
                $this->categories[3]->odoo_category_id
            ]);
        } elseif ($user->name === 'Michael Tester') {
            // Tester gets Testing, Documentation
            $user->categories()->attach([
                $this->categories[2]->odoo_category_id,
                $this->categories[3]->odoo_category_id
            ]);
        } elseif ($user->name === 'Emily Manager') {
            // Manager gets Management, Development
            $user->categories()->attach([
                $this->categories[0]->odoo_category_id,
                $this->categories[4]->odoo_category_id
            ]);
        }
    }
    
    /**
     * Assign tasks based on user role
     */
    private function assignTasksToUser(User $user): void
    {
        // Group tasks by project
        $apiTasks = $this->tasks->where('proofhub_project_id', 10001)->values(); // API Development
        $frontendTasks = $this->tasks->where('proofhub_project_id', 10002)->values(); // Frontend Dashboard
        $mobileTasks = $this->tasks->where('proofhub_project_id', 10003)->values(); // Mobile App
        
        // Only proceed if we have tasks for each project
        if ($apiTasks->isEmpty() || $frontendTasks->isEmpty() || $mobileTasks->isEmpty()) {
            return;
        }
        
        if ($user->name === 'John Developer') {
            // Admin gets a task from each project
            $user->tasks()->attach([
            $apiTasks->first()->proofhub_task_id, 
            $frontendTasks->first()->proofhub_task_id, 
            $mobileTasks->first()->proofhub_task_id
        ]);
        } elseif ($user->name === 'Jane Designer') {
            // Designer gets frontend UI tasks
            $designerTasks = [];
            if ($frontendTasks->count() >= 1) $designerTasks[] = $frontendTasks[0]->proofhub_task_id;
            if ($frontendTasks->count() >= 2) $designerTasks[] = $frontendTasks[1]->proofhub_task_id;
            if ($frontendTasks->count() >= 4) $designerTasks[] = $frontendTasks[3]->proofhub_task_id;
            
            if (!empty($designerTasks)) {
                $user->tasks()->attach($designerTasks);
            }
        } elseif ($user->name === 'Sarah Engineer') {
            // Engineer gets API and Mobile development tasks
            $engineerTasks = [];
            if ($apiTasks->count() >= 2) $engineerTasks[] = $apiTasks[1]->proofhub_task_id;
            if ($apiTasks->count() >= 4) $engineerTasks[] = $apiTasks[3]->proofhub_task_id;
            if ($mobileTasks->count() >= 1) $engineerTasks[] = $mobileTasks[0]->proofhub_task_id;
            if ($mobileTasks->count() >= 2) $engineerTasks[] = $mobileTasks[1]->proofhub_task_id;
            
            if (!empty($engineerTasks)) {
                $user->tasks()->attach($engineerTasks);
            }
        } elseif ($user->name === 'Michael Tester') {
            // Tester gets testing related tasks
            $testerTasks = [];
            if ($apiTasks->count() >= 1) $testerTasks[] = $apiTasks[0]->proofhub_task_id;
            if ($apiTasks->count() >= 3) $testerTasks[] = $apiTasks[2]->proofhub_task_id;
            if ($frontendTasks->count() >= 3) $testerTasks[] = $frontendTasks[2]->proofhub_task_id;
            if ($frontendTasks->count() >= 4) $testerTasks[] = $frontendTasks[3]->proofhub_task_id;
            
            if (!empty($testerTasks)) {
                $user->tasks()->attach($testerTasks);
            }
        } elseif ($user->name === 'Emily Manager') {
            // Manager gets management/planning tasks
            $managerTasks = [];
            if ($apiTasks->count() >= 3) $managerTasks[] = $apiTasks[2]->proofhub_task_id;
            if ($frontendTasks->count() >= 5) $managerTasks[] = $frontendTasks[4]->proofhub_task_id;
            if ($mobileTasks->count() >= 5) $managerTasks[] = $mobileTasks[4]->proofhub_task_id;
            
            if (!empty($managerTasks)) {
                $user->tasks()->attach($managerTasks);
            }
        }
    }
    
    /**
     * Create time data (attendance, time entries) for users
     */
    private function createTimeData(array $users, float $scheduleHours): void
    {
        $from = Carbon::now()->subMonth()->startOfDay();
        $to = Carbon::now()->endOfDay();
        
        $scheduleType = $scheduleHours >= 8 ? "full-time (8h)" : "part-time (4h)";
        echo "  🕒 Creating attendance and time entry data for " . count($users) . " " . $scheduleType . " users...\n";
        echo "    ⏳ Processing " . $from->diffInDays($to) + 1 . " days of data, please wait...\n";
        
        // Track progress counters
        $daysProcessed = 0;
        $totalDays = $from->diffInDays($to) + 1;
        $attendanceRecords = 0;
        $timeEntryRecords = 0;
        $leaveRecords = 0;
        $mondayCount = 0;
        
        // For each day in the specified time frame
        $currentDate = $from->copy()->startOfDay();
        
        while ($currentDate->lte($to)) {
            $daysProcessed++;
            $isWeekend = $currentDate->isWeekend();
            $isMonday = $currentDate->dayOfWeek === 1;
            
            // Extra info for Mondays
            if ($isMonday) {
                $mondayCount++;
            }
            
            // Skip weekends for most data (except Mondays and random entries)
            if ((!$isWeekend || $isMonday || rand(1, 100) <= 15)) {
                foreach ($users as $user) {
                    // Skip if it's a weekend (unless it's Monday or the user is full-time)
                    $isFullTime = $scheduleHours >= 8;
                    if ($isWeekend && !$isMonday && !$isFullTime && rand(1, 100) > 5) {
                        continue;
                    }
                    
                    // For users: chance of having leave (never on Mondays for testing)
                    $hasLeave = false;
                    if (!$isMonday && !$isWeekend && rand(1, 100) <= 15) {
                        $leaveType = $this->leaveTypes[array_rand($this->leaveTypes)];
                        
                        // 20% chance of creating a half-day leave
                        $isHalfDay = rand(1, 100) <= 20;
                        
                        if ($isHalfDay) {
                            // Decide if it's morning or afternoon
                            $isMorning = rand(0, 1) === 1;
                            
                            // Randomly decide the status (80% approved, 10% refused, 10% first validation)
                            $leaveStatus = 'validate';
                            $randomStatus = rand(1, 100);
                            if ($randomStatus > 90) {
                                $leaveStatus = 'refuse';
                            } elseif ($randomStatus > 80) {
                                $leaveStatus = 'validate1';
                            }
                            
                            if ($isMorning) {
                                UserLeave::factory()->halfDayMorning()->create([
                                    'user_id' => $user->id,
                                    'type' => 'employee',
                                    'status' => $leaveStatus,
                                    'start_date' => $currentDate->copy(),
                                    'end_date' => $currentDate->copy(),
                                    'leave_type_id' => $leaveType->odoo_leave_type_id,
                                ]);
                            } else {
                                UserLeave::factory()->halfDayAfternoon()->create([
                                    'user_id' => $user->id,
                                    'type' => 'employee',
                                    'status' => $leaveStatus,
                                    'start_date' => $currentDate->copy(),
                                    'end_date' => $currentDate->copy(),
                                    'leave_type_id' => $leaveType->odoo_leave_type_id,
                                ]);
                            }
                        } else {
                            // Create a full-day leave as before
                            $leaveDuration = rand(1, 3); // 1-3 days
                            
                            // Randomly decide the status (80% approved, 10% refused, 10% first validation)
                            $leaveStatus = 'validate';
                            $randomStatus = rand(1, 100);
                            if ($randomStatus > 90) {
                                $leaveStatus = 'refuse';
                            } elseif ($randomStatus > 80) {
                                $leaveStatus = 'validate1';
                            }
                            
                            $leaveStart = $currentDate->copy();
                            $leaveEnd = $currentDate->copy()->addDays($leaveDuration - 1);
                            
                            UserLeave::factory()->create([
                                'user_id' => $user->id,
                                'type' => 'employee',
                                'status' => $leaveStatus,
                                'start_date' => $leaveStart,
                                'end_date' => $leaveEnd,
                                'duration_days' => $leaveDuration,
                                'leave_type_id' => $leaveType->odoo_leave_type_id,
                            ]);
                        }
                        
                        $hasLeave = true;
                        $leaveRecords++;
                    }
                    
                    // If no leave, create attendance and time entries
                    if (!$hasLeave) {
                        // Calculate attendance hours with ±15% variation
                        $variation = $scheduleHours * (rand(-15, 15) / 100);
                        $attendanceHours = $scheduleHours + $variation;
                        
                        // Ensure hours stay within ±15% of schedule hours
                        $minHours = $scheduleHours * 0.85;
                        $maxHours = $scheduleHours * 1.15;
                        $attendanceHours = max($minHours, min($maxHours, $attendanceHours));
                        
                        $isRemote = rand(0, 1) === 1;
                        
                        $this->createAttendanceForDate($user, $currentDate, $attendanceHours, $isRemote);
                        $attendanceRecords++;
                        
                        // Create time entries with slight variation from attendance
                        $variation = $scheduleHours * (rand(-15, 15) / 100);
                        $timeEntryHours = $scheduleHours + $variation;
                        
                        // Ensure hours stay within ±15% of schedule hours
                        $timeEntryHours = max($minHours, min($maxHours, $timeEntryHours));
                        
                        $newTimeEntries = $this->createTimeEntriesForUser($user, $currentDate, $timeEntryHours);
                        $timeEntryRecords += $newTimeEntries;
                    }
                }
            }
            
            // Show progress every 25% of days processed
            if ($daysProcessed % max(1, intval($totalDays / 4)) === 0 || $daysProcessed === $totalDays) {
                $percentage = round(($daysProcessed / $totalDays) * 100);
                echo "    ↳ {$percentage}% complete: processed " . $daysProcessed . " days of data\n";
            }
            
            $currentDate->addDay();
        }
        
        echo "    ✓ Created " . $attendanceRecords . " attendance records\n";
        echo "    ✓ Created " . $timeEntryRecords . " time entry records\n";
        echo "    ✓ Created " . $leaveRecords . " leave records\n";
        echo "    ✓ Included data for " . $mondayCount . " Mondays\n";
    }
    
    /**
     * Create attendance record for a user on a specific date
     */
    private function createAttendanceForDate($user, $date, $hours, $isRemote): void
    {
        // Always ensure date is in UTC and at start of day
        $utcDate = $date->copy()->startOfDay()->setTimezone('UTC');
        
        // Ensure isRemote is explicitly boolean
        $isRemote = (bool) $isRemote;
        
        // Calculate exact presence seconds
        $presenceSeconds = (int) round($hours * 3600);
        
        if ($isRemote) {
            UserAttendance::factory()
                ->remote()
                ->create([
                    'user_id' => $user->id,
                    'date' => $utcDate,
                    'presence_seconds' => $presenceSeconds,
                    'is_remote' => true,
                ]);
        } else {
            $startHour = rand(8, 10);
            $startMinute = rand(0, 30);
            
            // For more accurate time calculations
            $totalMinutes = $hours * 60;
            $endHour = $startHour + floor($totalMinutes / 60);
            $endMinute = $startMinute + ($totalMinutes % 60);
            
            // Adjust if minutes overflow
            if ($endMinute >= 60) {
                $endHour++;
                $endMinute -= 60;
            }
            
            $startTime = $date->copy()->setTime($startHour, $startMinute, 0);
            $endTime = $date->copy()->setTime($endHour, $endMinute, 0);
            
            UserAttendance::factory()
                ->create([
                    'user_id' => $user->id,
                    'date' => $utcDate,
                    'start' => $startTime,
                    'end' => $endTime,
                    'presence_seconds' => $presenceSeconds,
                    'is_remote' => false,
                ]);
        }
    }
    
    /**
     * Create time entries for a user on a specific date
     */
    private function createTimeEntriesForUser($user, $date, $totalHours): int
    {
        // Get projects this user is assigned to
        $userProjects = $user->projects;
        
        if ($userProjects->isEmpty()) {
            return 0;
        }
        
        // Create 1-4 time entries for this day
        $numEntries = min(rand(1, 4), $userProjects->count());
        $remainingHours = $totalHours;
        $createdEntries = 0;
        
        // Keep track of already used combinations
        $usedCombinations = [];
        
        // Track total seconds to ensure we match the intended total exactly
        $totalSeconds = (int) round($totalHours * 3600);
        $usedSeconds = 0;
        
        foreach ($userProjects as $project) {
            // Find tasks for this project
            $projectTasks = $this->tasks->where('proofhub_project_id', $project->proofhub_project_id);
            
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
                
                // Calculate seconds for this entry - this is key for accuracy
                $entrySeconds = (int) round($entryHours * 3600);
                
                // Generate a unique ID for the time entry
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
                
                TimeEntry::factory()->create([
                    'proofhub_time_entry_id' => $uniqueId,
                    'user_id' => $user->id,
                    'proofhub_project_id' => $project->proofhub_project_id,
                    'proofhub_task_id' => $task->proofhub_task_id,
                    'status' => 'active',
                    'description' => $descriptions[array_rand($descriptions)],
                    'date' => $date,
                    'duration_seconds' => $entrySeconds,
                    'proofhub_created_at' => $date->copy()->addHours(rand(1, 8)),
                ]);
                
                $createdEntries++;
                $usedSeconds += $entrySeconds;
            }
        }
        
        // If we still have hours left, add them to a random project without task
        if ($remainingHours > 0.1 && $userProjects->isNotEmpty()) {
            $project = $userProjects->random();
            
            // Generate a unique ID for the time entry
            $uniqueId = rand(100000, 999999);
            
            $genericDescriptions = [
                'Project planning',
                'Team meeting',
                'Code review',
                'Research and development',
                'Technical documentation',
                'Bug fixes'
            ];
            
            // Ensure we match the total exactly
            $remainingSeconds = $totalSeconds - $usedSeconds;
            
            TimeEntry::factory()->create([
                'proofhub_time_entry_id' => $uniqueId,
                'user_id' => $user->id,
                'proofhub_project_id' => $project->proofhub_project_id,
                'proofhub_task_id' => null,
                'status' => 'active',
                'description' => $genericDescriptions[array_rand($genericDescriptions)],
                'date' => $date,
                'duration_seconds' => $remainingSeconds,
                'proofhub_created_at' => $date->copy()->addHours(rand(1, 8)),
            ]);
            
            $createdEntries++;
        }
        
        return $createdEntries;
    }
    
    /**
     * Create guaranteed leaves to ensure leave data exists
     */
    private function createGuaranteedLeaves($users, Carbon $from, Carbon $to): void
    {
        echo "  🏖️ Creating additional leave records...\n";
        $leaveCount = 0;
        
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
            $leaveType = $this->leaveTypes[array_rand($this->leaveTypes)];
            
            // Add some variety in leave statuses (for testing UI indicators)
            $randomStatus = rand(1, 100);
            $statusFactory = match(true) {
                $randomStatus <= 10 => 'refused',  // 10% refused
                $randomStatus <= 30 => 'pending',  // 20% pending
                default => 'approved',             // 70% approved
            };
            
            // 25% chance of creating a half-day leave for better testing variety
            if (rand(1, 100) <= 25) {
                // Half-day leave
                $isMorning = rand(0, 1) === 1;
                
                if ($isMorning) {
                    // Morning half-day leave
                    UserLeave::factory()
                        ->$statusFactory()
                        ->halfDayMorning()
                        ->create([
                            'user_id' => $user->id,
                            'type' => 'employee',
                            'start_date' => $midpoint->copy()->startOfDay(),
                            'leave_type_id' => $leaveType->odoo_leave_type_id,
                        ]);
                } else {
                    // Afternoon half-day leave
                    UserLeave::factory()
                        ->$statusFactory()
                        ->halfDayAfternoon()
                        ->create([
                            'user_id' => $user->id,
                            'type' => 'employee',
                            'start_date' => $midpoint->copy()->startOfDay(),
                            'leave_type_id' => $leaveType->odoo_leave_type_id,
                        ]);
                }
            } else {
                // Full-day leave
                $leaveDuration = rand(1, 5); // 1-5 days
                
                if ($leaveDuration == 1) {
                    // Single day leave - use the fullDay method
                    UserLeave::factory()
                        ->$statusFactory()
                        ->fullDay()
                        ->create([
                            'user_id' => $user->id,
                            'type' => 'employee',
                            'start_date' => $midpoint->copy()->startOfDay(),
                            'leave_type_id' => $leaveType->odoo_leave_type_id,
                        ]);
                } else {
                    // Multi-day leave - use forDateRange
                    $leaveEnd = $midpoint->copy()->addDays($leaveDuration - 1);
                    UserLeave::factory()
                        ->$statusFactory()
                        ->forDateRange($midpoint, $leaveEnd)
                        ->create([
                            'user_id' => $user->id,
                            'type' => 'employee',
                            'leave_type_id' => $leaveType->odoo_leave_type_id,
                        ]);
                }
            }
            
            $leaveCount++;
        }
        
        echo "    ✓ Created " . $leaveCount . " leave records (approved, pending, and refused statuses)\n";
    }
} 