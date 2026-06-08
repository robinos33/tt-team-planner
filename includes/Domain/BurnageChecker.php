<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Domain; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- PSR-4, TT\TeamPlanner est le préfixe plugin

use TT\TeamPlanner\Repository\MatchAppearanceRepository;
use TT\TeamPlanner\Repository\TeamCompositionRepository;

/**
 * Calcule le statut de "brûlage" d'un joueur pour une équipe donnée, selon le
 * règlement sportif FFTT II.112.1 (format 4 joueurs) :
 *
 *  - Règle 2 : un joueur ayant disputé 2 rencontres (consécutives ou non) dans
 *    une phase, au sein d'une équipe de numéro N (ou d'équipes différentes),
 *    ne peut plus jouer dans une équipe de numéro supérieur à N.
 *  - Règle 3 : à la 2e journée d'une phase, une équipe ne peut comporter plus
 *    d'un joueur ayant disputé la 1re journée dans une équipe de numéro inférieur.
 *
 * Le "numéro" d'une équipe est déduit du `team_code` (ex. "T1" → 1, "T2" → 2).
 * Si aucun chiffre n'est trouvé, le contrôle est désactivé pour cette équipe.
 */
final class BurnageChecker
{
    public function __construct(
        private readonly MatchAppearanceRepository $appearances = new MatchAppearanceRepository(),
        private readonly TeamCompositionRepository $compositions = new TeamCompositionRepository(),
    ) {}

    public static function extractTeamRank(string $teamCode): ?int
    {
        if (preg_match('/(\d+)/', $teamCode, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    public function statusFor(string $season, int $phase, int $round, string $teamCode, int $playerId): BurnageStatus
    {
        $teamRank = self::extractTeamRank($teamCode);
        if ($teamRank === null) {
            return BurnageStatus::ok();
        }

        return $this->checkRule2($season, $phase, $playerId, $teamRank)
            ?? $this->checkRule3($season, $phase, $round, $teamCode, $teamRank, $playerId)
            ?? BurnageStatus::ok();
    }

    private function checkRule2(string $season, int $phase, int $playerId, int $teamRank): ?BurnageStatus
    {
        $appearances = $this->appearances->findByPlayerAndPhase($season, $phase, $playerId);
        if (count($appearances) < 2) {
            return null;
        }

        // Pour chaque journée distincte, on retient le rang le plus fort (numériquement le plus petit) atteint.
        $bestRankByRound = [];
        foreach ($appearances as $appearance) {
            $round = $appearance->round;
            if (! isset($bestRankByRound[$round]) || $appearance->teamRank < $bestRankByRound[$round]) {
                $bestRankByRound[$round] = $appearance->teamRank;
            }
        }

        if (count($bestRankByRound) < 2) {
            return null;
        }

        $ranks = array_values($bestRankByRound);
        sort($ranks);

        // Une fois trié, le rang plafond N est le 2e rang le plus fort rencontré :
        // c'est le plus petit N tel qu'au moins 2 journées aient été jouées à un rang <= N.
        $ceiling = $ranks[1];

        if ($teamRank > $ceiling) {
            return new BurnageStatus(
                true,
                'rule2',
                sprintf(
                    /* translators: %d: numéro d'équipe */
                    __('A déjà disputé 2 rencontres dans une équipe de numéro %d ou inférieur : ne peut plus jouer dans une équipe de numéro supérieur.', 'tt-team-planner'),
                    $ceiling
                )
            );
        }

        return null;
    }

    private function checkRule3(
        string $season,
        int $phase,
        int $round,
        string $teamCode,
        int $teamRank,
        int $playerId
    ): ?BurnageStatus {
        if ($round !== 2) {
            return null;
        }

        $previousRoundAppearances = $this->appearances->findByPhaseAndRound($season, $phase, $round - 1);
        if ($previousRoundAppearances === []) {
            return null;
        }

        $bestRankByPlayer = [];
        foreach ($previousRoundAppearances as $appearance) {
            $pid = $appearance->playerId;
            if (! isset($bestRankByPlayer[$pid]) || $appearance->teamRank < $bestRankByPlayer[$pid]) {
                $bestRankByPlayer[$pid] = $appearance->teamRank;
            }
        }

        $isPromoted = static fn (int $pid): bool =>
            isset($bestRankByPlayer[$pid]) && $bestRankByPlayer[$pid] < $teamRank;

        if (! $isPromoted($playerId)) {
            return null;
        }

        $promotedAlreadyInTeam = 0;
        foreach ($this->compositions->findByTeamAndRound($season, $phase, $round, $teamCode) as $slot) {
            if ($slot->playerId !== null && $slot->playerId !== $playerId && $isPromoted($slot->playerId)) {
                $promotedAlreadyInTeam++;
            }
        }

        if ($promotedAlreadyInTeam >= 1) {
            return new BurnageStatus(
                true,
                'rule3',
                __("Limite J2 : une équipe ne peut comporter plus d'un joueur ayant disputé la 1re journée dans une équipe de numéro inférieur.", 'tt-team-planner')
            );
        }

        return null;
    }
}
