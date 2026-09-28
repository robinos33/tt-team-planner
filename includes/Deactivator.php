<?php
declare(strict_types=1);

namespace TT\TeamPlanner; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- PSR-4, TT\TeamPlanner est le préfixe plugin

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
