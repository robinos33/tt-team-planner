<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Front;

class Assets
{
    /** Max joueurs étrangers par composition — valeur fixe V1. */
    private const MAX_FOREIGN = 2;

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontend']);
        add_action('wp_head',           [$this, 'injectManifestLink']);
    }

    public function enqueueFrontend(): void
    {
        global $post;
        if (! $post || ! has_shortcode($post->post_content, 'tt_team_planner')) {
            return;
        }

        wp_enqueue_style('ttp-app',  TTP_PLUGIN_URL . 'assets/css/app.css', [], TTP_VERSION);
        wp_enqueue_script('ttp-app', TTP_PLUGIN_URL . 'assets/js/app.js',  [], TTP_VERSION, true);
        wp_localize_script('ttp-app', 'TTPConfig', $this->buildConfig());
    }

    public function injectManifestLink(): void
    {
        global $post;
        if (! $post || ! has_shortcode($post->post_content, 'tt_team_planner')) {
            return;
        }
        echo '<link rel="manifest" href="' . esc_url(TTP_PLUGIN_URL . 'assets/manifest.json') . '">' . "\n";
    }

    public function buildConfig(): array
    {
        $season = self::computeSeason();
        $phase  = self::detectPhase();
        $teams  = get_option('ttp_teams', []);

        return [
            'apiBase'           => rest_url('ttp/v1'),
            'nonce'             => wp_create_nonce('wp_rest'),
            'clubName'          => get_option('ttp_club_name', get_bloginfo('name')),
            'season'            => $season,
            'phase'             => $phase - 1, // 0-indexed pour le JS
            'teamsCount'        => count($teams),
            'teams'             => $teams,
            'journeeDates'      => (array) get_option('ttp_journee_dates_p' . $phase, []),
            'maxForeignPlayers' => self::MAX_FOREIGN,
            'smsTemplates'      => [
                'availability' => get_option('ttp_sms_template_availability', ''),
                'confirmation' => get_option('ttp_sms_template_confirmation', ''),
            ],
            'swUrl'             => TTP_PLUGIN_URL . 'assets/service-worker.js',
        ];
    }

    // ─── Helpers statiques ────────────────────────────────────────────────────

    /**
     * Saison courante calculée depuis la date système.
     * Septembre = début de saison : 2025-09 → "2025-2026".
     */
    public static function computeSeason(): string
    {
        $year  = (int) current_time('Y');
        $month = (int) current_time('n');
        $start = $month >= 9 ? $year : $year - 1;
        return $start . '-' . ($start + 1);
    }

    /**
     * Phase active (1 ou 2) détectée depuis les dates saisies en BO.
     *
     * Règle :
     *  1. Si aujourd'hui est dans la plage [première date … dernière date] d'une phase → cette phase.
     *  2. Sinon, la phase dont la prochaine journée est la plus proche dans le futur.
     *  3. Fallback : phase 1.
     */
    public static function detectPhase(): int
    {
        $today = current_time('Y-m-d');

        // Passe 1 — phase en cours
        for ($p = 1; $p <= 2; $p++) {
            $dates = array_filter((array) get_option('ttp_journee_dates_p' . $p, []));
            if (empty($dates)) {
                continue;
            }
            if ($today >= min($dates) && $today <= max($dates)) {
                return $p;
            }
        }

        // Passe 2 — prochaine phase à venir
        $bestTs    = null;
        $bestPhase = 1;
        $nowTs     = strtotime($today);

        for ($p = 1; $p <= 2; $p++) {
            $dates = array_filter((array) get_option('ttp_journee_dates_p' . $p, []));
            foreach ($dates as $d) {
                $ts = strtotime((string) $d);
                if ($ts !== false && $ts >= $nowTs && ($bestTs === null || $ts < $bestTs)) {
                    $bestTs    = $ts;
                    $bestPhase = $p;
                }
            }
        }

        return $bestPhase;
    }
}
