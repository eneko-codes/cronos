<?php

declare(strict_types=1);

use App\DataTransferObjects\Desktime\DesktimeAttendanceDTO;
use PHPUnit\Framework\TestCase;

class DesktimeAttendanceDTOTest extends TestCase
{
    public function test_it_can_be_constructed_with_all_fields(): void
    {
        $dto = new DesktimeAttendanceDTO(
            1,
            'Michael Scott',
            'demo@desktime.com',
            1,
            'Accounting',
            'https://desktime.com/app/employee/1/2012-03-16',
            false,
            '2024-07-06 09:00:00',
            false,
            false,
            3500,
            100,
            3600,
            3600,
            0,
            0,
            1800,
            95.5,
            80.0,
            '09:00:00',
            '18:00:00',
            ['Slack' => 'user'],
            [['project_id' => 1, 'duration' => 3600]],
            null,
            null
        );

        $this->assertSame(1, $dto->id);
        $this->assertSame('Michael Scott', $dto->name);
        $this->assertSame('demo@desktime.com', $dto->email);
        $this->assertSame(1, $dto->groupId);
        $this->assertSame('Accounting', $dto->group);
        $this->assertSame('https://desktime.com/app/employee/1/2012-03-16', $dto->profileUrl);
        $this->assertFalse($dto->isOnline);
        $this->assertSame('2024-07-06 09:00:00', $dto->arrived);
        $this->assertFalse($dto->left);
        $this->assertFalse($dto->late);
        $this->assertSame(3500, $dto->onlineTime);
        $this->assertSame(100, $dto->offlineTime);
        $this->assertSame(3600, $dto->desktimeTime);
        $this->assertSame(3600, $dto->atWorkTime);
        $this->assertSame(0, $dto->afterWorkTime);
        $this->assertSame(0, $dto->beforeWorkTime);
        $this->assertSame(1800, $dto->productiveTime);
        $this->assertSame(95.5, $dto->productivity);
        $this->assertSame(80.0, $dto->efficiency);
        $this->assertSame('09:00:00', $dto->work_starts);
        $this->assertSame('18:00:00', $dto->work_ends);
        $this->assertSame(['Slack' => 'user'], $dto->notes);
        $this->assertSame([['project_id' => 1, 'duration' => 3600]], $dto->activeProject);
    }

    public function test_nullable_fields(): void
    {
        $dto = new DesktimeAttendanceDTO;
        $this->assertNull($dto->id);
        $this->assertNull($dto->name);
        $this->assertNull($dto->email);
        $this->assertNull($dto->groupId);
        $this->assertNull($dto->group);
        $this->assertNull($dto->profileUrl);
        $this->assertNull($dto->isOnline);
        $this->assertNull($dto->arrived);
        $this->assertNull($dto->left);
        $this->assertNull($dto->late);
        $this->assertNull($dto->onlineTime);
        $this->assertNull($dto->offlineTime);
        $this->assertNull($dto->desktimeTime);
        $this->assertNull($dto->atWorkTime);
        $this->assertNull($dto->afterWorkTime);
        $this->assertNull($dto->beforeWorkTime);
        $this->assertNull($dto->productiveTime);
        $this->assertNull($dto->productivity);
        $this->assertNull($dto->efficiency);
        $this->assertNull($dto->work_starts);
        $this->assertNull($dto->work_ends);
        $this->assertNull($dto->notes);
        $this->assertNull($dto->activeProject);
        $this->assertNull($dto->apps);
        $this->assertNull($dto->projects);
    }

    public function test_arrived_and_left_can_be_bool_or_string(): void
    {
        $dto1 = new DesktimeAttendanceDTO(
            1, 'Michael Scott', 'demo@desktime.com', 1, 'Accounting', 'https://desktime.com/app/employee/1/2012-03-16', false, null, false, true
        );
        $this->assertTrue($dto1->late);
        $this->assertFalse($dto1->left);

        $dto2 = new DesktimeAttendanceDTO(
            1, 'Michael Scott', 'demo@desktime.com', 1, 'Accounting', 'https://desktime.com/app/employee/1/2012-03-16', false, '2024-07-06 09:00:00', '2024-07-06 18:00:00'
        );
        $this->assertSame('2024-07-06 09:00:00', $dto2->arrived);
        $this->assertSame('2024-07-06 18:00:00', $dto2->left);
    }
}
