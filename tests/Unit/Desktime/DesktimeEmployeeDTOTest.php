<?php

declare(strict_types=1);

use App\DataTransferObjects\Desktime\DesktimeEmployeeDTO;
use PHPUnit\Framework\TestCase;

class DesktimeEmployeeDTOTest extends TestCase
{
    public function test_it_can_be_constructed_with_all_fields(): void
    {
        $dto = new DesktimeEmployeeDTO(
            id: 1,
            email: 'john@example.com',
            name: 'John Doe'
        );

        $this->assertSame(1, $dto->id);
        $this->assertSame('john@example.com', $dto->email);
        $this->assertSame('John Doe', $dto->name);
    }

    public function test_nullable_fields(): void
    {
        $dto = new DesktimeEmployeeDTO;
        $this->assertNull($dto->id);
        $this->assertNull($dto->email);
        $this->assertNull($dto->name);
    }
}
