<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Repository; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- PSR-4, TT\TeamPlanner est le préfixe plugin

use TT\TeamPlanner\Domain\Availability;

class AvailabilityRepository
{
    private const CACHE_GROUP = 'ttp_availability';

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tttp_availabilities';
    }

    /** @return Availability[] */
    public function findByRound(string $season, int $phase, int $round): array
    {
        $key    = "{$season}_p{$phase}_r{$round}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows   = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d AND round = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season, $phase, $round
            ),
            ARRAY_A
        );
        $result = array_map([Availability::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    /** @return Availability[] */
    public function findByPlayer(int $playerId, string $season): array
    {
        $key    = "player_{$playerId}_{$season}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows   = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE player_id = %d AND season = %s ORDER BY phase, round", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $playerId, $season
            ),
            ARRAY_A
        );
        $result = array_map([Availability::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    /** @return Availability[] */
    public function findBySeason(string $season): array
    {
        $key    = "season_{$season}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows   = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s ORDER BY player_id, phase, round", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season
            ),
            ARRAY_A
        );
        $result = array_map([Availability::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    /** @return Availability[] */
    public function findByPhase(string $season, int $phase): array
    {
        $key    = "{$season}_p{$phase}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows   = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d ORDER BY player_id, round", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season, $phase
            ),
            ARRAY_A
        );
        $result = array_map([Availability::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    public function save(int $playerId, string $season, int $phase, int $round, string $status, string $comment = ''): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->table} (player_id, season, phase, round, status, comment) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
             VALUES (%d, %s, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE status = VALUES(status), comment = VALUES(comment), updated_at = NOW()",
            $playerId, $season, $phase, $round, $status, $comment
        ));

        wp_cache_flush_group(self::CACHE_GROUP);
    }
}
