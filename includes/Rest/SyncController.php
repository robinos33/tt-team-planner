<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Rest;

use WP_REST_Request;
use WP_REST_Response;
use TT\TeamPlanner\Repository\PlayerRepository;

/**
 * POST /ttp/v1/players/sync
 *
 * Pulls player data from MonClubTT (via the 'monclubtt_get_joueurs' WP filter)
 * and upserts it into wp_tttp_players.
 *
 * MonClubTT fields used:  licence, nom, prénom, classement (points mensuels),
 *                        nationalité (étranger), catégorie (jeune).
 * Manual fields preserved on update: phone, usual_team, is_captain,
 *                                    is_mutation, is_burned, notes.
 */
class SyncController
{
    private const NS = 'ttp/v1';

    /** FFTT youth categories that map to is_young = 1 */
    private const YOUTH_CATEGORIES = ['B', 'M', 'C', 'J']; // benjamin, minime, cadet, junior

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/players/sync', [
            'methods'             => 'POST',
            'callback'            => [$this, 'sync'],
            'permission_callback' => [$this, 'canSync'],
        ]);
    }

    public function sync(WP_REST_Request $request): WP_REST_Response
    {
        // MonClubTT must be active
        if (! has_filter('monclubtt_get_joueurs')) {
            return new WP_REST_Response(
                ['message' => __('Le plugin MonClubTT est requis et doit être activé.', 'tt-team-planner')],
                503
            );
        }

        /** @var \Joueur[]|mixed $joueurs */
        $joueurs = apply_filters('monclubtt_get_joueurs', 'MF');

        if (! is_array($joueurs) || empty($joueurs)) {
            return new WP_REST_Response(
                ['message' => __("MonClubTT n'a retourné aucun joueur. Vérifiez sa configuration.", 'tt-team-planner')],
                502
            );
        }

        $repo   = new PlayerRepository();
        $synced = 0;
        $errors = 0;

        foreach ($joueurs as $joueur) {
            // Guard: MonClubTT may return non-objects if API is misconfigured
            if (! is_object($joueur) || ! method_exists($joueur, 'getLicence')) {
                $errors++;
                continue;
            }

            $classement = $joueur->getClassement();
            $ranking    = $classement ? (int) round((float) $classement->getPointsMensuels()) : 0;
            $categorie  = strtoupper((string) $joueur->getCategorie());

            $repo->upsertFromMonClubTT([
                'external_id'    => $joueur->getLicence(),
                'license_number' => $joueur->getLicence(),
                'first_name'     => $joueur->getPrenom(),
                'last_name'      => $joueur->getNom(),
                'ranking'        => $ranking,
                'is_foreign'     => $joueur->isEtranger() ? 1 : 0,
                'is_young'       => in_array($categorie, self::YOUTH_CATEGORIES, true) ? 1 : 0,
                'raw_payload'    => wp_json_encode([
                    'licence'    => $joueur->getLicence(),
                    'classement' => $ranking,
                    'categorie'  => $categorie,
                    'sexe'       => $joueur->getSexe(),
                ]),
            ]);
            $synced++;
        }

        update_option('ttp_last_sync', current_time('mysql'));

        $message = sprintf(
            /* translators: 1: count synced, 2: count errors */
            _n('%d joueur synchronisé.', '%d joueurs synchronisés.', $synced, 'tt-team-planner'),
            $synced
        );

        if ($errors > 0) {
            $message .= ' ' . sprintf(
                __('%d entrée(s) ignorée(s) (données invalides).', 'tt-team-planner'),
                $errors
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'synced'  => $synced,
            'errors'  => $errors,
            'message' => $message,
        ], 200);
    }

    public function canSync(): bool
    {
        return current_user_can('manage_options');
    }
}
