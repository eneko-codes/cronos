<?php

declare(strict_types=1);

use App\Clients\DesktimeApiClient;
use App\DataTransferObjects\Desktime\DesktimeAttendanceDTO;
use App\Jobs\Sync\Desktime\SyncDesktimeAttendances;
use App\Models\User;
use App\Models\UserAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncDesktimeAttendancesTest extends TestCase
{
    use RefreshDatabase;

    private function getDesktimeApiClientMock(array $attendanceData = []): DesktimeApiClient
    {
        $mock = $this->getMockBuilder(DesktimeApiClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllAttendanceForDate', 'getSingleEmployee'])
            ->getMock();
        $mock->method('getAllAttendanceForDate')->willReturn(collect($attendanceData));
        // Return a DesktimeAttendanceDTO for getSingleEmployee, regardless of argument types
        $first = reset($attendanceData);
        $dto = $first instanceof DesktimeAttendanceDTO ? $first : (
            is_object($first) ? new DesktimeAttendanceDTO(...(array) $first) : new DesktimeAttendanceDTO
        );
        $mock->method('getSingleEmployee')->willReturn($dto);

        return $mock;
    }

    public function test_it_syncs_remote_attendance(): void
    {
        $user = User::factory()->create(['desktime_id' => 123]);
        $attendanceDTO = new DesktimeAttendanceDTO(
            now()->toDateString(),
            3600,
            1800,
            null,
            null,
            false,
            0,
            0,
            0,
            0,
            0,
            null,
            3600,
            0,
            true
        );
        $client = $this->getDesktimeApiClientMock([123 => $attendanceDTO]);
        $job = new SyncDesktimeAttendances($client);
        $job->handle();
        $this->assertDatabaseHas('user_attendances', [
            'user_id' => $user->id,
            'is_remote' => true,
        ]);
    }

    public function test_it_syncs_in_office_attendance(): void
    {
        $user = User::factory()->create(['desktime_id' => 456]);
        $attendanceDTO = new DesktimeAttendanceDTO(
            now()->toDateString(),
            28800,
            25000,
            now()->toDateString().' 09:00:00',
            now()->toDateString().' 18:00:00',
            false,
            0,
            0,
            0,
            0,
            0,
            null,
            28800,
            0,
            false
        );
        $client = $this->getDesktimeApiClientMock([456 => $attendanceDTO]);
        $job = new SyncDesktimeAttendances($client);
        $job->handle();
        $this->assertDatabaseHas('user_attendances', [
            'user_id' => $user->id,
            'is_remote' => false,
        ]);
    }

    public function test_it_handles_no_attendance_data(): void
    {
        $user = User::factory()->create(['desktime_id' => 789]);
        $client = $this->getDesktimeApiClientMock([]);
        $job = new SyncDesktimeAttendances($client);
        $job->handle();

        $this->assertDatabaseMissing('user_attendances', [
            'user_id' => $user->id,
        ]);
    }

    public function test_it_updates_existing_attendance_record(): void
    {
        $user = User::factory()->create(['desktime_id' => 321]);
        // First sync: create attendance
        $attendanceDTO = (object) [
            'desktimeTime' => 3600,
            'productiveTime' => 1800,
            'arrived' => null,
            'left' => null,
            'late' => false,
            'onlineTime' => 3500,
            'offlineTime' => 100,
            'atWorkTime' => 3600,
            'afterWorkTime' => 0,
            'beforeWorkTime' => 0,
            'productivity' => 95.5,
            'efficiency' => 80.0,
            'work_starts' => '09:00:00',
            'work_ends' => '18:00:00',
            'notes' => null,
            'activeProject' => null,
        ];
        $client = $this->getDesktimeApiClientMock([321 => $attendanceDTO]);
        $job = new SyncDesktimeAttendances($client);
        $job->handle();
        $this->assertDatabaseHas('user_attendances', [
            'user_id' => $user->id,
            'presence_seconds' => 3600,
        ]);
        // Second sync: update attendance
        $attendanceDTO->desktimeTime = 7200;
        $client = $this->getDesktimeApiClientMock([321 => $attendanceDTO]);
        $job = new SyncDesktimeAttendances($client);
        $job->handle();
        $this->assertDatabaseHas('user_attendances', [
            'user_id' => $user->id,
            'presence_seconds' => 7200,
        ]);
    }

    public function test_it_deletes_obsolete_attendance(): void
    {
        $user = User::factory()->create(['desktime_id' => 654]);
        $date = '2025-07-07'; // Use a fixed date for all steps
        // Create attendance record manually
        UserAttendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'presence_seconds' => 3600,
            'is_remote' => true,
        ]);
        $user->refresh();
        $user->desktime_id = (int) $user->desktime_id;
        $client = $this->getDesktimeApiClientMock([]); // No data from DeskTime
        $job = new SyncDesktimeAttendances($client, $user->id, $date, $date); // Pass the date to the job
        $job->handle();
        $this->assertDatabaseMissing('user_attendances', [
            'user_id' => $user->id,
            'date' => $date,
        ]);
    }

    public function test_it_handles_api_failure(): void
    {
        $user = User::factory()->create(['desktime_id' => 999]);
        $mock = $this->getMockBuilder(DesktimeApiClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllAttendanceForDate'])
            ->getMock();
        $mock->method('getAllAttendanceForDate')->will($this->throwException(new Exception('API error')));
        $job = new SyncDesktimeAttendances($mock);
        $this->expectException(Exception::class);
        $job->handle();
        $this->assertDatabaseMissing('user_attendances', [
            'user_id' => $user->id,
        ]);
    }

    public function test_it_syncs_multiple_users(): void
    {
        $user1 = User::factory()->create(['desktime_id' => 111]);
        $user2 = User::factory()->create(['desktime_id' => 222]);
        $attendanceDTO1 = (object) [
            'desktimeTime' => 3600,
            'productiveTime' => 1800,
            'arrived' => null,
            'left' => null,
            'late' => false,
            'onlineTime' => 3500,
            'offlineTime' => 100,
            'atWorkTime' => 3600,
            'afterWorkTime' => 0,
            'beforeWorkTime' => 0,
            'productivity' => 95.5,
            'efficiency' => 80.0,
            'work_starts' => '09:00:00',
            'work_ends' => '18:00:00',
            'notes' => null,
            'activeProject' => null,
        ];
        $attendanceDTO2 = (object) [
            'desktimeTime' => 7200,
            'productiveTime' => 3600,
            'arrived' => null,
            'left' => null,
            'late' => false,
            'onlineTime' => 7000,
            'offlineTime' => 200,
            'atWorkTime' => 7200,
            'afterWorkTime' => 0,
            'beforeWorkTime' => 0,
            'productivity' => 90.0,
            'efficiency' => 85.0,
            'work_starts' => '09:00:00',
            'work_ends' => '18:00:00',
            'notes' => null,
            'activeProject' => null,
        ];
        $client = $this->getDesktimeApiClientMock([
            111 => $attendanceDTO1,
            222 => $attendanceDTO2,
        ]);
        $job = new SyncDesktimeAttendances($client);
        $job->handle();
        $this->assertDatabaseHas('user_attendances', [
            'user_id' => $user1->id,
            'presence_seconds' => 3600,
        ]);
        $this->assertDatabaseHas('user_attendances', [
            'user_id' => $user2->id,
            'presence_seconds' => 7200,
        ]);
    }
}
