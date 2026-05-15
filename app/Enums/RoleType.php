<?php

namespace App\Enums;

enum RoleType: string
{
    case Admin = 'admin';
    case User = 'user';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::User => 'User',
            self::Maintenance => 'Maintenance',
        };
    }
}
