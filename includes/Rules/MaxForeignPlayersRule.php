<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Rules;

use TT\TeamPlanner\Domain\Player;
use TT\TeamPlanner\Domain\RuleViolation;

class MaxForeignPlayersRule implements CompositionRuleInterface
{
    public function __construct(
        private readonly int $maxForeignPlayers = 2
    ) {}

    public function check(array $players, string $teamCode, array $context): array
    {
        $foreignPlayers = array_filter($players, fn(Player $p) => $p->isForeign);
        $count = count($foreignPlayers);

        if ($count <= $this->maxForeignPlayers) {
            return [];
        }

        $names = implode(', ', array_map(fn(Player $p) => $p->fullName(), $foreignPlayers));

        return [new RuleViolation(
            type:      'E',
            severity:  RuleViolation::SEV_HIGH,
            title:     __('Trop de joueurs E', 'tt-team-planner'),
            detail:    sprintf(
                /* translators: 1: team code, 2: count, 3: max, 4: player names */
                __('%1$s compte %2$d joueur(s) E (max %3$d) : %4$s.', 'tt-team-planner'),
                $teamCode, $count, $this->maxForeignPlayers, $names
            ),
            teamCode:  $teamCode,
            journee:   $context['round'] ?? 0,
            playerIds: array_map(fn(Player $p) => $p->id, $foreignPlayers),
        )];
    }
}
