<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Tests\Unit\Domain;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TT\TeamPlanner\Domain\Division;
use TT\TeamPlanner\Front\Assets;

#[CoversClass(Division::class)]
#[CoversClass(Assets::class)]
final class DivisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('remove_accents')->alias(
            static fn (string $text): string => strtr($text, ['é' => 'e', 'è' => 'e', 'É' => 'E'])
        );
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /** @return iterable<string, array{string, bool}> */
    public static function levels(): iterable
    {
        yield 'Pré-Nationale' => ['Pré-Nationale', true];
        yield 'PN'            => ['PN', true];
        yield 'Régionale 1'   => ['Régionale 1', true];
        yield 'R2'            => ['R2', true];
        yield 'régionale 3'   => ['  régionale 3 ', true];
        yield 'Pré-Régionale' => ['Pré-Régionale', false];
        yield 'Départementale 1' => ['Départementale 1', false];
        yield 'Nationale 3'   => ['Nationale 3', false];
        yield 'vide'          => ['', false];
    }

    #[DataProvider('levels')]
    public function testOnlyRegionalDivisionsRequireAFullTeam(string $level, bool $expected): void
    {
        self::assertSame($expected, Division::requiresFullTeam($level));
    }

    public function testTeamsForFrontFlagsRegionalTeams(): void
    {
        $teams = Assets::teamsForFront([
            ['code' => 'T1', 'level' => 'Régionale 2'],
            ['code' => 'T5', 'level' => 'Départementale 1'],
            ['code' => 'T6'],
        ]);

        self::assertSame([true, false, false], array_column($teams, 'full_team_required'));
        self::assertSame('T1', $teams[0]['code']);
    }
}
