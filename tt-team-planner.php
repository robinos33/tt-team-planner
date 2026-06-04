<?php
declare(strict_types=1);

/**
 * Plugin Name: TT Team Planner
 * Plugin URI:  https://github.com/ustalencett/tt-team-planner
 * Description: Centralisez les disponibilités et préparez les compositions d'équipes pour votre club de tennis de table.
 * Version:     1.0.2
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author:      US Talence Tennis de Table
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tt-team-planner
 * Domain Path: /languages
 */

namespace TT\TeamPlanner;

if (! defined('ABSPATH')) {
    exit;
}

define('TTP_VERSION',    '1.0.2');
define('TTP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TTP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TTP_PLUGIN_FILE', __FILE__);
define('TTP_TEXT_DOMAIN', 'tt-team-planner');

// Load all includes (no Composer autoloader needed — compatible with shared hosting)
$ttp_includes = [
    'includes/Domain/Player.php',
    'includes/Domain/Availability.php',
    'includes/Domain/TeamComposition.php',
    'includes/Domain/RuleViolation.php',
    'includes/Repository/PlayerRepository.php',
    'includes/Repository/AvailabilityRepository.php',
    'includes/Repository/TeamCompositionRepository.php',
    'includes/Rules/CompositionRuleInterface.php',
    'includes/Rules/AvailabilityRule.php',
    'includes/Rules/MaxForeignPlayersRule.php',
    'includes/Rules/DuplicatePlayerRule.php',
    'includes/Rules/BurnRule.php',
    'includes/Admin/SettingsPage.php',
    'includes/Rest/PlayersController.php',
    'includes/Rest/AvailabilityController.php',
    'includes/Rest/TeamsController.php',
    'includes/Rest/SyncController.php',
    'includes/Rest/SeasonController.php',
    'includes/Front/Shortcode.php',
    'includes/Front/Assets.php',
    'includes/Front/StandaloneTemplate.php',
    'includes/Activator.php',
    'includes/Deactivator.php',
    'includes/Plugin.php',
];

foreach ($ttp_includes as $file) {
    require_once TTP_PLUGIN_DIR . $file;
}

register_activation_hook(__FILE__,   [Activator::class,   'activate']);
register_deactivation_hook(__FILE__, [Deactivator::class, 'deactivate']);

Plugin::getInstance();
