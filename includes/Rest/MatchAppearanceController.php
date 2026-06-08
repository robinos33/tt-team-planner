<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Rest; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- PSR-4, TT\TeamPlanner est le préfixe plugin

use WP_REST_Request;
use WP_REST_Response;
use TT\TeamPlanner\Domain\BurnageChecker;
use TT\TeamPlanner\Repository\MatchAppearanceRepository;
use TT\TeamPlanner\Repository\TeamCompositionRepository;
use TT\TeamPlanner\Repository\ValidatedRoundRepository;

class MatchAppearanceController
{
    private const NS = 'ttp/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/appearances/validate', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getValidationStatus'],
            'permission_callback' => [$this, 'canRead'],
        ]);

        register_rest_route(self::NS, '/appearances/validate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'validateRound'],
            'permission_callback' => [$this, 'canWrite'],
        ]);

        register_rest_route(self::NS, '/appearances/validate', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'unvalidateRound'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NS, '/burnage', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getBurnageStatus'],
            'permission_callback' => [$this, 'canRead'],
        ]);
    }

    public function getValidationStatus(WP_REST_Request $request): WP_REST_Response
    {
        $season   = sanitize_text_field($request->get_param('season') ?? get_option('ttp_active_season', ''));
        $phase    = (int) ($request->get_param('phase') ?? get_option('ttp_active_phase', 1));
        $round    = (int) $request->get_param('round');
        $teamCode = sanitize_text_field($request->get_param('team_code') ?? '');

        if (! $season || ! $round || ! $teamCode) {
            return new WP_REST_Response(['validated' => false], 200);
        }

        $validated = (new ValidatedRoundRepository())->isValidated($season, $phase, $round, $teamCode);

        return new WP_REST_Response(['validated' => $validated], 200);
    }

    public function validateRound(WP_REST_Request $request): WP_REST_Response
    {
        $season   = sanitize_text_field($request->get_param('season') ?? get_option('ttp_active_season', ''));
        $phase    = (int) $request->get_param('phase');
        $round    = (int) $request->get_param('round');
        $teamCode = sanitize_text_field($request->get_param('team_code') ?? '');

        if (! $season || ! $phase || ! $round || ! $teamCode) {
            return new WP_REST_Response(['message' => __('Paramètres invalides.', 'tt-team-planner')], 400);
        }

        $compositionRepo = new TeamCompositionRepository();
        $slots           = $compositionRepo->findByTeamAndRound($season, $phase, $round, $teamCode);
        $filled          = array_filter($slots, fn($slot) => $slot->playerId !== null);

        if (count($filled) === 0) {
            return new WP_REST_Response(['message' => __('Aucun joueur affecté pour cette équipe et cette journée.', 'tt-team-planner')], 400);
        }

        $teamRank = BurnageChecker::extractTeamRank($teamCode) ?? 0;
        $userId   = get_current_user_id() ?: null;

        (new MatchAppearanceRepository())->recordForRound($season, $phase, $round, $teamCode, $teamRank, $filled, $userId);
        (new ValidatedRoundRepository())->markValidated($season, $phase, $round, $teamCode, $userId);

        return new WP_REST_Response(['success' => true], 200);
    }

    public function unvalidateRound(WP_REST_Request $request): WP_REST_Response
    {
        $season   = sanitize_text_field($request->get_param('season') ?? get_option('ttp_active_season', ''));
        $phase    = (int) $request->get_param('phase');
        $round    = (int) $request->get_param('round');
        $teamCode = sanitize_text_field($request->get_param('team_code') ?? '');

        if (! $season || ! $phase || ! $round || ! $teamCode) {
            return new WP_REST_Response(['message' => __('Paramètres invalides.', 'tt-team-planner')], 400);
        }

        (new MatchAppearanceRepository())->deleteForRound($season, $phase, $round, $teamCode);
        (new ValidatedRoundRepository())->unmarkValidated($season, $phase, $round, $teamCode);

        return new WP_REST_Response(['success' => true], 200);
    }

    public function getBurnageStatus(WP_REST_Request $request): WP_REST_Response
    {
        $season   = sanitize_text_field($request->get_param('season') ?? get_option('ttp_active_season', ''));
        $phase    = (int) ($request->get_param('phase') ?? get_option('ttp_active_phase', 1));
        $round    = (int) $request->get_param('round');
        $teamCode = sanitize_text_field($request->get_param('team_code') ?? '');

        $playerIdsParam = (string) ($request->get_param('player_ids') ?? '');
        $playerIds      = array_filter(array_map('intval', explode(',', $playerIdsParam)));

        if (! $season || ! $round || ! $teamCode || ! $playerIds) {
            return new WP_REST_Response([], 200);
        }

        $checker = new BurnageChecker();
        $result  = [];
        foreach ($playerIds as $playerId) {
            $result[$playerId] = $checker->statusFor($season, $phase, $round, $teamCode, $playerId)->toArray();
        }

        return new WP_REST_Response($result, 200);
    }

    public function canRead(): bool
    {
        return current_user_can('read');
    }

    public function canWrite(): bool
    {
        return current_user_can('edit_posts');
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }
}
