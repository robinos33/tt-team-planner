<?php
declare(strict_types=1);

namespace VibeKanban\TableauEquipes\Domain\Repository;

use VibeKanban\TableauEquipes\Domain\Entity\Joueur;

/**
 * Interface pour le repository des joueurs
 */
interface JoueurRepositoryInterface
{
    /**
     * Find joueur by ID
     */
    public function findById(int $id): ?Joueur;

    /**
     * Find all joueurs
     * @return array<int, Joueur>
     */
    public function findAll(): array;

    /**
     * Find joueurs by IDs
     * @param array<int, int> $ids
     * @return array<int, Joueur>
     */
    public function findByIds(array $ids): array;

    /**
     * Find joueurs by classement range
     * @return array<int, Joueur>
     */
    public function findByClassementRange(int $min, int $max): array;

    /**
     * Save joueur
     */
    public function save(Joueur $joueur): void;

    /**
     * Delete joueur
     */
    public function delete(int $id): void;
}
