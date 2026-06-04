<?php
declare(strict_types=1);

namespace TT\TeamPlanner;

use TT\TeamPlanner\Admin\SettingsPage;
use TT\TeamPlanner\Front\Assets;
use TT\TeamPlanner\Front\Shortcode;
use TT\TeamPlanner\Front\StandaloneTemplate;
use TT\TeamPlanner\Rest\PlayersController;
use TT\TeamPlanner\Rest\AvailabilityController;
use TT\TeamPlanner\Rest\TeamsController;
use TT\TeamPlanner\Rest\SyncController;
use TT\TeamPlanner\Rest\SeasonController;

final class Plugin
{
    private static ?Plugin $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('plugins_loaded', [$this, 'loadTextDomain']);
        add_action('plugins_loaded', [Activator::class, 'maybeUpgrade']);
        add_action('init',           [$this, 'init']);
        add_action('rest_api_init',  [$this, 'registerRestRoutes']);

        if (is_admin()) {
            add_action('admin_menu', [$this, 'registerAdminMenu']);
            add_action('admin_init', [$this, 'registerSettings']);
        }
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            TTP_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(TTP_PLUGIN_FILE)) . '/languages'
        );
    }

    public function init(): void
    {
        (new StandaloneTemplate())->register();

        // Le shortcode et les assets classiques restent pour la compatibilité
        // (éditeur de blocs, preview admin, etc.) mais la vraie page
        // est servie par StandaloneTemplate avant que le thème ne s'affiche.
        (new Shortcode())->register();
        (new Assets())->register();
    }

    public function registerRestRoutes(): void
    {
        (new PlayersController())->registerRoutes();
        (new AvailabilityController())->registerRoutes();
        (new TeamsController())->registerRoutes();
        (new SyncController())->registerRoutes();
        (new SeasonController())->registerRoutes();
    }

    public function registerAdminMenu(): void
    {
        add_menu_page(
            __('TT Team Planner', 'tt-team-planner'),
            __('TT Team Planner', 'tt-team-planner'),
            'manage_options',
            'tt-team-planner',
            [new SettingsPage(), 'render'],
            'dashicons-groups',
            30
        );

        // Renomme la première entrée auto-générée en "Réglages"
        // (callback vide : WordPress utilise déjà celui de add_menu_page)
        add_submenu_page(
            'tt-team-planner',
            __('Réglages', 'tt-team-planner'),
            __('Réglages', 'tt-team-planner'),
            'manage_options',
            'tt-team-planner'
        );

        // Pas de sous-menu "Synchronisation" séparé :
        // le bouton de synchro est intégré à la page Réglages.
    }

    public function registerSettings(): void
    {
        (new SettingsPage())->registerSettings();
    }
}
