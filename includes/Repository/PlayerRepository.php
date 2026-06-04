<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Repository; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- PSR-4, TT\TeamPlanner est le préfixe plugin

use TT\TeamPlanner\Domain\Player;

class PlayerRepository
{
    private const CACHE_GROUP = 'ttp_players';

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tttp_players';
    }

    /** @return Player[] */
    public function findAll(): array
    {
        $cached = wp_cache_get('all', self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows   = $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY ranking DESC", ARRAY_A);
        $result = array_map([Player::class, 'fromRow'], $rows ?: []);

        wp_cache_set('all', $result, self::CACHE_GROUP);
        return $result;
    }

    public function findById(int $id): ?Player
    {
        $key    = "id_{$id}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached ?: null; // @phpstan-ignore-line
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
        $player = $row ? Player::fromRow($row) : null;

        wp_cache_set($key, $player ?? false, self::CACHE_GROUP);
        return $player;
    }

    /** @return Player[] */
    public function findByTeam(string $teamCode): array
    {
        $key    = 'team_' . sanitize_key($teamCode);
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows   = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE usual_team = %s ORDER BY ranking DESC", $teamCode),
            ARRAY_A
        );
        $result = array_map([Player::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    /**
     * Upsert depuis DataPing : met à jour uniquement les champs FFTT.
     * Les champs manuels (phone, usual_team, is_captain, is_mutation,
     * is_burned, notes) sont préservés lors des mises à jour.
     */
    public function upsertFromMonClubTT(array $data): int
    {
        global $wpdb;

        $externalId = sanitize_text_field($data['external_id'] ?? '');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->table} WHERE external_id = %s", $externalId)
        );

        $ffttFields = [
            'external_id'    => $externalId,
            'license_number' => sanitize_text_field($data['license_number'] ?? $externalId),
            'first_name'     => sanitize_text_field($data['first_name']     ?? ''),
            'last_name'      => sanitize_text_field($data['last_name']      ?? ''),
            'ranking'        => (int) ($data['ranking']   ?? 0),
            'is_foreign'     => (int) (bool) ($data['is_foreign'] ?? false),
            'is_young'       => (int) (bool) ($data['is_young']   ?? false),
            'raw_payload'    => sanitize_textarea_field($data['raw_payload'] ?? ''),
            'synced_at'      => current_time('mysql'),
        ];

        if ($existing) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->update($this->table, $ffttFields, ['id' => (int) $existing]);
            $this->invalidateCache();
            return (int) $existing;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert($this->table, array_merge($ffttFields, [
            'phone'       => '',
            'usual_team'  => '',
            'is_captain'  => 0,
            'is_mutation' => 0,
            'is_burned'   => 0,
            'notes'       => '',
        ]));

        $this->invalidateCache();
        return $wpdb->insert_id;
    }

    /**
     * Upsert complet (admin) — écrase tous les champs.
     */
    public function upsert(array $data): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->table} WHERE external_id = %s", $data['external_id'] ?? '')
        );

        $row = [
            'external_id'    => sanitize_text_field($data['external_id']    ?? ''),
            'first_name'     => sanitize_text_field($data['first_name']     ?? ''),
            'last_name'      => sanitize_text_field($data['last_name']      ?? ''),
            'license_number' => sanitize_text_field($data['license_number'] ?? ''),
            'phone'          => sanitize_text_field($data['phone']           ?? ''),
            'ranking'        => (int) ($data['ranking'] ?? 0),
            'usual_team'     => sanitize_text_field($data['usual_team']     ?? ''),
            'is_foreign'     => (int) (bool) ($data['is_foreign']           ?? false),
            'is_captain'     => (int) (bool) ($data['is_captain']           ?? false),
            'is_young'       => (int) (bool) ($data['is_young']             ?? false),
            'is_mutation'    => (int) (bool) ($data['is_mutation']          ?? false),
            'is_burned'      => (int) (bool) ($data['is_burned']            ?? false),
            'notes'          => sanitize_textarea_field($data['notes']      ?? ''),
            'raw_payload'    => wp_json_encode($data),
            'synced_at'      => current_time('mysql'),
        ];

        if ($existing) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->update($this->table, $row, ['id' => (int) $existing]);
            $this->invalidateCache();
            return (int) $existing;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert($this->table, $row);
        $this->invalidateCache();
        return $wpdb->insert_id;
    }

    public function updateContactInfo(int $id, string $phone, string $notes): bool
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $ok = (bool) $wpdb->update(
            $this->table,
            [
                'phone' => sanitize_text_field($phone),
                'notes' => sanitize_textarea_field($notes),
            ],
            ['id' => $id]
        );

        if ($ok) {
            $this->invalidateCache($id);
        }
        return $ok;
    }

    public function count(): int
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
    }

    private function invalidateCache(?int $id = null): void
    {
        wp_cache_flush_group(self::CACHE_GROUP);
    }
}
