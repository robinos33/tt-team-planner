<?php
declare(strict_types=1);

namespace VibeKanban\TableauEquipes\Infrastructure\Persistence;

use VibeKanban\TableauEquipes\Domain\Entity\Equipe;
use VibeKanban\TableauEquipes\Domain\Repository\EquipeRepositoryInterface;

/**
 * Repository with fake data for équipes
 * Simulates 14 équipes (4 joueurs each)
 */
final class FakeEquipeRepository implements EquipeRepositoryInterface
{
    /** @var array<int, Equipe> */
    private array $equipes = [];

    public function __construct()
    {
        $this->initializeFakeData();
    }

    public function findById(int $id): ?Equipe
    {
        return $this->equipes[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->equipes);
    }

    public function findByDivision(int $division): array
    {
        return array_filter(
            $this->equipes,
            fn(Equipe $equipe) => $equipe->getDivision() === $division
        );
    }

    public function save(Equipe $equipe): void
    {
        $this->equipes[$equipe->getId()] = $equipe;
    }

    public function delete(int $id): void
    {
        unset($this->equipes[$id]);
    }

    /**
     * Initialize fake data - 14 équipes
     */
    private function initializeFakeData(): void
    {
        // Create 14 équipes across different divisions
        $equipeData = [
            // Division 1 (top level) - 2 équipes
            ['id' => 1, 'nom' => 'Équipe Senior A', 'division' => 1],
            ['id' => 2, 'nom' => 'Équipe Senior B', 'division' => 1],

            // Division 2 - 3 équipes
            ['id' => 3, 'nom' => 'Équipe Régionale 1', 'division' => 2],
            ['id' => 4, 'nom' => 'Équipe Régionale 2', 'division' => 2],
            ['id' => 5, 'nom' => 'Équipe Régionale 3', 'division' => 2],

            // Division 3 - 4 équipes
            ['id' => 6, 'nom' => 'Équipe Départementale 1', 'division' => 3],
            ['id' => 7, 'nom' => 'Équipe Départementale 2', 'division' => 3],
            ['id' => 8, 'nom' => 'Équipe Départementale 3', 'division' => 3],
            ['id' => 9, 'nom' => 'Équipe Départementale 4', 'division' => 3],

            // Division 4 - 3 équipes
            ['id' => 10, 'nom' => 'Équipe Promotion 1', 'division' => 4],
            ['id' => 11, 'nom' => 'Équipe Promotion 2', 'division' => 4],
            ['id' => 12, 'nom' => 'Équipe Promotion 3', 'division' => 4],

            // Division 5 (beginners) - 2 équipes
            ['id' => 13, 'nom' => 'Équipe Loisir A', 'division' => 5],
            ['id' => 14, 'nom' => 'Équipe Loisir B', 'division' => 5],
        ];

        foreach ($equipeData as $data) {
            $this->equipes[$data['id']] = new Equipe(
                id: $data['id'],
                nom: $data['nom'],
                division: $data['division'],
                maxJoueurs: 4
            );
        }
    }
}
