<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Rules;

use TT\TeamPlanner\Domain\Player;
use TT\TeamPlanner\Domain\RuleViolation;

/**
 * Burn rule — V1: detects when a player is placed in a lower-ranked team than
 * their usual team. The actual brûlage logic (based on N appearances in a
 * higher team) is left for V2 and will require participation history.
 * This class is intentionally isolated so the logic can be replaced later.
 */
class BurnRule implements CompositionRuleInterface
{
    /** Team code ordered highest → lowest (e.g. ['E1','E2',...,'E11']) */
    public function __construct(
        private readonly array $teamOrder = []
    ) {}

    public function check(array $players, string $teamCode, array $context): array
    {
        if (empty($this->teamOrder)) {
            return [];
        }

        $violations = [];
        $currentRank = array_search($teamCode, $this->teamOrder, true);

        foreach ($players as $player) {
            $usualRank = array_search($player->usualTeam, $this->teamOrder, true);

            // If the player's usual team is ranked higher (lower index) than the current team
            if ($usualRank !== false && $currentRank !== false && $usualRank < $currentRank) {
                $violations[] = new RuleViolation(
                    type:      'brule',
                    severity:  RuleViolation::SEV_MED,
                    title:     __('Suspicion de brûlage', 'tt-team-planner'),
                    detail:    sprintf(
                        /* translators: 1: player name, 2: usual team, 3: current team */
                        __('%1$s joue habituellement en %2$s et est placé en %3$s. Vérifiez ses participations précédentes.', 'tt-team-planner'),
                        $player->fullName(), $player->usualTeam, $teamCode
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
