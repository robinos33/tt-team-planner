<?php
declare(strict_types=1);

/**
 * Plugin Name: Tableau Équipes
 * Plugin URI: https://github.com/vibe-kanban/tableau-equipes
 * Description: Extension WordPress pour gérer le déplacement de joueurs dans des équipes sur plusieurs journées de championnat de tennis de table
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Vibe Kanban
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tableau-equipes
 * Domain Path: /languages
 */

namespace VibeKanban\TableauEquipes;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TABLEAU_EQUIPES_VERSION', '1.0.0');
define('TABLEAU_EQUIPES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TABLEAU_EQUIPES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TABLEAU_EQUIPES_PLUGIN_FILE', __FILE__);

// Load Composer autoloader
if (file_exists(TABLEAU_EQUIPES_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once TABLEAU_EQUIPES_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Main plugin class
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    /**
     * Get singleton instance
     */
    public static function getInstance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - initialize plugin
     */
    private function __construct()
    {
        $this->checkDependencies();
        $this->initHooks();
    }

    /**
     * Check required dependencies
     */
    private function checkDependencies(): void
    {
        // Check if DataPing plugin is active
        if (!class_exists('DataPing')) {
            add_action('admin_notices', [$this, 'dataPingMissingNotice']);
            return;
        }
    }

    /**
     * Display notice when DataPing is missing
     */
    public function dataPingMissingNotice(): void
    {
        $class = 'notice notice-error';
        $message = sprintf(
            /* translators: %s: DataPing GitHub URL */
            __('Tableau Équipes nécessite le plugin DataPing pour fonctionner. Veuillez installer et activer <a href="%s" target="_blank">DataPing</a>.', 'tableau-equipes'),
            'https://github.com/robinos33/DataPing'
        );
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks(): void
    {
        register_activation_hook(TABLEAU_EQUIPES_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(TABLEAU_EQUIPES_PLUGIN_FILE, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'loadTextDomain']);
        add_action('init', [$this, 'init']);
    }

    /**
     * Plugin activation
     */
    public function activate(): void
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            deactivate_plugins(plugin_basename(TABLEAU_EQUIPES_PLUGIN_FILE));
            wp_die(
                esc_html__('Tableau Équipes nécessite PHP 8.1 ou supérieur.', 'tableau-equipes'),
                esc_html__('Erreur d\'activation du plugin', 'tableau-equipes'),
                ['back_link' => true]
            );
        }

        // Create default options
        $this->createDefaultOptions();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create default plugin options
     */
    private function createDefaultOptions(): void
    {
        // Option to store authorized users (empty by default, only superadmin can access)
        if (get_option('tableau_equipes_authorized_users') === false) {
            add_option('tableau_equipes_authorized_users', []);
        }
    }

    /**
     * Load plugin text domain for translations
     */
    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'tableau-equipes',
            false,
            dirname(plugin_basename(TABLEAU_EQUIPES_PLUGIN_FILE)) . '/languages'
        );
    }

    /**
     * Initialize plugin
     */
    public function init(): void
    {
        // Only load admin functionality in admin area
        if (is_admin()) {
            $this->initAdmin();
        }
    }

    /**
     * Initialize admin functionality
     */
    private function initAdmin(): void
    {
        // Check if user is authorized (superadmin or in authorized list)
        if (!$this->isUserAuthorized()) {
            return;
        }

        // Load admin controller
        if (file_exists(TABLEAU_EQUIPES_PLUGIN_DIR . 'admin/class-admin-controller.php')) {
            require_once TABLEAU_EQUIPES_PLUGIN_DIR . 'admin/class-admin-controller.php';
            // Admin controller will be initialized when the file is created
        }
    }

    /**
     * Check if current user is authorized to access plugin
     */
    private function isUserAuthorized(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        // Superadmins always have access
        if (is_super_admin()) {
            return true;
        }

        // Check if user is in authorized list
        $authorizedUsers = get_option('tableau_equipes_authorized_users', []);
        $currentUserId = get_current_user_id();

        return in_array($currentUserId, $authorizedUsers, true);
    }
}

// Initialize the plugin
Plugin::getInstance();
