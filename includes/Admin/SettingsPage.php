<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Admin;

use TT\TeamPlanner\Repository\PlayerRepository;

class SettingsPage
{
    private const MCT_GITHUB  = 'https://github.com/robinos33/MonClubTT';
    private const MCT_ZIP_URL = 'https://github.com/robinos33/MonClubTT/archive/refs/heads/master.zip';

    // =========================================================================
    // Enregistrement
    // =========================================================================

    public function registerSettings(): void
    {
        add_action('admin_post_ttp_create_front_page', [$this, 'handleCreateFrontPage']);

        register_setting('ttp_settings', 'ttp_club_name', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('ttp_settings', 'ttp_teams',                     ['sanitize_callback' => [$this, 'sanitizeTeams']]);
        register_setting('ttp_settings', 'ttp_sms_template_availability', ['sanitize_callback' => 'sanitize_textarea_field']);
        register_setting('ttp_settings', 'ttp_sms_template_confirmation', ['sanitize_callback' => 'sanitize_textarea_field']);
        register_setting('ttp_settings', 'ttp_journee_dates_p1',          ['sanitize_callback' => [$this, 'sanitizeDates']]);
        register_setting('ttp_settings', 'ttp_journee_dates_p2',          ['sanitize_callback' => [$this, 'sanitizeDates']]);
    }

    public function sanitizeTeams(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $result = [];
        foreach ($raw as $team) {
            if (! is_array($team)) {
                continue;
            }
            $result[] = [
                'code'  => sanitize_text_field($team['code']  ?? ''),
                'name'  => sanitize_text_field($team['name']  ?? ''),
                'level' => sanitize_text_field($team['level'] ?? ''),
                'color' => sanitize_hex_color($team['color']  ?? '#2563eb') ?? '#2563eb',
            ];
        }
        return $result;
    }

    /** Valide un tableau de 7 dates ISO (YYYY-MM-DD), ignore les invalides. */
    public function sanitizeDates(mixed $raw): array
    {
        if (! is_array($raw)) {
            return array_fill(0, 7, '');
        }
        $result = [];
        for ($i = 0; $i < 7; $i++) {
            $val = trim($raw[$i] ?? '');
            // Accepte YYYY-MM-DD ou vide
            $result[] = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val) && strtotime($val) !== false)
                ? $val
                : '';
        }
        return $result;
    }

    // =========================================================================
    // Handler admin-post
    // =========================================================================

    /** Cree une page WP publiee contenant le shortcode [tt_team_planner]. */
    public function handleCreateFrontPage(): void
    {
        check_admin_referer('ttp_create_front_page');

        if (! current_user_can('publish_pages')) {
            wp_die(esc_html__('Permission insuffisante.', 'tt-team-planner'));
        }

        $clubName = (string) get_option('ttp_club_name', 'TT Team Planner');

        $pageId = wp_insert_post([
            'post_title'   => sanitize_text_field($clubName),
            'post_content' => '[tt_team_planner]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ], true);

        if (is_wp_error($pageId)) {
            wp_redirect(add_query_arg(
                ['page' => 'tt-team-planner', 'ttp_notice' => 'create_error'],
                admin_url('admin.php')
            ));
        } else {
            wp_redirect(add_query_arg(
                ['page' => 'tt-team-planner', 'ttp_notice' => 'created', 'ttp_page_id' => $pageId],
                admin_url('admin.php')
            ));
        }
        exit;
    }

    // =========================================================================
    // Rendu principal
    // =========================================================================

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Acces refuse.', 'tt-team-planner'));
        }

        $repo           = new PlayerRepository();
        $lastSync       = get_option('ttp_last_sync');
        $datapingActive = has_filter('monclubtt_get_joueurs');

        echo '<div class="wrap">';

        // Titre + lien front
        echo '<h1>' . esc_html__('TT Team Planner', 'tt-team-planner') . ' ';
        $this->renderFrontLink();
        echo '</h1>';

        // Bannieres
        $this->renderMonClubTTBanner($datapingActive);
        $this->renderRedirectNotice();
        settings_errors('ttp_settings');

        // Formulaire de reglages
        echo '<form method="post" action="' . esc_url(admin_url('options.php')) . '">';
        settings_fields('ttp_settings');

        echo '<h2>' . esc_html__('Reglages', 'tt-team-planner') . '</h2>';
        echo '<table class="form-table" role="presentation">';

        // Nom du club
        echo '<tr>';
        echo '<th scope="row"><label for="ttp_club_name">' . esc_html__('Nom du club', 'tt-team-planner') . '</label></th>';
        echo '<td><input type="text" id="ttp_club_name" name="ttp_club_name" value="' . esc_attr(get_option('ttp_club_name', '')) . '" class="regular-text"></td>';
        echo '</tr>';

        // Saison + phase détectées automatiquement — lecture seule
        $detectedSeason = \TT\TeamPlanner\Front\Assets::computeSeason();
        $detectedPhase  = \TT\TeamPlanner\Front\Assets::detectPhase();
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Saison / Phase (auto)', 'tt-team-planner') . '</th>';
        echo '<td><code>' . esc_html($detectedSeason) . '</code> &nbsp; Phase <code>' . esc_html((string) $detectedPhase) . '</code>';
        echo ' <span class="description">' . esc_html__('Calculees depuis la date du jour et les dates des journees.', 'tt-team-planner') . '</span></td>';
        echo '</tr>';

        echo '</table>';

        // SMS
        echo '<h2>' . esc_html__('SMS pre-rediges', 'tt-team-planner') . '</h2>';
        echo '<p class="description">' . esc_html__('Variables : {prenom}, {nom}, {journee}, {date}, {equipe}', 'tt-team-planner') . '</p>';
        echo '<table class="form-table" role="presentation">';

        echo '<tr>';
        echo '<th scope="row"><label for="ttp_sms_avail">' . esc_html__('Demande de disponibilite', 'tt-team-planner') . '</label></th>';
        echo '<td><textarea id="ttp_sms_avail" name="ttp_sms_template_availability" rows="3" class="large-text">' . esc_textarea(get_option('ttp_sms_template_availability', '')) . '</textarea></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="ttp_sms_confirm">' . esc_html__('Confirmation de composition', 'tt-team-planner') . '</label></th>';
        echo '<td><textarea id="ttp_sms_confirm" name="ttp_sms_template_confirmation" rows="3" class="large-text">' . esc_textarea(get_option('ttp_sms_template_confirmation', '')) . '</textarea></td>';
        echo '</tr>';

        echo '</table>';

        $this->renderDatesSection();

        submit_button(__('Enregistrer les reglages', 'tt-team-planner'));
        echo '</form>';

        echo '<hr>';
        $this->renderSyncSection($datapingActive, $lastSync, $repo);
        echo '</div>';
    }

    // =========================================================================
    // Sections privees
    // =========================================================================

    private function renderDatesSection(): void
    {
        $season = \TT\TeamPlanner\Front\Assets::computeSeason();

        echo '<h2>' . esc_html__('Dates des journees', 'tt-team-planner') . '</h2>';
        echo '<p class="description">' . esc_html__('Saisissez les 7 dates de chaque phase. Ces dates sont affichees dans l\'application et envoyees par SMS.', 'tt-team-planner') . '</p>';

        echo '<div style="display:flex;gap:48px;flex-wrap:wrap">';
        foreach ([1 => 'ttp_journee_dates_p1', 2 => 'ttp_journee_dates_p2'] as $num => $key) {
            $dates = (array) get_option($key, array_fill(0, 7, ''));
            if (count($dates) < 7) {
                $dates = array_pad($dates, 7, '');
            }

            echo '<div>';
            echo '<h3 style="margin-bottom:8px">Phase ' . $num . '</h3>';
            echo '<table role="presentation">';
            for ($j = 1; $j <= 7; $j++) {
                $val  = esc_attr($dates[$j - 1] ?? '');
                $id   = 'ttp_date_p' . $num . '_j' . $j;
                $name = $key . '[' . ($j - 1) . ']';
                echo '<tr style="margin-bottom:4px">';
                echo '<th scope="row" style="width:36px;font-weight:600;padding:4px 8px 4px 0"><label for="' . $id . '">J' . $j . '</label></th>';
                echo '<td><input type="date" id="' . $id . '" name="' . $name . '" value="' . $val . '"></td>';
                echo '</tr>';
            }
            echo '</table>';
            echo '</div>';
        }
        echo '</div>';

        // Un seul bouton reset pour toute la saison
        $confirmMsg = esc_js(__('ATTENTION : supprime toutes les compositions et disponibilites des deux phases. Tapez RESET pour confirmer.', 'tt-team-planner'));
        $resetUrl   = esc_url(rest_url('ttp/v1/season/reset'));
        $nonce      = esc_js(wp_create_nonce('wp_rest'));

        echo "<div style='margin-top:20px'>";
        echo "<button type='button' class='button' style='color:#b91c1c;border-color:#b91c1c'"
           . " onclick=\"(function(){"
           . "if(prompt('{$confirmMsg}')!=='RESET')return;"
           . "var url='{$resetUrl}',h={'Content-Type':'application/json','X-WP-Nonce':'{$nonce}'};"
           . "Promise.all([1,2].map(function(p){return fetch(url,{method:'POST',headers:h,body:JSON.stringify({phase:p})}).then(function(r){return r.json();});}))"
           . ".then(function(r){alert(r.map(function(d){return d.message;}).join('\n'));location.reload();})"
           . ".catch(function(){alert('" . esc_js(__('Erreur lors de la reinitialisation.', 'tt-team-planner')) . "');});"
           . "})()\">"
           . "&#x1F5D1; " . esc_html__('Reinitialiser la saison', 'tt-team-planner')
           . " " . esc_html($season)
           . "</button>";
        echo "</div>";
    }

    private function renderRedirectNotice(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $notice = isset($_GET['ttp_notice']) ? sanitize_key($_GET['ttp_notice']) : '';
        $pageId = isset($_GET['ttp_page_id']) ? (int) $_GET['ttp_page_id'] : 0;
        // phpcs:enable

        if ($notice === 'created' && $pageId > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Page creee avec succes.', 'tt-team-planner') . ' ';
            echo '<a href="' . esc_url((string) get_permalink($pageId)) . '" target="_blank" rel="noopener"><strong>' . esc_html__("Voir l'application", 'tt-team-planner') . ' &rarr;</strong></a>';
            echo ' &mdash; <a href="' . esc_url((string) get_edit_post_link($pageId)) . '">' . esc_html__('Modifier la page', 'tt-team-planner') . '</a>';
            echo '</p></div>';
        } elseif ($notice === 'create_error') {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo esc_html__('Erreur lors de la creation de la page.', 'tt-team-planner');
            echo '</p></div>';
        }
    }

    private function renderFrontLink(): void
    {
        global $wpdb;

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
            echo '<a href="' . esc_url((string) get_permalink((int) $page->ID)) . '" class="page-title-action" target="_blank" rel="noopener">';
            echo '&#x1F3D3; ' . esc_html__("Ouvrir l'application", 'tt-team-planner') . ' &rarr;';
            echo '</a>';
        } else {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;margin-left:6px">';
            echo '<input type="hidden" name="action" value="ttp_create_front_page">';
            wp_nonce_field('ttp_create_front_page');
            echo '<button type="submit" class="page-title-action">+ ' . esc_html__('Creer la page front', 'tt-team-planner') . '</button>';
            echo '</form>';
        }
    }

    private function renderMonClubTTBanner(bool $active): void
    {
        if ($active) {
            echo '<div class="notice notice-success inline" style="margin-bottom:16px"><p>';
            echo '<strong>MonClubTT</strong> &mdash; ';
            echo esc_html__('Plugin detecte et actif. Les joueurs sont synchronises depuis le cache FFTT.', 'tt-team-planner');
            echo '</p></div>';
            return;
        }

        echo '<div class="notice notice-error inline" style="margin-bottom:16px"><p>';
        echo '<strong>MonClubTT</strong> &mdash; ';
        echo esc_html__('Plugin introuvable ou desactive. TT Team Planner en depend pour importer les joueurs.', 'tt-team-planner');
        echo ' &nbsp;<a href="' . esc_url(self::MCT_GITHUB) . '" class="button button-small" target="_blank" rel="noopener">' . esc_html__('Voir sur GitHub', 'tt-team-planner') . '</a>';
        echo ' &nbsp;<a href="' . esc_url(self::MCT_ZIP_URL) . '" class="button button-small button-primary" target="_blank" rel="noopener">&darr; ' . esc_html__('Telecharger le .zip', 'tt-team-planner') . '</a>';
        echo '</p></div>';
    }

    private function renderSyncSection(bool $datapingActive, mixed $lastSync, PlayerRepository $repo): void
    {
        echo '<h2>' . esc_html__('Joueurs - synchronisation', 'tt-team-planner') . '</h2>';
        echo '<p>';
        echo esc_html__('Importe les joueurs depuis MonClubTT dans la base TT Team Planner. A relancer apres chaque synchro MonClubTT ou en debut de saison.', 'tt-team-planner');
        echo '<br><em>' . esc_html__('Les donnees manuelles (telephone, equipe habituelle, capitaine, notes) sont preservees.', 'tt-team-planner') . '</em>';
        echo '</p>';

        echo '<table class="form-table" role="presentation" style="margin-bottom:12px">';
        echo '<tr><th>' . esc_html__('Derniere synchronisation', 'tt-team-planner') . '</th>';
        echo '<td>' . esc_html($lastSync ? (string) $lastSync : __('Jamais', 'tt-team-planner')) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Joueurs en base', 'tt-team-planner') . '</th>';
        echo '<td id="ttp-player-count">' . esc_html((string) $repo->count()) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Version', 'tt-team-planner') . '</th>';
        echo '<td>' . esc_html(TTP_VERSION) . '</td></tr>';
        echo '</table>';

        $disabled = $datapingActive ? '' : ' disabled';
        echo '<button id="ttp-sync-btn" class="button button-primary"' . $disabled . '>';
        echo esc_html__('Synchroniser les joueurs', 'tt-team-planner');
        echo '</button>';
        echo '<div id="ttp-sync-result" style="margin-top:12px;display:none"></div>';

        $syncUrl   = esc_url(rest_url('ttp/v1/players/sync'));
        $nonce     = esc_js(wp_create_nonce('wp_rest'));
        $lblWait   = esc_js(__('Synchronisation en cours...', 'tt-team-planner'));
        $lblSync   = esc_js(__('Synchroniser les joueurs', 'tt-team-planner'));
        $lblNet    = esc_js(__('Erreur reseau.', 'tt-team-planner'));

        echo "<script>
        document.getElementById('ttp-sync-btn')?.addEventListener('click', async function () {
            var btn    = this;
            var result = document.getElementById('ttp-sync-result');
            btn.disabled    = true;
            btn.textContent = '{$lblWait}';
            result.style.display = 'none';

            var res, text;
            try {
                res  = await fetch('{$syncUrl}', { method: 'POST', headers: { 'X-WP-Nonce': '{$nonce}' } });
                text = await res.text();
            } catch (e) {
                result.style.display = 'block';
                result.className     = 'notice notice-error';
                result.innerHTML     = '<p>Erreur reseau : ' + e.message + '</p>';
                btn.disabled = false; btn.textContent = '{$lblSync}';
                return;
            }

            try {
                var data = JSON.parse(text);
                result.style.display = 'block';
                result.className     = 'notice ' + (res.ok ? 'notice-success' : 'notice-error');
                result.innerHTML     = '<p>' + (data.message || JSON.stringify(data)) + '</p>';
                if (res.ok && data.synced !== undefined) {
                    var c = document.getElementById('ttp-player-count');
                    if (c) c.textContent = data.synced;
                }
            } catch (e) {
                // La reponse n'est pas du JSON (ex: erreur PHP fatale) — on l'affiche brut
                result.style.display = 'block';
                result.className     = 'notice notice-error';
                result.innerHTML     = '<p>HTTP ' + res.status + ' — reponse non-JSON :</p>'
                    + '<pre style=\"max-height:200px;overflow:auto;font-size:11px\">' + text.substring(0, 2000) + '</pre>';
            }

            btn.disabled    = false;
            btn.textContent = '{$lblSync}';
        });
        </script>";
    }
}
