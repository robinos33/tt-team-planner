<?php

declare(strict_types=1);

namespace TT\TeamPlanner\Admin;

/**
 * TT Team Planner n'est plus maintenu : la gestion des équipes est intégrée
 * à MonClubTT depuis sa version 2.0.0. Ce bandeau, affiché aux
 * administrateurs sur tout le back-office, explique comment migrer.
 *
 * Il n'est volontairement pas masquable : tant que le plugin est actif,
 * le module de MonClubTT reste en pause.
 */
class DeprecationNotice
{
    public const MCT_RELEASES = 'https://github.com/robinos33/MonClubTT/releases/latest';

    public function register(): void
    {
        add_action('admin_notices', [$this, 'render']);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo '<strong>' . esc_html__('TT Team Planner n\'est plus maintenu.', 'tt-team-planner') . '</strong> ';
        echo esc_html__('La gestion des équipes est désormais intégrée à MonClubTT (version 2.0.0 et suivantes, PHP 8.4 requis), qui reprend toutes vos données. Pour migrer :', 'tt-team-planner');
        echo '</p><ol style="margin-left:2em">';
        echo '<li>' . esc_html__('Installez ou mettez à jour MonClubTT en version 2.0.0 ou plus.', 'tt-team-planner') . '</li>';
        echo '<li><strong>' . esc_html__('Désactivez TT Team Planner sans le supprimer', 'tt-team-planner') . '</strong> ' . esc_html__('(sa suppression efface ses données).', 'tt-team-planner') . '</li>';
        echo '<li>' . esc_html__('Dans Mon Club TT › Gestion des équipes › Import / export, importez les données depuis TT Team Planner.', 'tt-team-planner') . '</li>';
        echo '<li>' . esc_html__('Vérifiez l\'application, renvoyez les liens magiques aux joueurs, puis supprimez TT Team Planner.', 'tt-team-planner') . '</li>';
        echo '</ol><p>';
        echo '<a href="' . esc_url(self::MCT_RELEASES) . '" class="button button-primary" target="_blank" rel="noopener">' . esc_html__('Télécharger MonClubTT', 'tt-team-planner') . '</a>';
        echo '</p></div>';
    }
}
