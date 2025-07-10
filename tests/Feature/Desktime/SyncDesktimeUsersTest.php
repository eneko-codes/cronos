<?php

declare(strict_types=1);

use App\Clients\DesktimeApiClient;
use App\DataTransferObjects\Desktime\DesktimeEmployeeDTO;
use App\Jobs\Sync\Desktime\SyncDesktimeUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncDesktimeUsersTest extends TestCase
{
    use RefreshDatabase;

    private function getDesktimeApiClientMock(array $users = []): DesktimeApiClient
    {
        $mock = $this->getMockBuilder(DesktimeApiClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllEmployees'])
            ->getMock();
        $mock->method('getAllEmployees')->willReturn(collect($users));

        return $mock;
    }

    public function test_it_creates_new_users_from_desktime(): void
    {
        $user = User::factory()->create([
            'email' => 'newuser@example.com',
            'name' => 'New User',
        ]);
        $employeeDTO = new DesktimeEmployeeDTO(
            id: 123,
            email: 'newuser@example.com',
            name: 'New User'
        );
        $client = $this->getDesktimeApiClientMock([$employeeDTO]);
        $job = new SyncDesktimeUsers($client);
        $job->handle();
        $user->refresh();
        $this->assertEquals(123, $user->desktime_id);
    }

    public function test_it_updates_existing_users_from_desktime(): void
    {
        $user = User::factory()->create([
            'desktime_id' => 124,
            'email' => 'existing@example.com',
            'name' => 'Old Name',
        ]);
        $employeeDTO = new DesktimeEmployeeDTO(
            id: 124,
            email: 'existing@example.com',
            name: 'Updated Name'
        );
        $client = $this->getDesktimeApiClientMock([$employeeDTO]);
        $job = new SyncDesktimeUsers($client);
        $job->handle();
        $user->refresh();
        $this->assertEquals(124, $user->desktime_id);
    }

    public function test_it_clears_desktime_ids_for_users_no_longer_present(): void
    {
        $user = User::factory()->create([
            'desktime_id' => 3,
            'email' => 'removed@example.com',
            'name' => 'Removed User',
        ]);
        $employeeDTO = new DesktimeEmployeeDTO(
            id: 4,
            email: 'other@example.com',
            name: 'Other User'
        );
        $client = $this->getDesktimeApiClientMock([$employeeDTO]);
        $job = new SyncDesktimeUsers($client);
        $job->handle();
        $user->refresh();
        $this->assertNull($user->desktime_id);
    }

    public function test_it_handles_duplicate_emails(): void
    {
        $user1 = User::factory()->create([
            'desktime_id' => 5,
            'email' => 'duplicate1@example.com',
            'name' => 'User One',
        ]);
        $user2 = User::factory()->create([
            'desktime_id' => 6,
            'email' => 'duplicate2@example.com',
            'name' => 'User Two',
        ]);
        $employeeDTO1 = new DesktimeEmployeeDTO(
            id: 5,
            email: 'duplicate1@example.com',
            name: 'User One'
        );
        $employeeDTO2 = new DesktimeEmployeeDTO(
            id: 6,
            email: 'duplicate2@example.com',
            name: 'User Two'
        );
        $client = $this->getDesktimeApiClientMock([$employeeDTO1, $employeeDTO2]);
        $job = new SyncDesktimeUsers($client);
        $job->handle();
        $user1->refresh();
        $user2->refresh();
        $this->assertEquals(5, $user1->desktime_id);
        $this->assertEquals(6, $user2->desktime_id);
    }

    public function test_it_skips_users_with_missing_or_invalid_email(): void
    {
        $employeeDTO = new DesktimeEmployeeDTO(
            id: 7,
            email: null,
            name: 'No Email'
        );
        $client = $this->getDesktimeApiClientMock([$employeeDTO]);
        $job = new SyncDesktimeUsers($client);
        $job->handle();
        $this->assertDatabaseMissing('users', [
            'desktime_id' => 7,
        ]);
    }

    public function test_it_handles_api_failure(): void
    {
        $mock = $this->getMockBuilder(DesktimeApiClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllEmployees'])
            ->getMock();
        $mock->method('getAllEmployees')->will($this->throwException(new \Exception('API error')));
        $job = new SyncDesktimeUsers($mock);
        $this->expectException(\Exception::class);
        $job->handle();
    }
}
