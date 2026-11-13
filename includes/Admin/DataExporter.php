<?php

declare(strict_types=1);

namespace TT\TeamPlanner\Admin;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- PSR-4, TT\TeamPlanner est le préfixe plugin

/**
 * Export complet des données du plugin au format JSON, pour les réimporter
 * ailleurs (MonClubTT notamment). Les identifiants SQL sont conservés tels
 * quels : les tables se référencent par `player_id` → `players.id`, à
 * remapper à l'import (correspondance via `license_number` ou `external_id`).
 *
 * Les liens magiques ne sont pas exportés : ce sont des jetons d'accès
 * temporaires, sans valeur hors de ce site.
 */
class DataExporter
{
    public const FORMAT         = 'tt-team-planner-export';
    public const FORMAT_VERSION = 1;
    public const ACTION         = 'ttp_export_data';

    /** Options exportées (réglages + métadonnées de synchronisation). */
    private const OPTIONS = [
        'ttp_club_name',
        'ttp_pwa_enabled',
        'ttp_teams',
        'ttp_sms_template_availability',
        'ttp_sms_template_confirmation',
        'ttp_journee_dates_p1',
        'ttp_journee_dates_p2',
        'ttp_last_sync',
        'ttp_last_appearance_import',
        'ttp_db_version',
    ];

    /**
     * Tables exportées (clé JSON → suffixe de table) et colonnes entières,
     * converties en int pour que le JSON soit typé.
     */
    private const TABLES = [
        'players' => [
            'table' => 'tttp_players',
            'ints'  => ['id', 'ranking', 'best_official_points', 'is_foreign', 'is_young', 'is_mutation', 'is_active'],
        ],
        'availabilities' => [
            'table' => 'tttp_availabilities',
            'ints'  => ['id', 'player_id', 'phase', 'round'],
        ],
        'team_compositions' => [
            'table' => 'tttp_team_compositions',
            'ints'  => ['id', 'phase', 'round', 'slot_number', 'player_id', 'is_captain'],
        ],
        'phase_squads' => [
            'table' => 'tttp_phase_squads',
            'ints'  => ['id', 'phase', 'player_id', 'position', 'is_captain'],
        ],
        'match_appearances' => [
            'table' => 'tttp_match_appearances',
            'ints'  => ['id', 'phase', 'round', 'team_rank', 'player_id', 'slot_number', 'validated_by'],
        ],
        'validated_rounds' => [
            'table' => 'tttp_validated_rounds',
            'ints'  => ['id', 'phase', 'round', 'validated_by'],
        ],
        'team_forfeits' => [
            'table' => 'tttp_team_forfeits',
            'ints'  => ['id', 'phase', 'round', 'declared_by'],
        ],
        'audit_log' => [
            'table' => 'tttp_audit_log',
            'ints'  => ['id', 'user_id', 'phase', 'round'],
        ],
    ];

    /** Modèles de SMS personnels, stockés en user meta (un par membre). */
    private const USER_SMS_META = [
        'availability' => 'ttp_sms_template_availability',
        'confirmation' => 'ttp_sms_template_confirmation',
    ];

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handleDownload']);
    }

    public function handleDownload(): void
    {
        check_admin_referer(self::ACTION);

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission insuffisante.', 'tt-team-planner'));
        }

        $json = wp_json_encode(
            $this->build(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            wp_die(esc_html__("Impossible de générer l'export JSON.", 'tt-team-planner'));
        }

        $filename = sprintf('tt-team-planner-export-%s.json', gmdate('Y-m-d-His'));

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));

        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- téléchargement JSON brut, pas de rendu HTML
        exit;
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        $options = [];
        foreach (self::OPTIONS as $name) {
            $options[$name] = get_option($name, null);
        }

        $tables = [];
        foreach (self::TABLES as $key => $def) {
            $tables[$key] = $this->dumpTable($def['table'], $def['ints']);
        }

        return [
            'format'         => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'plugin_version' => TTP_VERSION,
            'exported_at'    => gmdate('c'),
            'site_url'       => home_url('/'),
            'counts'         => array_map('count', $tables),
            'options'        => $options,
            'tables'         => $tables,
            'user_sms_templates' => $this->userSmsTemplates(),
        ];
    }

    /**
     * SMS personnalisés par les membres, avec leur login et e-mail pour
     * retrouver le compte côté import (les ID utilisateurs peuvent différer).
     *
     * @return list<array{user_id: int, user_login: string, user_email: string, availability: ?string, confirmation: ?string}>
     */
    private function userSmsTemplates(): array
    {
        $users = get_users([
            'fields'       => ['ID', 'user_login', 'user_email'],
            'meta_query'   => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- export ponctuel
                'relation' => 'OR',
                ['key' => self::USER_SMS_META['availability'], 'compare' => 'EXISTS'],
                ['key' => self::USER_SMS_META['confirmation'], 'compare' => 'EXISTS'],
            ],
        ]);

        $result = [];
        foreach ($users as $user) {
            $row = [
                'user_id'    => (int) $user->ID,
                'user_login' => (string) $user->user_login,
                'user_email' => (string) $user->user_email,
            ];
            foreach (self::USER_SMS_META as $key => $metaKey) {
                $value     = get_user_meta((int) $user->ID, $metaKey, true);
                $row[$key] = is_string($value) && $value !== '' ? $value : null;
            }
            $result[] = $row;
        }
        return $result;
    }

    /**
     * @param list<string> $intColumns
     * @return list<array<string, mixed>>
     */
    private function dumpTable(string $suffix, array $intColumns): array
    {
        global $wpdb;
        $table = $wpdb->prefix . $suffix;

        // Table absente (migration pas encore passée) : liste vide plutôt
        // qu'une erreur SQL imprimée au milieu du fichier téléchargé.
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- export ponctuel
        if ($exists !== $table) {
            return [];
        }

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- export ponctuel, cache inutile
            "SELECT * FROM {$table} ORDER BY id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nom de table issu de $wpdb->prefix et d'une constante
            ARRAY_A
        );

        $intColumns = array_flip($intColumns);
        $result     = [];
        foreach ($rows ?: [] as $row) {
            foreach ($row as $col => $value) {
                if ($value !== null && isset($intColumns[$col])) {
                    $row[$col] = (int) $value;
                }
            }
            if (isset($row['raw_payload']) && is_string($row['raw_payload'])) {
                // Le payload FFTT est stocké en JSON : on le restitue en objet
                // plutôt qu'en chaîne échappée, s'il est décodable.
                $decoded = json_decode($row['raw_payload'], true);
                $row['raw_payload'] = is_array($decoded) ? $decoded : $row['raw_payload'];
            }
            $result[] = $row;
        }
        return $result;
    }
}
