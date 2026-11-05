<?php

declare(strict_types=1);

namespace TT\TeamPlanner;

use TT\TeamPlanner\Mail\AvailabilityReminder;
use TT\TeamPlanner\Sync\AppearanceImporter;

class Deactivator
{
    public static function deactivate(): void
    {
        AvailabilityReminder::unschedule();
        AppearanceImporter::unschedule();
        flush_rewrite_rules();
    }
}
