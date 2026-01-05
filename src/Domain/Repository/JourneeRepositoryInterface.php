<?php
declare(strict_types=1);

namespace VibeKanban\TableauEquipes\Domain\Repository;

use VibeKanban\TableauEquipes\Domain\Entity\Journee;

/**
 * Interface pour le repository des journées
 */
interface JourneeRepositoryInterface
{
    /**
     * Find journée by ID
     */
    public function findById(int $id): ?Journee;

    /**
     * Find all journées
     * @return array<int, Journee>
     */
    public function findAll(): array;

    /**
     * Find journée by numero
     */
    public function findByNumero(int $numero): ?Journee;

    /**
     * Find journées by statut
     * @return array<int, Journee>
     */
    public function findByStatut(string $statut): array;

    /**
     * Save journée
     */
    public function save(Journee $journee): void;

    /**
     * Delete journée
     */
    public function delete(int $id): void;
}
