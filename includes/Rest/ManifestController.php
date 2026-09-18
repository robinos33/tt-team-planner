<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Rest; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- PSR-4, TT\TeamPlanner est le préfixe plugin

use WP_REST_Request;
use WP_REST_Response;
use TT\TeamPlanner\Front\Assets;

/**
 * GET /ttp/v1/manifest
 *
 * Génère le Web App Manifest (installation PWA) à partir des réglages du
 * plugin (nom du club, couleur, icône). Route publique : le manifest n'a
 * rien de sensible et doit rester accessible même hors session authentifiée.
 */
class ManifestController
{
    private const NS = 'ttp/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/manifest', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        if (! Assets::pwaEnabled()) {
            return new WP_REST_Response(
                ['message' => __("Installation PWA désactivée dans les réglages.", 'tt-team-planner')],
                404
            );
        }

        $clubName = (string) get_option('ttp_club_name', get_bloginfo('name'));
        $startUrl = $this->findFrontPageUrl();

        $response = new WP_REST_Response([
            'name'             => $clubName,
            'short_name'       => wp_trim_words($clubName, 2, ''),
            'description'      => __('Gérez les compositions d’équipes et disponibilités du club.', 'tt-team-planner'),
            'start_url'        => $startUrl,
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => '#f5f7fb',
            'theme_color'      => '#2563eb',
            'lang'             => 'fr',
            'icons'            => Assets::pwaIcons(),
        ], 200);

        $response->header('Content-Type', 'application/manifest+json');

        return $response;
    }

    /** Retrouve l'URL de la page publiée contenant [tt_team_planner], sinon l'accueil du site. */
    private function findFrontPageUrl(): string
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lecture ponctuelle à chaque requête manifest, pas de valeur à mettre en cache
        $page = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_status  = 'publish'
                   AND post_type    = 'page'
                   AND post_content LIKE %s
                 LIMIT 1",
                '%[tt_team_planner]%'
            )
        );

        if ($page) {
            $url = get_permalink((int) $page->ID);
            if ($url) {
                return $url;
            }
        }

        return home_url('/');
    }
}
