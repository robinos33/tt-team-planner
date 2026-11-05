<?php
declare(strict_types=1);

namespace TT\TeamPlanner; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- PSR-4, TT\TeamPlanner est le préfixe plugin

/**
 * Droit de gérer le planner (compos, effectifs, dispos, joueurs, équipes,
 * magic links), indépendant des droits d'édition de WordPress.
 *
 * Le rôle « Membre commission équipe » ne porte que ce droit et la lecture :
 * on peut confier le planner à un capitaine sans lui ouvrir le back-office.
 */
final class Capabilities
{
    public const MANAGE = 'ttp_manage_planner';
    public const ROLE   = 'ttp_planner';

    /** Nom du rôle en 1.9.0, renommé à la migration. */
    private const LEGACY_LABEL = 'Gestionnaire planner';

    /** Rôles natifs qui reçoivent le droit à la création du rôle. */
    private const GRANTED_ROLES = ['administrator', 'editor'];

    public static function canManagePlanner(): bool
    {
        return current_user_can(self::MANAGE);
    }

    /**
     * Crée le rôle et donne le droit aux administrateurs et éditeurs.
     *
     * Ne fait rien si le rôle existe déjà : un droit retiré à la main
     * (aux éditeurs, par exemple) n'est pas rendu à la mise à jour suivante.
     */
    public static function install(): void
    {
        if (get_role(self::ROLE) !== null) {
            self::renameLegacyRole();
            return;
        }

        add_role(self::ROLE, self::label(), [
            'read'       => true,
            self::MANAGE => true,
        ]);

        foreach (self::GRANTED_ROLES as $name) {
            get_role($name)?->add_cap(self::MANAGE);
        }
    }

    /**
     * WordPress stocke le nom du rôle à sa création : un rôle créé en 1.9.0
     * s'appelle encore « Gestionnaire planner ». On le recrée sous son nom
     * actuel, avec ses droits du moment ; les utilisateurs le gardent, leur
     * compte ne référence que le slug. Un nom changé à la main est respecté.
     */
    private static function renameLegacyRole(): void
    {
        if ((wp_roles()->role_names[self::ROLE] ?? null) !== self::LEGACY_LABEL) {
            return;
        }

        $capabilities = get_role(self::ROLE)->capabilities;
        remove_role(self::ROLE);
        add_role(self::ROLE, self::label(), $capabilities);
    }

    private static function label(): string
    {
        return __('Membre commission équipe', 'tt-team-planner');
    }

    /** Retire le rôle et le droit de tous les rôles qui l'ont reçu. */
    public static function uninstall(): void
    {
        remove_role(self::ROLE);

        foreach (array_keys(wp_roles()->roles) as $name) {
            get_role($name)?->remove_cap(self::MANAGE);
        }
    }
}
