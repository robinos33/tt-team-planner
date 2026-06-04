<?php
declare(strict_types=1);

namespace TT\TeamPlanner; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- PSR-4, TT\TeamPlanner est le préfixe plugin

class Activator
{
    public static function activate(): void
    {
        self::checkRequirements();
        self::createTables();
        self::seedDefaultOptions();
        flush_rewrite_rules();
    }

    /**
     * Applique les migrations de schéma si la version DB est dépassée.
     * À appeler à chaque chargement admin (pas seulement à l'activation).
     */
    public static function maybeUpgrade(): void
    {
        if (get_option('ttp_db_version') === TTP_VERSION) {
            return;
        }
        self::createTables(); // dbDelta() ajoute les colonnes manquantes sans rien casser
        self::seedDefaultOptions();
    }

    private static function checkRequirements(): void
    {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            deactivate_plugins(plugin_basename(TTP_PLUGIN_FILE));
            wp_die(
                esc_html__('TT Team Planner nécessite PHP 8.1 ou supérieur.', 'tt-team-planner'),
                esc_html__("Erreur d'activation", 'tt-team-planner'),
                ['back_link' => true]
            );
        }
    }

    private static function createTables(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [];

        $sql[] = "CREATE TABLE {$wpdb->prefix}tttp_players (
            id             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            external_id    varchar(100)        NOT NULL DEFAULT '',
            first_name     varchar(100)        NOT NULL DEFAULT '',
            last_name      varchar(100)        NOT NULL DEFAULT '',
            license_number varchar(50)         NOT NULL DEFAULT '',
            phone          varchar(30)         NOT NULL DEFAULT '',
            ranking        int(11)             NOT NULL DEFAULT 0,
            usual_team     varchar(20)         NOT NULL DEFAULT '',
            is_foreign     tinyint(1)          NOT NULL DEFAULT 0,
            is_captain     tinyint(1)          NOT NULL DEFAULT 0,
            is_young       tinyint(1)          NOT NULL DEFAULT 0,
            is_mutation    tinyint(1)          NOT NULL DEFAULT 0,
            is_burned      tinyint(1)          NOT NULL DEFAULT 0,
            notes          text                NOT NULL,
            raw_payload    longtext            NOT NULL,
            synced_at      datetime                     DEFAULT NULL,
            created_at     datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at     datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY external_id (external_id),
            KEY usual_team (usual_team)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}tttp_availabilities (
            id        bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            player_id bigint(20) UNSIGNED NOT NULL,
            season    varchar(20)         NOT NULL DEFAULT '',
            phase     tinyint(1)          NOT NULL DEFAULT 1,
            round     tinyint(2)          NOT NULL DEFAULT 1,
            status    enum('available','unavailable','uncertain','unknown') NOT NULL DEFAULT 'unknown',
            comment   text                NOT NULL,
            created_at datetime           NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY player_season_phase_round (player_id, season, phase, round),
            KEY season_phase_round (season, phase, round)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}tttp_team_compositions (
            id          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season      varchar(20)         NOT NULL DEFAULT '',
            phase       tinyint(1)          NOT NULL DEFAULT 1,
            round       tinyint(2)          NOT NULL DEFAULT 1,
            team_code   varchar(20)         NOT NULL DEFAULT '',
            slot_number tinyint(1)          NOT NULL DEFAULT 1,
            player_id   bigint(20) UNSIGNED          DEFAULT NULL,
            created_at  datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY season_phase_round_team_slot (season, phase, round, team_code, slot_number),
            KEY player_season (player_id, season, phase, round)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}tttp_phase_squads (
            id         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season     varchar(20)         NOT NULL DEFAULT '',
            phase      tinyint(1)          NOT NULL DEFAULT 1,
            team_code  varchar(20)         NOT NULL DEFAULT '',
            player_id  bigint(20) UNSIGNED NOT NULL,
            position   smallint(5)         NOT NULL DEFAULT 0,
            created_at datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY season_phase_team_player (season, phase, team_code, player_id),
            KEY season_phase (season, phase)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $query) {
            dbDelta($query);
        }

        update_option('ttp_db_version', TTP_VERSION);
    }

    private static function seedDefaultOptions(): void
    {
        $defaults = [
            'ttp_teams'                      => [],
            'ttp_club_name'                  => 'Mon Club TT',
            'ttp_sms_template_availability'  => 'Bonjour {prenom}, es-tu disponible pour la J{journee} ({date}) ? Merci de répondre. — ' . get_bloginfo('name'),
            'ttp_sms_template_confirmation'  => 'Bonjour {prenom}, tu joues en {equipe} pour la J{journee} le {date}. À bientôt ! — ' . get_bloginfo('name'),
            'ttp_last_sync'                  => null,
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
}
