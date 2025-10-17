<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\UserLeave;
use App\Models\UserAttendance;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Test suite to verify timezone handling is correct.
 * 
 * Architecture:
 * - Database stores as UTC (timestamptz columns)
 * - App timezone is Europe/Madrid for display
 * - API data parsed with correct source timezone
 */
class TimezoneHandlingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that Odoo leave data (UTC) is stored correctly as UTC.
     */
    public function test_odoo_leave_stores_as_utc(): void
    {
        // Simulate Odoo API data: 09:00 UTC (they explicitly state UTC in docs)
        $utcTime = '2024-07-01 09:00:00';
        
        $leave = UserLeave::create([
            'odoo_leave_id' => 999,
            'type' => 'employee',
            'status' => 'validate',
            'start_date' => Carbon::parse($utcTime, 'UTC'),
            'end_date' => Carbon::parse('2024-07-05 17:00:00', 'UTC'),
            'duration_days' => 5.0,
        ]);

        // Verify stored as UTC in database
        $stored = $leave->fresh();
        $this->assertEquals(
            '2024-07-01 09:00:00',
            $stored->start_date->timezone('UTC')->format('Y-m-d H:i:s'),
            'Leave should be stored as 09:00 UTC'
        );

        // Verify displays in Madrid time (UTC+2 in summer, UTC+1 in winter)
        // In July (summer), Madrid is UTC+2
        $this->assertEquals(
            '2024-07-01 11:00:00',
            $stored->start_date->format('Y-m-d H:i:s'),
            'Leave should display as 11:00 in Madrid time (UTC+2)'
        );
    }

    /**
     * Test what happens WITHOUT explicit timezone parsing (to prove it's needed).
     */
    public function test_without_explicit_parsing_causes_error(): void
    {
        // Simulate Odoo API data WITHOUT timezone suffix
        $utcTime = '2024-07-01 09:00:00'; // This is UTC from Odoo
        
        // WITHOUT explicit UTC parsing - Carbon assumes app timezone
        $leave = UserLeave::create([
            'odoo_leave_id' => 998,
            'type' => 'employee',
            'status' => 'validate',
            'start_date' => $utcTime, // ❌ No timezone specified
            'end_date' => '2024-07-05 17:00:00',
            'duration_days' => 5.0,
        ]);

        $stored = $leave->fresh();
        
        // This will be WRONG - Carbon assumed Madrid time, converted to UTC
        // 09:00 Madrid → 07:00 UTC (2 hour difference in summer)
        $storedUtc = $stored->start_date->timezone('UTC')->format('Y-m-d H:i:s');
        
        $this->assertEquals(
            '2024-07-01 07:00:00',
            $storedUtc,
            'WITHOUT explicit parsing, 09:00 Madrid is stored as 07:00 UTC - WRONG!'
        );
        
        // This is NOT what Odoo sent (they sent 09:00 UTC, not 07:00 UTC)
        $this->assertNotEquals(
            '2024-07-01 09:00:00',
            $storedUtc,
            'Proves that without explicit timezone, we store wrong time'
        );
    }

    /**
     * Test DeskTime attendance (Europe/London timezone).
     */
    public function test_desktime_attendance_london_timezone(): void
    {
        $user = User::factory()->create();
        
        // DeskTime API returns time in Europe/London (per company timezone_identifier)
        $londonTime = '2024-07-01 09:00:00'; // 9 AM London time
        
        $attendance = UserAttendance::create([
            'user_id' => $user->id,
            'date' => '2024-07-01',
            'clock_in' => Carbon::parse($londonTime, 'Europe/London'),
            'clock_out' => null,
            'duration_seconds' => 0,
            'is_remote' => true,
        ]);

        $stored = $attendance->fresh();
        
        // Verify stored as UTC (London in summer is UTC+1, so 09:00 London = 08:00 UTC)
        $this->assertEquals(
            '2024-07-01 08:00:00',
            $stored->clock_in->timezone('UTC')->format('Y-m-d H:i:s'),
            'DeskTime 09:00 London should store as 08:00 UTC'
        );
        
        // Verify displays in Madrid time (Madrid is UTC+2, London is UTC+1)
        // So Madrid is 1 hour ahead of London
        $this->assertEquals(
            '2024-07-01 10:00:00',
            $stored->clock_in->format('Y-m-d H:i:s'),
            'Should display as 10:00 in Madrid (1 hour ahead of London)'
        );
    }

    /**
     * Test SystemPin attendance (Europe/Madrid local time).
     */
    public function test_systempin_attendance_madrid_timezone(): void
    {
        $user = User::factory()->create();
        
        // SystemPin is a physical machine in Madrid office, returns local time
        $madridTime = '2024-07-01 09:00:00'; // 9 AM Madrid local time
        
        $attendance = UserAttendance::create([
            'user_id' => $user->id,
            'date' => '2024-07-01',
            'clock_in' => Carbon::parse($madridTime, 'Europe/Madrid'),
            'clock_out' => null,
            'duration_seconds' => 0,
            'is_remote' => false,
        ]);

        $stored = $attendance->fresh();
        
        // Verify stored as UTC (Madrid in summer is UTC+2, so 09:00 Madrid = 07:00 UTC)
        $this->assertEquals(
            '2024-07-01 07:00:00',
            $stored->clock_in->timezone('UTC')->format('Y-m-d H:i:s'),
            'SystemPin 09:00 Madrid should store as 07:00 UTC'
        );
        
        // Verify displays in Madrid time (same as source)
        $this->assertEquals(
            '2024-07-01 09:00:00',
            $stored->clock_in->format('Y-m-d H:i:s'),
            'Should display as 09:00 in Madrid (same as local time)'
        );
    }

    /**
     * Test ProofHub time entry (ISO 8601 with timezone).
     */
    public function test_proofhub_time_entry_iso8601(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        
        // ProofHub sends ISO 8601 with timezone suffix
        $iso8601 = '2024-07-01T09:00:00+00:00'; // UTC with explicit timezone
        
        $entry = TimeEntry::create([
            'proofhub_time_entry_id' => 999,
            'user_id' => $user->id,
            'proofhub_project_id' => $project->proofhub_project_id,
            'date' => '2024-07-01',
            'duration_seconds' => 3600,
            'status' => 'billable',
            'proofhub_created_at' => $iso8601, // Carbon handles ISO 8601 automatically
        ]);

        $stored = $entry->fresh();
        
        // Verify stored as UTC
        $this->assertEquals(
            '2024-07-01 09:00:00',
            $stored->proofhub_created_at->timezone('UTC')->format('Y-m-d H:i:s'),
            'ProofHub ISO 8601 should store as UTC'
        );
        
        // Verify displays in Madrid time (UTC+2 in summer)
        $this->assertEquals(
            '2024-07-01 11:00:00',
            $stored->proofhub_created_at->format('Y-m-d H:i:s'),
            'Should display as 11:00 in Madrid'
        );
    }

    /**
     * Test that database columns are actually timestamptz type.
     */
    public function test_database_uses_timestamptz_columns(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This test requires PostgreSQL');
        }

        // Check user_leaves columns
        $columns = DB::select("
            SELECT column_name, data_type 
            FROM information_schema.columns 
            WHERE table_name = 'user_leaves' 
            AND column_name IN ('start_date', 'end_date')
        ");

        foreach ($columns as $column) {
            $this->assertEquals(
                'timestamp with time zone',
                $column->data_type,
                "Column {$column->column_name} should be timestamptz"
            );
        }

        // Check user_attendances columns
        $columns = DB::select("
            SELECT column_name, data_type 
            FROM information_schema.columns 
            WHERE table_name = 'user_attendances' 
            AND column_name IN ('clock_in', 'clock_out')
        ");

        foreach ($columns as $column) {
            $this->assertEquals(
                'timestamp with time zone',
                $column->data_type,
                "Column {$column->column_name} should be timestamptz"
            );
        }
    }

    /**
     * Test that database session timezone is UTC.
     */
    public function test_database_session_timezone_is_utc(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This test requires PostgreSQL');
        }

        $result = DB::selectOne('SHOW timezone');
        
        $this->assertEquals(
            'UTC',
            $result->TimeZone,
            'Database session should use UTC timezone'
        );
    }

    /**
     * Test app timezone is Europe/Madrid for display.
     */
    public function test_app_timezone_is_madrid(): void
    {
        $this->assertEquals(
            'Europe/Madrid',
            config('app.timezone'),
            'App timezone should be Europe/Madrid for UI display'
        );
    }

    /**
     * Test winter time (DST off) - Madrid is UTC+1.
     */
    public function test_winter_time_utc_plus_1(): void
    {
        // January is winter - Madrid is UTC+1
        $utcTime = '2024-01-15 09:00:00';
        
        $leave = UserLeave::create([
            'odoo_leave_id' => 997,
            'type' => 'employee',
            'status' => 'validate',
            'start_date' => Carbon::parse($utcTime, 'UTC'),
            'end_date' => Carbon::parse('2024-01-15 17:00:00', 'UTC'),
            'duration_days' => 1.0,
        ]);

        $stored = $leave->fresh();
        
        // In January, Madrid is UTC+1 (winter)
        $this->assertEquals(
            '2024-01-15 10:00:00',
            $stored->start_date->format('Y-m-d H:i:s'),
            'In winter, 09:00 UTC should display as 10:00 Madrid (UTC+1)'
        );
    }

    /**
     * Test that Carbon correctly identifies timezone offset.
     */
    public function test_carbon_timezone_offset(): void
    {
        // Summer time (July) - Madrid is UTC+2
        $summer = Carbon::parse('2024-07-01 12:00:00', 'Europe/Madrid');
        $this->assertEquals(
            '+02:00',
            $summer->format('P'),
            'Madrid in summer should be UTC+2'
        );

        // Winter time (January) - Madrid is UTC+1
        $winter = Carbon::parse('2024-01-01 12:00:00', 'Europe/Madrid');
        $this->assertEquals(
            '+01:00',
            $winter->format('P'),
            'Madrid in winter should be UTC+1'
        );
    }

    /**
     * Test date comparisons work correctly across timezones.
     */
    public function test_date_comparisons_work_correctly(): void
    {
        $user = User::factory()->create();
        
        // Create leave at 09:00 UTC
        $leave = UserLeave::create([
            'odoo_leave_id' => 996,
            'type' => 'employee',
            'status' => 'validate',
            'start_date' => Carbon::parse('2024-07-01 09:00:00', 'UTC'),
            'end_date' => Carbon::parse('2024-07-01 17:00:00', 'UTC'),
            'duration_days' => 1.0,
            'user_id' => $user->id,
        ]);

        // Query leaves starting after 08:00 UTC
        $found = UserLeave::where('start_date', '>', Carbon::parse('2024-07-01 08:00:00', 'UTC'))
            ->count();
        
        $this->assertEquals(1, $found, 'Should find leave starting at 09:00 UTC');

        // Query leaves starting after 10:00 UTC
        $notFound = UserLeave::where('start_date', '>', Carbon::parse('2024-07-01 10:00:00', 'UTC'))
            ->count();
        
        $this->assertEquals(0, $notFound, 'Should not find leave when querying > 10:00 UTC');
    }
}

