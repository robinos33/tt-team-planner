<?php
declare(strict_types=1);

namespace TT\TeamPlanner\Repository; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- PSR-4, TT\TeamPlanner est le préfixe plugin

use TT\TeamPlanner\Domain\MagicLink;

class MagicLinkRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tttp_magic_links';
    }

    /**
     * Crée un lien magique pour un joueur et retourne le token en clair
     * (seul son hash est stocké en base — un dump de la table ne suffit
     * pas à réutiliser les liens envoyés par mail).
     */
    public function create(int $playerId, string $season, int $ttlDays): string
    {
        global $wpdb;

        $token     = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttlDays * DAY_IN_SECONDS);

        $wpdb->insert($this->table, [ // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            'player_id'  => $playerId,
            'token_hash' => hash('sha256', $token),
            'season'     => $season,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    public function findValidByToken(string $token): ?MagicLink
    {
        global $wpdb;

        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE token_hash = %s", hash('sha256', $token)), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );

        if (! $row) {
            return null;
        }

        $link = MagicLink::fromRow($row);
        return $link->isExpired() ? null : $link;
    }

    public function markUsed(int $id): void
    {
        global $wpdb;
        $wpdb->update($this->table, ['used_at' => gmdate('Y-m-d H:i:s')], ['id' => $id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
