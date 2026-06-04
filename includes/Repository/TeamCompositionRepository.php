<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Repository;

use TT\TeamPlanner\Domain\TeamComposition;

class TeamCompositionRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tttp_team_compositions';
    }

    /** @return TeamComposition[] */
    public function findByRound(string $season, int $phase, int $round): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d AND round = %d ORDER BY team_code, slot_number",
                $season, $phase, $round
            ),
            ARRAY_A
        );
        return array_map([TeamComposition::class, 'fromRow'], $rows ?: []);
    }

    /** @return TeamComposition[] */
    public function findByTeamAndRound(string $season, int $phase, int $round, string $teamCode): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d AND round = %d AND team_code = %s ORDER BY slot_number",
                $season, $phase, $round, $teamCode
            ),
            ARRAY_A
        );
        return array_map([TeamComposition::class, 'fromRow'], $rows ?: []);
    }

    public function setSlot(string $season, int $phase, int $round, string $teamCode, int $slot, int $playerId): void
    {
        global $wpdb;

        // Prevent same player appearing twice in the same round (different teams)
        $this->clearPlayerFromRound($season, $phase, $round, $playerId);

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->table} (season, phase, round, team_code, slot_number, player_id)
             VALUES (%s, %d, %d, %s, %d, %d)
             ON DUPLICATE KEY UPDATE player_id = VALUES(player_id), updated_at = NOW()",
            $season, $phase, $round, $teamCode, $slot, $playerId
        ));
    }

    public function clearSlot(string $season, int $phase, int $round, string $teamCode, int $slot): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->table} (season, phase, round, team_code, slot_number, player_id)
             VALUES (%s, %d, %d, %s, %d, NULL)
             ON DUPLICATE KEY UPDATE player_id = NULL, updated_at = NOW()",
            $season, $phase, $round, $teamCode, $slot
        ));
    }

    public function clearTeam(string $season, int $phase, int $round, string $teamCode): void
    {
        global $wpdb;
        $wpdb->update(
            $this->table,
            ['player_id' => null],
            ['season' => $season, 'phase' => $phase, 'round' => $round, 'team_code' => $teamCode]
        );
    }

    private function clearPlayerFromRound(string $season, int $phase, int $round, int $playerId): void
    {
        global $wpdb;
        $wpdb->update(
            $this->table,
            ['player_id' => null],
            ['season' => $season, 'phase' => $phase, 'round' => $round, 'player_id' => $playerId]
        );
    }
}
