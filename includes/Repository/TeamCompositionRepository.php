<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Repository; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- PSR-4, TT\TeamPlanner est le préfixe plugin

use TT\TeamPlanner\Domain\TeamComposition;

class TeamCompositionRepository
{
    private const CACHE_GROUP = 'ttp_compositions';

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tttp_team_compositions';
    }

    /** @return TeamComposition[] */
    public function findByRound(string $season, int $phase, int $round): array
    {
        $key    = "{$season}_p{$phase}_r{$round}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        $rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d AND round = %d ORDER BY team_code, slot_number", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season, $phase, $round
            ),
            ARRAY_A
        );
        $result = array_map([TeamComposition::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    /** @return TeamComposition[] */
    public function findByPhase(string $season, int $phase): array
    {
        $key    = "{$season}_p{$phase}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        $rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d ORDER BY round, team_code, slot_number", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season, $phase
            ),
            ARRAY_A
        );
        $result = array_map([TeamComposition::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    /** @return TeamComposition[] */
    public function findByTeamAndRound(string $season, int $phase, int $round, string $teamCode): array
    {
        $key    = "{$season}_p{$phase}_r{$round}_t" . sanitize_key($teamCode);
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        $rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d AND round = %d AND team_code = %s ORDER BY slot_number", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season, $phase, $round, $teamCode
            ),
            ARRAY_A
        );
        $result = array_map([TeamComposition::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    public function setSlot(string $season, int $phase, int $round, string $teamCode, int $slot, int $playerId): void
    {
        global $wpdb;

        $this->clearPlayerFromRound($season, $phase, $round, $playerId);

        $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "INSERT INTO {$this->table} (season, phase, round, team_code, slot_number, player_id)" . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            " VALUES (%s, %d, %d, %s, %d, %d)" .
            " ON DUPLICATE KEY UPDATE player_id = VALUES(player_id), updated_at = NOW()",
            $season, $phase, $round, $teamCode, $slot, $playerId
        ));

        wp_cache_flush_group(self::CACHE_GROUP);
    }

    public function clearSlot(string $season, int $phase, int $round, string $teamCode, int $slot): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "INSERT INTO {$this->table} (season, phase, round, team_code, slot_number, player_id)" . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            " VALUES (%s, %d, %d, %s, %d, NULL)" .
            " ON DUPLICATE KEY UPDATE player_id = NULL, updated_at = NOW()",
            $season, $phase, $round, $teamCode, $slot
        ));

        wp_cache_flush_group(self::CACHE_GROUP);
    }

    public function clearTeam(string $season, int $phase, int $round, string $teamCode): void
    {
        global $wpdb;
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->table,
            ['player_id' => null],
            ['season' => $season, 'phase' => $phase, 'round' => $round, 'team_code' => $teamCode]
        );

        wp_cache_flush_group(self::CACHE_GROUP);
    }

    private function clearPlayerFromRound(string $season, int $phase, int $round, int $playerId): void
    {
        global $wpdb;
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->table,
            ['player_id' => null],
            ['season' => $season, 'phase' => $phase, 'round' => $round, 'player_id' => $playerId]
        );
    }
}
