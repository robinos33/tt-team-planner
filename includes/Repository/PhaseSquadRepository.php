<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Repository; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- PSR-4, TT\TeamPlanner est le préfixe plugin

use TT\TeamPlanner\Domain\PhaseSquad;

class PhaseSquadRepository
{
    private const CACHE_GROUP = 'ttp_squads';

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tttp_phase_squads';
    }

    /** @return PhaseSquad[] */
    public function findBySeasonPhase(string $season, int $phase): array
    {
        $key    = "{$season}_p{$phase}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows   = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d ORDER BY team_code, position ASC",
                $season,
                $phase
            ),
            ARRAY_A
        );
        $result = array_map([PhaseSquad::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    /** @return PhaseSquad[] */
    public function findByTeam(string $season, int $phase, string $teamCode): array
    {
        $key    = "{$season}_p{$phase}_t" . sanitize_key($teamCode);
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows   = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d AND team_code = %s ORDER BY position ASC",
                $season,
                $phase,
                $teamCode
            ),
            ARRAY_A
        );
        $result = array_map([PhaseSquad::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    public function add(string $season, int $phase, string $teamCode, int $playerId): bool
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $maxPos = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(MAX(position), 0) FROM {$this->table} WHERE season = %s AND phase = %d AND team_code = %s",
                $season,
                $phase,
                $teamCode
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert($this->table, [
            'season'    => $season,
            'phase'     => $phase,
            'team_code' => $teamCode,
            'player_id' => $playerId,
            'position'  => $maxPos + 1,
        ]);

        if ($result !== false) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }

        return $result !== false;
    }

    public function remove(string $season, int $phase, string $teamCode, int $playerId): bool
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $ok = (bool) $wpdb->delete($this->table, [
            'season'    => $season,
            'phase'     => $phase,
            'team_code' => $teamCode,
            'player_id' => $playerId,
        ]);

        if ($ok) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }

        return $ok;
    }

    public function existsInAnyTeam(string $season, int $phase, int $playerId): bool
    {
        $key    = "exists_{$season}_p{$phase}_{$playerId}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return (bool) $cached;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE season = %s AND phase = %d AND player_id = %d",
                $season,
                $phase,
                $playerId
            )
        );

        wp_cache_set($key, (int) $result, self::CACHE_GROUP);
        return $result;
    }
}
