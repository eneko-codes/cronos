<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents external platforms that users can be linked to.
 * Used for identity mapping and synchronization.
 */
enum Platform: string
{
    case Odoo = 'odoo';
    case DeskTime = 'desktime';
    case ProofHub = 'proofhub';
    case SystemPin = 'systempin';

    /**
     * Get a human-readable label for the platform.
     */
    public function label(): string
    {
        return match ($this) {
            self::Odoo => 'Odoo',
            self::DeskTime => 'DeskTime',
            self::ProofHub => 'ProofHub',
            self::SystemPin => 'SystemPin',
        };
    }
}
