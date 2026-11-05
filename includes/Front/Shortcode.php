<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Front;

class Shortcode
{
    public function register(): void
    {
        add_shortcode('tt_team_planner', [$this, 'render']);
    }

    public function render(array $atts = []): string
    {
        if (! is_user_logged_in()) {
            return '<p class="ttp-login-required">' .
                esc_html__('Vous devez être connecté pour accéder à cette fonctionnalité.', 'tt-team-planner') .
                '</p>';
        }

        return '<div id="ttp-app" role="main" aria-label="' . esc_attr__('TT Team Planner', 'tt-team-planner') . '"></div>';
    }
}
