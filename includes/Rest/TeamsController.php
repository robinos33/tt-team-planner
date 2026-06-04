<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Rest;

use WP_REST_Request;
use WP_REST_Response;
use TT\TeamPlanner\Repository\PlayerRepository;
use TT\TeamPlanner\Repository\AvailabilityRepository;
use TT\TeamPlanner\Repository\TeamCompositionRepository;
use TT\TeamPlanner\Rules\AvailabilityRule;
use TT\TeamPlanner\Rules\MaxForeignPlayersRule;
use TT\TeamPlanner\Rules\DuplicatePlayerRule;
use TT\TeamPlanner\Rules\BurnRule;

class TeamsController
{
    private const NS = 'ttp/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/compositions', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getCompositions'],
            'permission_callback' => [$this, 'canRead'],
        ]);

        register_rest_route(self::NS, '/compositions', [
            'methods'             => 'POST',
            'callback'            => [$this, 'setSlot'],
            'permission_callback' => [$this, 'canWrite'],
        ]);

        register_rest_route(self::NS, '/compositions', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'clearSlot'],
            'permission_callback' => [$this, 'canWrite'],
        ]);

        register_rest_route(self::NS, '/rules/check', [
            'methods'             => 'GET',
            'callback'            => [$this, 'checkRules'],
            'permission_callback' => [$this, 'canRead'],
        ]);
    }

    public function getCompositions(WP_REST_Request $request): WP_REST_Response
    {
        $season = sanitize_text_field($request->get_param('season') ?? get_option('ttp_active_season', ''));
        $phase  = (int) ($request->get_param('phase') ?? get_option('ttp_active_phase', 1));
        $round  = (int) ($request->get_param('round') ?? 1);

        $repo = new TeamCompositionRepository();
        $rows = $repo->findByRound($season, $phase, $round);

        return new WP_REST_Response(array_map(fn($c) => $c->toArray(), $rows), 200);
    }

    public function setSlot(WP_REST_Request $request): WP_REST_Response
    {
        $season   = sanitize_text_field($request->get_param('season') ?? get_option('ttp_active_season', ''));
        $phase    = (int) $request->get_param('phase');
        $round    = (int) $request->get_param('round');
        $teamCode = sanitize_text_field($request->get_param('team_code') ?? '');
        $slot     = (int) $request->get_param('slot_number');
        $playerId = (int) $request->get_param('player_id');

        if ($slot < 1 || $slot > 4 || ! $playerId || ! $teamCode) {
            return new WP_REST_Response(['message' => __('Paramètres invalides.', 'tt-team-planner')], 400);
        }

        (new TeamCompositionRepository())->setSlot($season, $phase, $round, $teamCode, $slot, $playerId);
        return new WP_REST_Response(['success' => true], 200);
    }

    public function clearSlot(WP_REST_Request $request): WP_REST_Response
    {
        $season   = sanitize_text_field($request->get_param('season') ?? get_option('ttp_active_season', ''));
        $phase    = (int) $request->get_param('phase');
        $round    = (int) $request->get_param('round');
        $teamCode = sanitize_text_field($request->get_param('team_code') ?? '');
        $slot     = (int) $request->get_param('slot_number');

        (new TeamCompositionRepository())->clearSlot($season, $phase, $round, $teamCode, $slot);
        return new WP_REST_Response(['success' => true], 200);
    }

    public function checkRules(WP_REST_Request $request): WP_REST_Response
    {
        $season = sanitize_text_field($request->get_param('season') ?? get_option('ttp_active_season', ''));
        $phase  = (int) ($request->get_param('phase') ?? get_option('ttp_active_phase', 1));
        $round  = (int) ($request->get_param('round') ?? 1);

        $playerRepo = new PlayerRepository();
        $availRepo  = new AvailabilityRepository();
        $compoRepo  = new TeamCompositionRepository();

        $allPlayers    = $playerRepo->findAll();
        $allAvailRows  = $availRepo->findByRound($season, $phase, $round);
        $compositions  = $compoRepo->findByRound($season, $phase, $round);

        // Build availability map
        $availMap = [];
        foreach ($allAvailRows as $a) {
            $availMap[$a->playerId] = $a->status;
        }

        // Build player map
        $playerMap = [];
        foreach ($allPlayers as $p) {
            $playerMap[$p->id] = $p;
        }

        // Group compositions by team
        $byTeam = [];
        foreach ($compositions as $c) {
            if ($c->playerId) {
                $byTeam[$c->teamCode][] = $c->playerId;
            }
        }

        // Build player-to-teams map for duplicate detection
        $playerTeamMap = [];
        foreach ($byTeam as $teamCode => $playerIds) {
            foreach ($playerIds as $pid) {
                $playerTeamMap[$pid][] = $teamCode;
            }
        }

        $teams     = get_option('ttp_teams', []);
        $teamOrder = array_column($teams, 'code');
        $maxE      = (int) get_option('ttp_max_foreign_players', 2);

        $rules = [
            new MaxForeignPlayersRule($maxE),
            new AvailabilityRule($availMap),
            new DuplicatePlayerRule($playerTeamMap),
            new BurnRule($teamOrder),
        ];

        $context    = ['season' => $season, 'phase' => $phase, 'round' => $round];
        $violations = [];

        foreach ($byTeam as $teamCode => $playerIds) {
            $players = array_filter(array_map(fn($id) => $playerMap[$id] ?? null, $playerIds));
            foreach ($rules as $rule) {
                $violations = array_merge($violations, $rule->check(array_values($players), $teamCode, $context));
            }
        }

        return new WP_REST_Response(array_map(fn($v) => $v->toArray(), $violations), 200);
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
