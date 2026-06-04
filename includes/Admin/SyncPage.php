<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Admin; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- PSR-4, TT\TeamPlanner est le préfixe plugin

class SyncPage
{
    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'tt-team-planner'));
        }

        $datapingActive = has_filter('monclubtt_get_joueurs');
        $lastSync       = get_option('ttp_last_sync');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('TT Team Planner — Synchronisation', 'tt-team-planner'); ?></h1>

            <?php if ($datapingActive) : ?>
                <div class="notice notice-success inline">
                    <p><?php esc_html_e('MonClubTT est actif — la synchronisation utilisera ses données FFTT.', 'tt-team-planner'); ?></p>
                </div>
            <?php else : ?>
                <div class="notice notice-error inline">
                    <p>
                        <?php esc_html_e('MonClubTT est introuvable ou désactivé. La synchronisation ne peut pas fonctionner.', 'tt-team-planner'); ?>
                        <a href="<?php echo esc_url(admin_url('plugins.php')); ?>">
                            <?php esc_html_e('Gérer les extensions →', 'tt-team-planner'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

            <p>
                <?php esc_html_e('Importe les joueurs du club depuis MonClubTT (cache FFTT) dans la base TT Team Planner.', 'tt-team-planner'); ?><br>
                <strong><?php esc_html_e('Les données manuelles (téléphone, équipe habituelle, capitaine, notes) sont préservées lors de chaque synchro.', 'tt-team-planner'); ?></strong>
            </p>

            <?php if ($lastSync) : ?>
                <p class="description">
                    <?php printf(
                        /* translators: %s = date de dernière synchro */
                        esc_html__('Dernière synchronisation : %s', 'tt-team-planner'),
                        esc_html($lastSync)
                    ); ?>
                </p>
            <?php endif; ?>

            <button id="ttp-sync-btn" class="button button-primary" <?php echo $datapingActive ? '' : 'disabled'; ?>>
                <?php esc_html_e('Synchroniser maintenant', 'tt-team-planner'); ?>
            </button>

            <div id="ttp-sync-result" style="margin-top:16px;display:none"></div>

            <script>
            document.getElementById('ttp-sync-btn')?.addEventListener('click', async function() {
                const btn    = this;
                const result = document.getElementById('ttp-sync-result');
                btn.disabled    = true;
                btn.textContent = '<?php echo esc_js(__('Synchronisation en cours…', 'tt-team-planner')); ?>';
                result.style.display = 'none';

                try {
                    const res  = await fetch('<?php echo esc_url(rest_url('ttp/v1/players/sync')); ?>', {
                        method: 'POST',
                        headers: { 'X-WP-Nonce': '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>' }
                    });
                    const data = await res.json();
                    result.style.display = 'block';
                    result.className     = res.ok ? 'notice notice-success' : 'notice notice-error';
                    result.innerHTML     = '<p>' + (data.message || JSON.stringify(data)) + '</p>';
                } catch (e) {
                    result.style.display = 'block';
                    result.className     = 'notice notice-error';
                    result.innerHTML     = '<p><?php echo esc_js(__('Erreur réseau.', 'tt-team-planner')); ?></p>';
                }

                btn.disabled    = false;
                btn.textContent = '<?php echo esc_js(__('Synchroniser maintenant', 'tt-team-planner')); ?>';
            });
            </script>
        </div>
        <?php
    }
}
