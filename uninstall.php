<?php
declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- préfixe ttp_ conforme à la convention interne
// Drop custom tables
$ttp_tables = [
    $wpdb->prefix . 'tttp_players',
    $wpdb->prefix . 'tttp_availabilities',
    $wpdb->prefix . 'tttp_team_compositions',
    $wpdb->prefix . 'tttp_phase_squads',
];

foreach ($ttp_tables as $ttp_table) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( "DROP TABLE IF EXISTS `{$ttp_table}`" );
}

// Remove plugin options
$ttp_options = [
    'ttp_db_version',
    'ttp_api_url',
    'ttp_api_token',
    'ttp_active_season',
    'ttp_active_phase',
    'ttp_max_foreign_players',
    'ttp_teams',
    'ttp_club_name',
    'ttp_sms_template_availability',
    'ttp_sms_template_confirmation',
    'ttp_journee_dates_p1',
    'ttp_journee_dates_p2',
    'ttp_last_sync',
];

foreach ($ttp_options as $ttp_option) {
    delete_option( $ttp_option );
}
// phpcs:enable
