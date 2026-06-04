<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Front;

/**
 * Remplace le thème WordPress par une page HTML autonome
 * pour toute page contenant le shortcode [tt_team_planner].
 *
 * - Non connecté  → redirection vers wp-login.php (avec retour vers l'app)
 * - Connecté      → HTML minimal, zero chrome WordPress
 */
class StandaloneTemplate
{
    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybeServeApp']);
    }

    public function maybeServeApp(): void
    {
        global $post;

        if (! $post || ! has_shortcode($post->post_content, 'tt_team_planner')) {
            return;
        }

        // Garde d'authentification — redirige vers login avec retour
        if (! is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(get_permalink((int) $post->ID)));
            exit;
        }

        $config      = (new Assets())->buildConfig();
        $configJson  = wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
        $clubName    = esc_html(get_option('ttp_club_name', 'TT Team Planner'));
        $cssUrl      = esc_url(TTP_PLUGIN_URL . 'assets/css/app.css?v=' . TTP_VERSION);
        $jsUrl       = esc_url(TTP_PLUGIN_URL . 'assets/js/app.js?v='  . TTP_VERSION);
        $manifestUrl = esc_url(TTP_PLUGIN_URL . 'assets/manifest.json');

        // On prend la main sur la réponse
        status_header(200);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#2563eb">
  <meta name="robots" content="noindex,nofollow">
  <title>' . $clubName . ' — TT Team Planner</title>
  <link rel="manifest" href="' . $manifestUrl . '">
  <link rel="stylesheet" href="' . $cssUrl . '">
</head>
<body style="margin:0;padding:0;overflow:hidden;background:#f5f7fb;height:100dvh">
  <div id="ttp-app"></div>
  <script>window.TTPConfig = ' . $configJson . ';</script>
  <script src="' . $jsUrl . '"></script>
</body>
</html>';
        // phpcs:enable

        exit;
    }
}
