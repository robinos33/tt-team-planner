<?php
declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Drop custom tables
$tables = [
    $wpdb->prefix . 'tttp_players',
    $wpdb->prefix . 'tttp_availabilities',
    $wpdb->prefix . 'tttp_team_compositions',
];

foreach ($tables as $table) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
}

// Remove plugin options
$options = [
    'ttp_db_version',
    'ttp_api_url',
    'ttp_api_token',
    'ttp_active_season',
    'ttp_active_phase',
    'ttp_max_foreign_players',
    'ttp_teams',
    'ttp_sms_template_availability',
    'ttp_sms_template_confirmation',
    'ttp_last_sync',
];

foreach ($options as $option) {
    delete_option($option);
}
