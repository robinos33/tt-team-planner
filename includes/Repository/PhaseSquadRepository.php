<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Repository;

use TT\TeamPlanner\Domain\PhaseSquad;

class PhaseSquadRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tttp_phase_squads';
    }

    /** @return PhaseSquad[] */
    public function findBySeasonPhase(string $season, int $phase): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d ORDER BY team_code, position ASC",
                $season,
                $phase
            ),
            ARRAY_A
        );
        return array_map([PhaseSquad::class, 'fromRow'], $rows ?: []);
    }

    /** @return PhaseSquad[] */
    public function findByTeam(string $season, int $phase, string $teamCode): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d AND team_code = %s ORDER BY position ASC",
                $season,
                $phase,
                $teamCode
            ),
            ARRAY_A
        );
        return array_map([PhaseSquad::class, 'fromRow'], $rows ?: []);
    }

    public function add(string $season, int $phase, string $teamCode, int $playerId): bool
    {
        global $wpdb;

        // Calcule la prochaine position
        $maxPos = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(MAX(position), 0) FROM {$this->table} WHERE season = %s AND phase = %d AND team_code = %s",
                $season,
                $phase,
                $teamCode
            )
        );

        $result = $wpdb->insert($this->table, [
            'season'    => $season,
            'phase'     => $phase,
            'team_code' => $teamCode,
            'player_id' => $playerId,
            'position'  => $maxPos + 1,
        ]);

        return $result !== false;
    }

    public function remove(string $season, int $phase, string $teamCode, int $playerId): bool
    {
        global $wpdb;
        return (bool) $wpdb->delete($this->table, [
            'season'    => $season,
            'phase'     => $phase,
            'team_code' => $teamCode,
            'player_id' => $playerId,
        ]);
    }

    public function existsInAnyTeam(string $season, int $phase, int $playerId): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE season = %s AND phase = %d AND player_id = %d",
                $season,
                $phase,
                $playerId
            )
        );
    }
}
