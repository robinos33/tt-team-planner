<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Rules;

use TT\TeamPlanner\Domain\Player;
use TT\TeamPlanner\Domain\RuleViolation;

interface CompositionRuleInterface
{
    /**
     * Check a team composition for rule violations.
     *
     * @param Player[] $players  Players in the composition
     * @param string   $teamCode Team code (e.g. "E1")
     * @param array    $context  ['season', 'phase', 'round', 'all_players_in_round']
     * @return RuleViolation[]
     */
    public function check(array $players, string $teamCode, array $context): array;
}
