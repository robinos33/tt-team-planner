<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Rest; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- PSR-4, TT\TeamPlanner est le préfixe plugin

use WP_REST_Request;
use WP_REST_Response;
use TT\TeamPlanner\Repository\PlayerRepository;

class PlayersController
{
    private const NS = 'ttp/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/players', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPlayers'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/players/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPlayer'],
            'permission_callback' => '__return_true',
            'args' => ['id' => ['validate_callback' => fn($v) => is_numeric($v)]],
        ]);

        register_rest_route(self::NS, '/players/(?P<id>\d+)', [
            'methods'             => 'PATCH',
            'callback'            => [$this, 'updatePlayer'],
            'permission_callback' => [$this, 'canWrite'],
            'args' => ['id' => ['validate_callback' => fn($v) => is_numeric($v)]],
        ]);
    }

    public function getPlayers(WP_REST_Request $request): WP_REST_Response
    {
        $repo    = new PlayerRepository();
        $players = $repo->findAll();
        return new WP_REST_Response(array_map(fn($p) => $p->toArray(), $players), 200);
    }

    public function getPlayer(WP_REST_Request $request): WP_REST_Response
    {
        $repo   = new PlayerRepository();
        $player = $repo->findById((int) $request['id']);

        if (! $player) {
            return new WP_REST_Response(['message' => __('Joueur introuvable.', 'tt-team-planner')], 404);
        }

        return new WP_REST_Response($player->toArray(), 200);
    }

    public function updatePlayer(WP_REST_Request $request): WP_REST_Response
    {
        $repo  = new PlayerRepository();
        $notes = sanitize_textarea_field($request->get_param('notes') ?? '');
        $phone = sanitize_text_field($request->get_param('phone') ?? '');
        $ok    = $repo->updateContactInfo((int) $request['id'], $phone, $notes);

        if (! $ok) {
            return new WP_REST_Response(['success' => false], 500);
        }

        $player = $repo->findById((int) $request['id']);
        return new WP_REST_Response($player?->toArray(), 200);
    }

    public function canRead(): bool
    {
        return current_user_can('read');
    }

    public function canWrite(): bool
    {
        return current_user_can('edit_posts');
    }
}
