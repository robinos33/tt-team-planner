<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Rules;

use TT\TeamPlanner\Domain\Player;
use TT\TeamPlanner\Domain\RuleViolation;

class AvailabilityRule implements CompositionRuleInterface
{
    /**
     * Availability statuses indexed by player ID, format: ['available'|'unavailable'|'uncertain'|'unknown']
     * @param array<int, string> $availabilities
     */
    public function __construct(
        private readonly array $availabilities = []
    ) {}

    public function check(array $players, string $teamCode, array $context): array
    {
        $violations = [];

        foreach ($players as $player) {
            $status = $this->availabilities[$player->id] ?? 'unknown';

            if ($status === 'unavailable') {
                $violations[] = new RuleViolation(
                    type:      'avail',
                    severity:  RuleViolation::SEV_HIGH,
                    title:     __('Joueur indisponible', 'tt-team-planner'),
                    detail:    sprintf(
                        /* translators: 1: player name, 2: team code, 3: round number */
                        __('%1$s est indisponible pour la J%3$d et est placé en %2$s.', 'tt-team-planner'),
                        $player->fullName(), $teamCode, $context['round'] ?? 0
                    ),
                    teamCode:  $teamCode,
                    journee:   $context['round'] ?? 0,
                    playerIds: [$player->id],
                );
            } elseif ($status === 'uncertain') {
                $violations[] = new RuleViolation(
                    type:      'avail',
                    severity:  RuleViolation::SEV_MED,
                    title:     __('Disponibilité incertaine', 'tt-team-planner'),
                    detail:    sprintf(
                        __('%1$s n\'a pas encore confirmé sa disponibilité pour la J%2$d.', 'tt-team-planner'),
                        $player->fullName(), $context['round'] ?? 0
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
