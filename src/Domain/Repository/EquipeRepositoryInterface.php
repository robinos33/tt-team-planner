<?php
declare(strict_types=1);

namespace VibeKanban\TableauEquipes\Domain\Repository;

use VibeKanban\TableauEquipes\Domain\Entity\Equipe;

/**
 * Interface pour le repository des équipes
 */
interface EquipeRepositoryInterface
{
    /**
     * Find équipe by ID
     */
    public function findById(int $id): ?Equipe;

    /**
     * Find all équipes
     * @return array<int, Equipe>
     */
    public function findAll(): array;

    /**
     * Find équipes by division
     * @return array<int, Equipe>
     */
    public function findByDivision(int $division): array;

    /**
     * Save équipe
     */
    public function save(Equipe $equipe): void;

    /**
     * Delete équipe
     */
    public function delete(int $id): void;
}
