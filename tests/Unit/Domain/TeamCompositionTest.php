<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Tests\Unit\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TT\TeamPlanner\Domain\TeamComposition;

#[CoversClass(TeamComposition::class)]
final class TeamCompositionTest extends TestCase
{
    private const ROW = [
        'id' => 1, 'season' => '2026-2027', 'phase' => 1, 'round' => 3,
        'team_code' => 'E1', 'slot_number' => 2,
    ];

    public function testLeCapitaineEstLuSurUnSlotOccupe(): void
    {
        $slot = TeamComposition::fromRow(self::ROW + ['player_id' => '7', 'is_captain' => '1']);

        self::assertTrue($slot->isCaptain);
        self::assertTrue($slot->toArray()['is_captain']);
    }

    /** Vider un slot remet player_id à NULL sans toucher au drapeau. */
    public function testUnSlotVideNEstJamaisCapitaine(): void
    {
        $slot = TeamComposition::fromRow(self::ROW + ['player_id' => null, 'is_captain' => '1']);

        self::assertFalse($slot->isCaptain);
    }
}
