<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Repository;

use TT\TeamPlanner\Domain\Availability;

class AvailabilityRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tttp_availabilities';
    }

    /** @return Availability[] */
    public function findByRound(string $season, int $phase, int $round): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d AND round = %d",
                $season, $phase, $round
            ),
            ARRAY_A
        );
        return array_map([Availability::class, 'fromRow'], $rows ?: []);
    }

    /** @return Availability[] */
    public function findByPlayer(int $playerId, string $season): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE player_id = %d AND season = %s ORDER BY phase, round",
                $playerId, $season
            ),
            ARRAY_A
        );
        return array_map([Availability::class, 'fromRow'], $rows ?: []);
    }

    /** @return Availability[] */
    public function findBySeason(string $season): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s ORDER BY player_id, phase, round",
                $season
            ),
            ARRAY_A
        );
        return array_map([Availability::class, 'fromRow'], $rows ?: []);
    }

    /** @return Availability[] */
    public function findByPhase(string $season, int $phase): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d ORDER BY player_id, round",
                $season, $phase
            ),
            ARRAY_A
        );
        return array_map([Availability::class, 'fromRow'], $rows ?: []);
    }

    public function save(int $playerId, string $season, int $phase, int $round, string $status, string $comment = ''): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$this->table} (player_id, season, phase, round, status, comment)
             VALUES (%d, %s, %d, %d, %s, %s)
             ON DUPLICATE KEY UPDATE status = VALUES(status), comment = VALUES(comment), updated_at = NOW()",
            $playerId, $season, $phase, $round, $status, $comment
        ));
    }
}
