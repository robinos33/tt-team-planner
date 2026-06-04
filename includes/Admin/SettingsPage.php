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
            $code = sanitize_text_field($team['code'] ?? '');
            if ($code === '') {
                continue; // Ignore les lignes sans code
            }
            $result[] = [
                'code'  => $code,
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

        $this->renderTeamsSection();
        $this->renderDatesSection();

        submit_button(__('Enregistrer les reglages', 'tt-team-planner'));
        echo '</form>';

        echo '<hr>';
        $this->renderSyncSection($datapingActive, $lastSync, $repo);
        $this->renderSupportNotice();
        echo '</div>';
    }

    // =========================================================================
    // Sections privees
    // =========================================================================

    private function renderTeamsSection(): void
    {
        $teams = (array) get_option('ttp_teams', []);

        echo '<h2>' . esc_html__('Équipes', 'tt-team-planner') . '</h2>';
        echo '<p class="description">' . esc_html__('Définissez les équipes du club. Le code est utilisé pour grouper les joueurs par équipe habituelle.', 'tt-team-planner') . '</p>';

        echo '<datalist id="ttp-levels-list">';
        foreach ([
            'Pro A', 'Pro B',
            'Nationale 1', 'Nationale 2', 'Nationale 3',
            'Pré-Nationale',
            'Régionale 1', 'Régionale 2', 'Régionale 3',
            'Pré-Régionale',
            'Départementale 1', 'Départementale 2', 'Départementale 3', 'Départementale 4', 'Départementale 5',
        ] as $lvl) {
            echo '<option value="' . esc_attr($lvl) . '">';
        }
        echo '</datalist>';

        echo '<table id="ttp-teams-table" class="widefat" style="max-width:700px;margin-bottom:12px">';
        echo '<thead><tr>';
        echo '<th style="width:80px">' . esc_html__('Code', 'tt-team-planner') . '</th>';
        echo '<th>' . esc_html__('Nom', 'tt-team-planner') . '</th>';
        echo '<th style="width:140px">' . esc_html__('Niveau', 'tt-team-planner') . '</th>';
        echo '<th style="width:60px">' . esc_html__('Couleur', 'tt-team-planner') . '</th>';
        echo '<th style="width:36px"></th>';
        echo '</tr></thead>';
        echo '<tbody id="ttp-teams-body">';

        foreach ($teams as $i => $team) {
            $this->renderTeamRow((int) $i, $team);
        }

        echo '</tbody></table>';

        echo '<button type="button" id="ttp-add-team" class="button">';
        echo '+ ' . esc_html__('Ajouter une équipe', 'tt-team-planner');
        echo '</button>';

        // Template de ligne (caché) cloné par le JS
        echo '<template id="ttp-team-row-tpl">';
        echo '<tr class="ttp-team-row">';
        echo '<td><input type="text" name="ttp_teams[__i__][code]" value="" placeholder="auto" class="small-text ttp-team-code" style="color:#6b7280;font-style:italic"></td>';
        echo '<td><input type="text" name="ttp_teams[__i__][name]" value="" placeholder="Équipe 1" class="regular-text ttp-team-name" required></td>';
        echo '<td><input type="text" name="ttp_teams[__i__][level]" value="" placeholder="Régionale 1" list="ttp-levels-list" class="regular-text ttp-team-level"></td>';
        echo '<td><input type="color" name="ttp_teams[__i__][color]" value="#2563eb" style="width:44px;height:30px;padding:2px;cursor:pointer"></td>';
        echo '<td><button type="button" class="button ttp-remove-team" title="' . esc_attr__('Supprimer', 'tt-team-planner') . '">✕</button></td>';
        echo '</tr>';
        echo '</template>';

        echo '<script>
        (function () {
            var tbody  = document.getElementById("ttp-teams-body");
            var addBtn = document.getElementById("ttp-add-team");
            var tpl    = document.getElementById("ttp-team-row-tpl");

            function autoCode(name) {
                return (name.normalize("NFD").replace(/[̀-ͯ]/g, "")
                    .match(/[A-Za-z]+|\d+/g) || [])
                    .map(function (t) { return /^\d+$/.test(t) ? t : t[0].toUpperCase(); })
                    .join("");
            }

            function reIndex() {
                tbody.querySelectorAll("tr.ttp-team-row").forEach(function (row, i) {
                    row.querySelectorAll("[name]").forEach(function (el) {
                        el.name = el.name.replace(/\[\d+\]/, "[" + i + "]");
                    });
                });
            }

            function bindRow(row) {
                var nameInput = row.querySelector(".ttp-team-name");
                var codeInput = row.querySelector(".ttp-team-code");

                // Auto-fill code from name unless manually overridden
                nameInput.addEventListener("input", function () {
                    if (codeInput.dataset.manual !== "1") {
                        codeInput.value = autoCode(nameInput.value);
                    }
                });
                codeInput.addEventListener("input", function () {
                    codeInput.dataset.manual = codeInput.value ? "1" : "0";
                });
                codeInput.addEventListener("blur", function () {
                    // Sync back if user cleared the code field
                    if (!codeInput.value) {
                        codeInput.dataset.manual = "0";
                        codeInput.value = autoCode(nameInput.value);
                    }
                });

                row.querySelector(".ttp-remove-team").addEventListener("click", function () {
                    row.remove();
                    reIndex();
                });
            }

            tbody.querySelectorAll("tr.ttp-team-row").forEach(function (row) {
                // Existing rows: code already set, mark as manual
                var codeInput = row.querySelector(".ttp-team-code");
                if (codeInput.value) codeInput.dataset.manual = "1";
                bindRow(row);
            });

            addBtn.addEventListener("click", function () {
                var count = tbody.querySelectorAll("tr.ttp-team-row").length;
                var frag  = tpl.content.cloneNode(true);
                var row   = frag.querySelector("tr");
                row.querySelectorAll("[name]").forEach(function (el) {
                    el.name = el.name.replace("__i__", count);
                });
                tbody.appendChild(frag);
                row = tbody.querySelector("tr.ttp-team-row:last-child");
                bindRow(row);
                row.querySelector(".ttp-team-name").focus();
            });
        })();
        </script>';
    }

    private function renderTeamRow(int $i, array $team): void
    {
        $code  = esc_attr($team['code']  ?? '');
        $name  = esc_attr($team['name']  ?? '');
        $level = esc_attr($team['level'] ?? '');
        $color = esc_attr($team['color'] ?? '#2563eb');

        echo '<tr class="ttp-team-row">';
        echo '<td><input type="text" name="ttp_teams[' . $i . '][code]" value="' . $code . '" placeholder="auto" class="small-text ttp-team-code"></td>';
        echo '<td><input type="text" name="ttp_teams[' . $i . '][name]" value="' . $name . '" placeholder="Équipe 1" class="regular-text ttp-team-name" required></td>';
        echo '<td><input type="text" name="ttp_teams[' . $i . '][level]" value="' . $level . '" placeholder="Régionale 1" list="ttp-levels-list" class="regular-text ttp-team-level"></td>';
        echo '<td><input type="color" name="ttp_teams[' . $i . '][color]" value="' . $color . '" style="width:44px;height:30px;padding:2px;cursor:pointer"></td>';
        echo '<td><button type="button" class="button ttp-remove-team" title="' . esc_attr__('Supprimer', 'tt-team-planner') . '">✕</button></td>';
        echo '</tr>';
    }

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

    private function renderSupportNotice(): void
    {
        $subject = rawurlencode('[TT Team Planner v' . TTP_VERSION . '] ');
        $mailto  = 'mailto:robin.aldasoro@gmail.com?subject=' . $subject;

        echo '<div style="margin-top:24px;padding:14px 16px;background:#f0f6ff;border-left:4px solid #2563eb;border-radius:0 6px 6px 0">';
        echo '<strong style="font-size:13px">💬 ' . esc_html__('Support & suggestions', 'tt-team-planner') . '</strong>';
        echo '<p style="margin:6px 0 0;font-size:13px;color:#374151">';
        echo esc_html__('Un bug à signaler ou une nouvelle fonctionnalité à proposer ?', 'tt-team-planner') . ' ';
        echo '<a href="' . esc_url($mailto) . '">' . esc_html__('Envoyer un e-mail', 'tt-team-planner') . '</a>.';
        echo '</p>';
        echo '</div>';
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
