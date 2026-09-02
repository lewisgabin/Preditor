<?php

namespace App\Domain\Draws\Enums;

enum SyncTrigger: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
    case Reconciliation = 'reconciliation';
    case Historical = 'historical';
}
