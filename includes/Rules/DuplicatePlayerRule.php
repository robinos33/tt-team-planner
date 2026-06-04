<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Rules;

use TT\TeamPlanner\Domain\Player;
use TT\TeamPlanner\Domain\RuleViolation;

class DuplicatePlayerRule implements CompositionRuleInterface
{
    /**
     * @param array<int, string[]> $playerTeamMap  playerId => [teamCodes]
     */
    public function __construct(
        private readonly array $playerTeamMap = []
    ) {}

    public function check(array $players, string $teamCode, array $context): array
    {
        $violations = [];

        foreach ($players as $player) {
            $teams = $this->playerTeamMap[$player->id] ?? [];
            $otherTeams = array_filter($teams, fn($t) => $t !== $teamCode);

            if (count($otherTeams) > 0) {
                $violations[] = new RuleViolation(
                    type:      'doublon',
                    severity:  RuleViolation::SEV_HIGH,
                    title:     __('Joueur en doublon', 'tt-team-planner'),
                    detail:    sprintf(
                        /* translators: 1: player name, 2: other teams, 3: round number */
                        __('%1$s est aussi sélectionné en %2$s pour la J%3$d.', 'tt-team-planner'),
                        $player->fullName(), implode(', ', $otherTeams), $context['round'] ?? 0
                    ),
                    teamCode:  $teamCode,
                    journee:   $context['round'] ?? 0,
                    playerIds: [$player->id],
                );
            }
        }

        return $violations;
    }
}
