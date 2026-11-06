<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Le plugin charge ses classes depuis la liste de tt-team-planner.php, sans
 * autoloader. Les tests, eux, passent par l'autoloader de Composer : une
 * classe absente de la liste les laisse verts et casse le site en production
 * (« Class not found », 1.12.0).
 */
#[CoversNothing]
final class PluginIncludesTest extends TestCase
{
    public function testEveryIncludedFileIsLoadedByThePlugin(): void
    {
        $root = dirname(__DIR__, 2);

        preg_match_all("#'(includes/[^']+\\.php)'#", (string) file_get_contents($root . '/tt-team-planner.php'), $matches);
        $listed = $matches[1];

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/includes'));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        $missing = array_values(array_diff($files, $listed));
        sort($missing);
        self::assertSame([], $missing, 'Fichiers absents de la liste de tt-team-planner.php');
    }
}
