<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Repository;

use TT\TeamPlanner\Domain\Player;

class PlayerRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tttp_players';
    }

    /** @return Player[] */
    public function findAll(): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY ranking DESC", ARRAY_A);
        return array_map([Player::class, 'fromRow'], $rows ?: []);
    }

    public function findById(int $id): ?Player
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ? Player::fromRow($row) : null;
    }

    /** @return Player[] */
    public function findByTeam(string $teamCode): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE usual_team = %s ORDER BY ranking DESC", $teamCode),
            ARRAY_A
        );
        return array_map([Player::class, 'fromRow'], $rows ?: []);
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

        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->table} WHERE external_id = %s", $externalId)
        );

        // Champs issus de la FFTT via DataPing — toujours écrasés
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
            // UPDATE : uniquement les champs FFTT — les champs manuels sont inchangés
            $wpdb->update($this->table, $ffttFields, ['id' => (int) $existing]);
            return (int) $existing;
        }

        // INSERT : champs FFTT + champs manuels avec valeurs vides par défaut
        $wpdb->insert($this->table, array_merge($ffttFields, [
            'phone'       => '',
            'usual_team'  => '',
            'is_captain'  => 0,
            'is_mutation' => 0,
            'is_burned'   => 0,
            'notes'       => '',
        ]));

        return $wpdb->insert_id;
    }

    /**
     * Upsert complet (admin) — écrase tous les champs.
     * À utiliser uniquement depuis l'interface d'administration TTP.
     */
    public function upsert(array $data): int
    {
        global $wpdb;

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
            $wpdb->update($this->table, $row, ['id' => (int) $existing]);
            return (int) $existing;
        }

        $wpdb->insert($this->table, $row);
        return $wpdb->insert_id;
    }

    public function updateNotes(int $id, string $notes): bool
    {
        global $wpdb;
        return (bool) $wpdb->update(
            $this->table,
            ['notes' => sanitize_textarea_field($notes)],
            ['id'    => $id]
        );
    }

    public function count(): int
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
    }
}
